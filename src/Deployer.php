<?php

namespace WP2StaticS3;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Aws\S3\S3Client;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;
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

    public function __construct() {
        $cf_max_paths_str = Controller::getValue( 'cfMaxPathsToInvalidate' );
        if ( $cf_max_paths_str ) {
            $this->cf_max_paths = intval( $cf_max_paths_str );
        }
    }

    public function uploadFiles( string $processed_site_path ) : void {
        // check if dir exists
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

        $namespace = self::DEFAULT_NAMESPACE;

        // instantiate S3 client
        $s3 = self::s3Client();

        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

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

        foreach ( $iterator as $filename => $file_object ) {
            $base_name = basename( $filename );
            if ( $base_name != '.' && $base_name != '..' ) {
                $real_filepath = realpath( $filename );

                // TODO: do filepaths differ when running from WP-CLI (non-chroot)?

                $cache_key = str_replace( $processed_site_path, '', $filename );

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

                $put_data['Key'] = $s3_key;
                $put_data['ContentType'] = $mime_type;
                $put_data_hash = md5( (string) json_encode( $put_data ) );
                $put_data['SourceFile'] = $filename;
                $file_hash = md5_file( $filename );
                if ( !$file_hash ) {
                    WsLog::l( 'Failed to hash file ' . $filename );
                    continue;
                }
                $hash = md5( $put_data_hash . $file_hash );

                $is_cached = \WP2Static\DeployCache::fileisCached(
                    $cache_key,
                    $namespace,
                    $hash,
                );

                if ( $is_cached ) {
                    continue;
                }

                try {
                    $result = $s3->putObject( $put_data );

                    if ( $result['@metadata']['statusCode'] === 200 ) {
                        \WP2Static\DeployCache::addFile( $cache_key, $namespace, $hash );
                        $this->addCfPath( $cache_key );
                    }
                } catch ( AwsException $e ) {
                    WsLog::l( 'Error uploading file ' . $filename . ': ' . $e->getMessage() );
                }
            }
        }

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

            $is_cached = \WP2Static\DeployCache::fileisCached(
                $cache_key,
                $namespace,
                $hash,
            );

            if ( $is_cached ) {
                continue;
            }

            try {
                $result = $s3->putObject( $put_data );

                if ( $result['@metadata']['statusCode'] === 200 ) {
                    \WP2Static\DeployCache::addFile( $cache_key, $namespace, $hash );
                    $this->addCfPath( $cache_key );
                }
            } catch ( AwsException $e ) {
                WsLog::l(
                    'Error uploading redirect ' . $redirect['url'] . ': ' . $e->getMessage()
                );
            }
        }

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
