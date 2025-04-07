<?php

namespace WP2StaticS3;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Aws\CloudFront\CloudFrontClient;
use Aws\CommandPool;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use WP2Static\WsLog;

class Deployer {

    const DEFAULT_NAMESPACE = 'wp2static-addon-s3/default';

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
    private $chunk_size = 50;

    /**
     * @var array
     */
    private $chunk = [];

    /**
     * @var string
     */
    private $namespace = self::DEFAULT_NAMESPACE;

    /**
     * @var S3Client
     */
    private $s3_client;

    public function __construct() {
        $cf_max_paths_str = Controller::getValue( 'cfMaxPathsToInvalidate' );
        if ( $cf_max_paths_str ) {
            $this->cf_max_paths = intval( $cf_max_paths_str );
        }

        $this->s3_client = self::s3Client();
    }

    public function addToChunk(
        string $cache_key,
        string $hash,
        array $put_data
    ): void {
        array_push(
            $this->chunk,
            [
                'cache_key' => $cache_key,
                'hash' => $hash,
                'put_data' => $put_data
            ]
        );
    }

    public function deployChunk(): void {
        if ( empty( $this->chunk ) ) {
            return;
        }

        $already_cached = 0;

        $commands = [];

        foreach ( $this->chunk as $obj ) {
            $cache_key = $obj['cache_key'];
            $hash = $obj['hash'];
            $put_data = $obj['put_data'];

            $is_cached = \WP2Static\DeployCache::fileisCached(
                $cache_key,
                $this->namespace,
                $hash,
            );

            if ( $is_cached ) {
                $already_cached++;
                continue;
            }

            array_push(
                $commands,
                $this->s3_client->getCommand( 'PutObject', $put_data )
            );
        }

        if ( ! empty( $commands ) ) {
            $cmd_pool = new CommandPool(
                $this->s3_client,
                $commands,
                [
                    'fulfilled' => function ( $reason, $iterKey, $promise) {
                        $item = $this->chunk[$iterKey];
                        \WP2Static\DeployCache::addFile( $item['cache_key'], $this->namespace, $item['hash'] );
                        $this->addCfPath( $item['cache_key'] );
                    },
                    'rejected' => function ( $reason, $iterKey, $promise) {
                        WsLog::l( 'Error uploading file ' . $this->chunk[$iterKey]['cache_key'] . ': ' . $reason );
                    }
                ]
            );

            $cmd_pool->promise()->wait();
        }

        $uncached = count( $this->chunk ) - $already_cached;
        WsLog::l('Deployed chunk: ' . $uncached . ' uploaded, ' . $already_cached . ' cached from previous deploy.');

        $this->chunk = [];
    }

    public function uploadFiles( string $processed_site_path ) : void {
        // check if dir exists
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $file_arrays = function ( $iterator) use ( $processed_site_path ) {
            foreach ( $iterator as $filename => $file_object ) {
                $base_name = basename( $filename );
                if ( $base_name != '.' && $base_name != '..' ) {
                    yield [
                        'filename' => $filename,
                        'path' => str_replace( $processed_site_path, '', $filename ),
                    ];
                }
            }
        };

        self::uploadFilesIter( $file_arrays( $iterator ) );
    }

    public function uploadFilesIter( \Iterator $files ) : void {
        $object_acl = Controller::getValue( 's3ObjectACL' );
        $put_data = [
            'Bucket' => Controller::getValue( 's3Bucket' ),
            'ACL'    => $object_acl === '' ? 'public-read' : $object_acl,
        ];

        $cache_control = Controller::getValue( 's3CacheControl' );
        if ( $cache_control ) {
            $put_data['CacheControl'] = $cache_control;
        }

        $base_put_data = $put_data;

        $s3_remote_path = Controller::getValue( 's3RemotePath' );
        $s3_prefix = $s3_remote_path ? $s3_remote_path . '/' : '';

        $items_by_iterKey = [];

        $command_generator = function (
            $iterator
        ) use (
            &$items_by_iterKey,
            $put_data,
            $s3_prefix
        ) {
            $iterKey = 0;

            foreach ( $iterator as $file ) {
                $cache_key = $file['path'];
                $filename = $file['filename'];
                $real_filepath = realpath( $filename );

                if ( ! $real_filepath ) {
                    $err = 'Trying to deploy unknown file to S3: ' . $filename;
                    \WP2Static\WsLog::l( $err );
                    continue;
                }

                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                if ( ! is_string( $filename ) ) {
                    continue;
                }

                $s3_key = $s3_prefix . ltrim( $cache_key, '/' );

                $mime_type = MimeTypes::guessMimeType( $filename );
                if ( 'text/' === substr( $mime_type, 0, 5 ) ) {
                    $mime_type = $mime_type . '; charset=UTF-8';
                }

                $file_hash = md5_file( $filename, true);
                if ( !$file_hash ) {
                    WsLog::l( 'Failed to hash file ' . $filename );
                    continue;
                }

                $put_data['ContentMD5'] = base64_encode( $file_hash );
                $put_data['ContentType'] = $mime_type;
                $put_data['Key'] = $s3_key;
                $hash = md5( (string) json_encode( $put_data ) );
                $put_data['SourceFile'] = $filename;

                $is_cached = \WP2Static\DeployCache::fileisCached(
                    $cache_key,
                    $this->namespace,
                    $hash,
                );
                
                if ( $is_cached ) {
                    continue;
                }

                // Save data so we can retrieve it by iterKey
                // in the fulfilled handler
                $items_by_iterKey[$iterKey] = [
                    'cache_key' => $cache_key,
                    'hash' => $hash
                ];
                $iterKey++;

                yield $this->s3_client->getCommand('PutObject', $put_data);
            }
        };

        $commands = $command_generator( $files );

        $cmd_pool = new CommandPool(
            $this->s3_client,
            $commands,
            [
                'fulfilled' => function ($result, $iterKey, $promise)
                    use (&$items_by_iterKey) {
                    $item = $items_by_iterKey[$iterKey];
                    \WP2Static\DeployCache::addFile( $item['cache_key'], $this->namespace, $item['hash'] );
                    $this->addCfPath( $item['cache_key'] );
                    unset($items_by_iterKey[$iterKey]);
                },
                'rejected' => function ( $reason, $iterKey, $promise)
                    use ($items_by_iterKey) {
                    $item = $items_by_iterKey[$iterKey];
                    WsLog::l( 'Error uploading file ' . $item['cache_key'] . ': ' . $reason );
                    unset($items_by_iterKey[$iterKey]);
                }
            ]
        );

        $cmd_pool->promise()->wait();

        // Deploy 301 redirects.

        $put_data = $base_put_data;
        $redirects = apply_filters( 'wp2static_list_redirects', [] );

        foreach ( $redirects as $redirect ) {
            $cache_key = $redirect['url'];

            if ( mb_substr( $cache_key, -1 ) === '/' ) {
                $cache_key = $cache_key . 'index.html';
            }

            $s3_key = $s3_prefix . ltrim( $cache_key, '/' );

            $put_data['Key'] = $s3_key;
            $put_data['WebsiteRedirectLocation'] = $redirect['redirect_to'];
            $hash = md5( (string) json_encode( $put_data ) );

            $this->addToChunk( $cache_key, $hash, $put_data );

            if ( $this->chunk_size <= count( $this->chunk ) ) {
                $this->deployChunk();
            }
        }

        $this->deployChunk();

        $distribution_id = Controller::getValue( 'cfDistributionID' );
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

    public static function s3Client() : \Aws\S3\S3Client {
        $client_options = [
            'version' => 'latest',
            'region' => Controller::getValue( 's3Region' ),
        ];

        /*
           If no credentials option, SDK attempts to load credentials from
           your environment in the following order:

           - environment variables.
           - a credentials .ini file.
           - an IAM role.
         */
        if (
            Controller::getValue( 's3AccessKeyID' ) &&
            Controller::getValue( 's3SecretAccessKey' )
        ) {
            $client_options['credentials'] = [
                'key' => Controller::getValue( 's3AccessKeyID' ),
                'secret' => \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 's3SecretAccessKey' )
                ),
            ];
        } elseif ( Controller::getValue( 's3Profile' ) ) {
            $client_options['profile'] = Controller::getValue( 's3Profile' );
        }

        return new \Aws\S3\S3Client( $client_options );
    }

    public static function cloudfrontClient() : \Aws\CloudFront\CloudFrontClient {
        /*
            If no credentials option, SDK attempts to load credentials from
            your environment in the following order:
                 - environment variables.
                 - a credentials .ini file.
                 - an IAM role.
        */
        if (
            Controller::getValue( 'cfAccessKeyID' ) &&
            Controller::getValue( 'cfSecretAccessKey' )
        ) {
            // Use the supplied access keys.
            $credentials = new \Aws\Credentials\Credentials(
                Controller::getValue( 'cfAccessKeyID' ),
                \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 'cfSecretAccessKey' )
                )
            );
            $client = \Aws\CloudFront\CloudFrontClient::factory(
                [
                    'region' => Controller::getValue( 'cfRegion' ),
                    'version' => 'latest',
                    'credentials' => $credentials,
                ]
            );
        } elseif ( Controller::getValue( 'cfProfile' ) ) {
            // Use the specified profile.
            $client = \Aws\CloudFront\CloudFrontClient::factory(
                [
                    'profile' => Controller::getValue( 'cfProfile' ),
                    'region' => Controller::getValue( 'cfRegion' ),
                    'version' => 'latest',
                ]
            );
        } else {
            // Use the IAM role.
            $client = \Aws\CloudFront\CloudFrontClient::factory(
                [
                    'region' => Controller::getValue( 'cfRegion' ),
                    'version' => 'latest',
                ]
            );
        }

        return $client;
    }

    public function addCfPath(String $path) : void {
        if ( $this->cf_max_paths >= count( $this->cf_stale_paths ) ) {
            if ( 0 === substr_compare( $path, '/index.html', -11 ) ) {
                $path = substr( $path, 0, -10 );
            }
            $path = str_replace( ' ', '%20', $path );
            array_push( $this->cf_stale_paths, $path );
        }
    }

    /**
     * Create invalidation in CloudFront
     *
     * @param mixed[] $items mixed array
     */
    public static function createInvalidation( string $distribution_id, array $items ) : string {
        $client = self::cloudfrontClient();

        return $client->createInvalidation(
            [
                'DistributionId' => $distribution_id,
                'InvalidationBatch' => [
                    'CallerReference' => 'WP2Static S3 Add-on ' . time(),
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
    public static function invalidateItems( string $distribution_id, array $items ) : ?string {
        try {
            return self::createInvalidation( $distribution_id, $items );
        } catch ( AwsException $e ) {
            WsLog::l( 'Error creating CloudFront invalidation: ' . $e->getMessage() );
            return null;
        }
    }

}
