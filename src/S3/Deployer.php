<?php

namespace StaticDeploy\S3;

use Aws\CloudFront\CloudFrontClient;
use Aws\CommandPool;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use StaticDeploy\DeployCache;
use StaticDeploy\DeployerTrait;
use StaticDeploy\Options;
use StaticDeploy\WsLog;

class Deployer {

    use DeployerTrait;

    const DEFAULT_NAMESPACE = 'static-deploy-addon-s3/default';

    /**
     * @var integer
     */
    private $cf_max_paths = 0;

    /**
     * @var array
     */
    private $cf_stale_paths = [];

    /**
     * @var integer
     */
    private $deployed_ct = 0;

    /**
     * @var integer
     */
    private $deploy_cache_ct = 0;

    /**
     * @var integer
     */
    private $deploy_error_ct = 0;

    /**
     * @var S3Client
     */
    private $s3_client;

    public function __construct() {
        $cf_max_paths_str = S3Options::getValue( 'maxPathsToInvalidate' );
        $this->cf_max_paths = intval( $cf_max_paths_str );
        $this->s3_client = self::s3Client();
    }

    public static function getDeployerSlug(): string {
        return 'static-deploy-addon-s3';
    }

    public static function getDeployerData(): array {
        return [
            'description' => 'Deploys to Amazon S3',
            'name' => 'S3 Deployment',
            'url' => 'https://github.com/staticweb-io/static-deploy',
        ];
    }

    /**
     * @param \Iterator<PathInfo> $path_infos
     */
    public function uploadFilesIter( \Iterator $path_infos ): void {
        $object_acl = S3Options::getValue( 'objectAcl' );
        $base_put_data = [
            'Bucket' => S3Options::getValue( 'bucketName' ),
            'ACL'    => $object_acl === '' ? 'public-read' : $object_acl,
        ];

        $cache_control = S3Options::getValue( 'headerCacheControl' );
        if ( $cache_control !== '' ) {
            $base_put_data['CacheControl'] = $cache_control;
        }

        $s3_remote_path = S3Options::getValue( 'bucketPrefix' );
        $s3_prefix = $s3_remote_path !== '' ? $s3_remote_path . '/' : '';

        $items_by_iter_key = [];

        $command_generator = function (
            $iterator,
        ) use (
            &$items_by_iter_key,
            $base_put_data,
            $s3_prefix,
        ) {
            $iter_key = 0;
            $last_log_time = microtime( true );

            foreach ( $iterator as $path_info ) {
                $now = microtime( true );
                $total = $this->deployed_ct + $this->deploy_cache_ct + $this->deploy_error_ct;
                if ( $total > 0 && $now - $last_log_time >= 60 ) {
                    WsLog::l( 'Deployed ' . $path_info->path );
                    $notice = "Deploy progress: $this->deployed_ct deployed," .
                        " $this->deploy_error_ct failed," .
                        " $this->deploy_cache_ct skipped (cached).";
                    WsLog::l( $notice );
                    $last_log_time = microtime( true );
                }

                $body = $path_info->body;
                $cache_key = $path_info->path;
                $content_type = $path_info->content_type;
                $filename = $path_info->filename;
                $redirect_to = $path_info->redirect_to;
                $status = $path_info->status;

                if ( ! $body && $filename ) {
                    $real_filepath = realpath( $filename );

                    if ( ! $real_filepath ) {
                        $err = 'Trying to deploy unknown file to S3: ' . $filename;
                        WsLog::l( $err );
                        continue;
                    }

                    // Standardise all paths to use / (Windows support)
                    $filename = str_replace( '\\', '/', $filename );

                    if ( ! is_string( $filename ) ) {
                        continue;
                    }
                }

                if ( ! $content_type && $filename ) {
                    $content_type = MimeTypes::guessMimeType( $filename );
                    if ( str_starts_with( $content_type, 'text/' ) ) {
                        $content_type = $content_type . '; charset=UTF-8';
                    }
                }

                $s3_key = $s3_prefix . ltrim( $cache_key, '/' );
                if ( mb_substr( $cache_key, -1 ) === '/' ) {
                    $s3_key = $s3_key . 'index.html';
                }

                if ( $status === 404 ) {
                    $cmd_name = 'DeleteObject';
                    $cmd_data = [
                        'Bucket' => $base_put_data['Bucket'],
                        'Key' => $s3_key,
                    ];
                    $hash = md5( $cmd_name . json_encode( $cmd_data ) );
                } else {
                    $cmd_name = 'PutObject';
                    $cmd_data = array_merge( [], $base_put_data );

                    if ( $redirect_to ) {
                        $cmd_data['WebsiteRedirectLocation'] = $redirect_to;
                    } elseif ( ! $path_info->getContentHash() ) {
                        WsLog::l( 'Failed to hash file ' . $filename );
                        continue;
                    } else {
                        $file_hash = hex2bin( (string) $path_info->getContentHash() );
                        $cmd_data['ContentMD5'] = base64_encode( $file_hash );
                        $cmd_data['ContentType'] = $content_type;
                    }

                    $cmd_data['Key'] = $s3_key;
                    $hash = md5( $cmd_name . json_encode( $cmd_data ) );

                    if ( $body !== null ) {
                        $cmd_data['Body'] = $body;
                    } elseif ( $filename ) {
                        $cmd_data['SourceFile'] = $filename;
                    }

                    if ( empty( $cmd_data['Key'] ) ) {
                        unset( $cmd_data['Body'] );
                        $msg = 'Invalid deploy data for path "' . $path_info->path .
                            '": ' . json_encode( $cmd_data );
                        if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' )
                        && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                            throw WsLog::ex( esc_html( $msg ) );
                        }
                        throw WsLog::ex( $msg );
                    }

                    if ( ! isset( $cmd_data['Body'] )
                    && ! isset( $cmd_data['SourceFile'] )
                    && ! isset( $cmd_data['WebsiteRedirectLocation'] )
                    ) {
                        $msg = 'Invalid deploy data: ' . json_encode( $cmd_data );
                        if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' )
                        && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                            throw WsLog::ex( esc_html( $msg ) );
                        }
                        throw WsLog::ex( $msg );
                    }
                }

                if ( isset( $path_info->deploy_cache )
                && ( $path_info->deploy_cache[ self::DEFAULT_NAMESPACE ] ?? null ) === $hash ) {
                    ++$this->deploy_cache_ct;
                    if ( STATIC_DEPLOY_DEBUG ) {
                        WsLog::d( 'Skipping deploy of cached file ' . $cache_key );
                    }
                    continue;
                }

                // Save data so we can retrieve it by iter_key
                // in the fulfilled handler
                $items_by_iter_key[ $iter_key ] = [
                    'cache_key' => $cache_key,
                    'hash' => $hash,
                ];
                ++$iter_key;

                if ( STATIC_DEPLOY_DEBUG ) {
                    $d = $cmd_data;
                    if ( isset( $d['Body'] ) ) {
                        $d['Body'] = '...' . strlen( (string) $d['Body'] ) . ' bytes...';
                    }
                    $pi = $path_info->withBody( '' );
                    WsLog::d(
                        "Command $cmd_name: " . json_encode( $d )
                        . ' for : ' . json_encode( $pi )
                    );
                }

                yield $this->s3_client->getCommand( $cmd_name, array_merge( [], $cmd_data ) );
            }
        };

        $commands = $command_generator( $path_infos );

        $concurrency = intval( S3Options::getValue( 'concurrency' ) || '4' );
        $config = [
            'concurrency' => $concurrency,
        ];

        $cmd_pool = new CommandPool(
            $this->s3_client,
            $commands,
            [
                // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
                'fulfilled' =>
                function ( $result, $iter_key, $promise ) use ( &$items_by_iter_key ): void {
                    $item = $items_by_iter_key[ $iter_key ];
                    DeployCache::addFile(
                        $item['cache_key'],
                        $item['hash'],
                        self::DEFAULT_NAMESPACE,
                    );
                    $this->addCfPath( $item['cache_key'] );
                    unset( $items_by_iter_key[ $iter_key ] );
                    if ( STATIC_DEPLOY_DEBUG ) {
                        WsLog::d( 'Deployed ' . $item['cache_key'] );
                    }
                    $this->deployed_ct++;
                },
                // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
                'rejected' =>
                function ( $reason, $iter_key, $promise ) use ( &$items_by_iter_key ): void {
                    $item = $items_by_iter_key[ $iter_key ];
                    WsLog::e( 'Error uploading file ' . $item['cache_key'] . ': ' . $reason );
                    unset( $items_by_iter_key[ $iter_key ] );
                    $this->deploy_error_ct++;
                },
            ],
            $config
        );

        $cmd_pool->promise()->wait();

        $notice = "Deployed $this->deployed_ct files," .
            " $this->deploy_error_ct failed," .
            " $this->deploy_cache_ct skipped (cached).";
        WsLog::l( $notice );

        $distribution_id = S3Options::getValue( 'distributionId' );
        $num_stale = count( $this->cf_stale_paths );
        if ( $distribution_id && $num_stale > 0 ) {
            if ( $num_stale > $this->cf_max_paths ) {
                WsLog::l( 'Invalidating all CloudFront paths' );
                self::invalidateItems( $distribution_id, [ '/*' ] );
            } else {
                $path_text = ( $num_stale === 1 ) ? 'path' : 'paths';
                WsLog::l( "Invalidating $num_stale CloudFront $path_text" );
                self::invalidateItems( $distribution_id, $this->cf_stale_paths );
            }
        }
    }

    public static function awsClientOpts(): array {
        $opts = [
            'region' => S3Options::getValue( 'awsRegion' ),
        ];

        $endpoint = S3Options::getValue( 'awsEndpoint' );
        if ( $endpoint !== '' ) {
            $opts['endpoint'] = $endpoint;

            // Work-around for localstack.
            // Docs suggest to use s3.localhost.localstack.cloud,
            // but the DNS lookups fail in test.
            if ( str_starts_with( $endpoint, 'http://localhost:4668' ) ) {
                $opts['use_path_style_endpoint'] = true;
            }
        }

        /*
         * If no credentials option, SDK attempts to load credentials from
         * your environment in the following order:
         *
         * - environment variables.
         * - a credentials .ini file.
         * - an IAM role.
         */
        if (
            S3Options::getValue( 'awsAccessKeyId' ) &&
            S3Options::getValue( 'awsSecretAccessKey' )
        ) {
            $opts['credentials'] = [
                'key' => S3Options::getValue( 'awsAccessKeyId' ),
                'secret' => Options::encrypt_decrypt(
                    'decrypt',
                    S3Options::getValue( 'awsSecretAccessKey' )
                ),
            ];
        } else {
            $profile = S3Options::getValue( 'awsProfile' );
            if ( $profile !== '' ) {
                $opts['profile'] = $profile;
            }
        }

        return $opts;
    }

    public static function s3Client(): \Aws\S3\S3Client {
        return new \Aws\S3\S3Client( self::awsClientOpts() );
    }

    public static function cloudfrontClient(): \Aws\CloudFront\CloudFrontClient {
        return new \Aws\CloudFront\CloudFrontClient( self::awsClientOpts() );
    }

    public function addCfPath( string $path ): void {
        if ( $this->cf_max_paths >= count( $this->cf_stale_paths ) ) {
            if ( str_ends_with( $path, '/index.html' ) ) {
                $path = substr( $path, 0, -10 );
            }
            $path = str_replace( ' ', '%20', $path );
            $this->cf_stale_paths[] = $path;
        }
    }

    /**
     * Create invalidation in CloudFront
     *
     * @param mixed[] $items mixed array
     */
    public static function createInvalidation( string $distribution_id, array $items ): string {
        $client = self::cloudfrontClient();

        return $client->createInvalidation(
            [
                'DistributionId' => $distribution_id,
                'InvalidationBatch' => [
                    'CallerReference' => 'Static Deploy S3 Add-on ' . time(),
                    'Paths' => [
                        'Items' => $items,
                        'Quantity' => count( $items ),
                    ],
                ],
            ]
        );
    }

    /**
     * Invalidate paths in CloudFront, catching and logging exceptions.
     *
     * @param mixed[] $items mixed array
     */
    public static function invalidateItems( string $distribution_id, array $items ): ?string {
        try {
            return self::createInvalidation( $distribution_id, $items );
        } catch ( AwsException $e ) {
            WsLog::l( 'Error creating CloudFront invalidation: ' . $e->getMessage() );
            return null;
        }
    }
}
