<?php

namespace WP2Static\S3;

use WP2Static\Options;
use WP2Static\OptionSpec;

/*
 * Options for the S3 deployer
*/
class S3Options {

    /**
     * @var array<string, OptionSpec>
     */
    private static $cached_option_specs;

    public static function getName( string $slug ): string {
        return 's3_' . $slug;
    }

    /**
     * @return array<string, OptionSpec>
     */
    public static function optionSpecs(): array {
        if ( isset( self::$cached_option_specs ) ) {
            return self::$cached_option_specs;
        }

        $specs = [
            // AWS credentials
            new OptionSpec(
                'string',
                self::getName( 'awsAccessKeyId' ),
                '',
                'AWS Access Key ID',
                'Access Key ID'
            ),
            new OptionSpec(
                'string',
                self::getName( 'awsProfile' ),
                '',
                'AWS Profile',
                'Specifies which profile to use when credentials are ' .
                'created from the AWS credentials file in your HOME directory.',
            ),
            new OptionSpec(
                'string',
                self::getName( 'awsRegion' ),
                'us-east-1',
                'AWS Region',
                'Region'
            ),
            new OptionSpec(
                'string',
                self::getName( 'awsSecretAccessKey' ),
                '',
                'AWS Secret Access Key',
                'Secret Access Key'
            ),

            // S3 settings
            new OptionSpec(
                'string',
                self::getName( 'bucketName' ),
                '',
                'S3 Bucket',
                'Bucket name'
            ),
            new OptionSpec(
                'string',
                self::getName( 'bucketPrefix' ),
                '',
                'Path prefix in bucket',
                'If set, uploads files to this path within the bucket.'
            ),
            new OptionSpec(
                'integer',
                self::getName( 'concurrency' ),
                '4',
                'Maximum number of files that will be uploaded at the same time',
                ''
            ),
            new OptionSpec(
                'string',
                self::getName( 'headerCacheControl' ),
                'public, max-age=900',
                'Cache-Control header value',
                '',
                ''
            ),
            new OptionSpec(
                'string',
                self::getName( 'objectAcl' ),
                'public-read',
                'Object ACL',
                '',
                ''
            ),

            // CloudFront settings
            new OptionSpec(
                'string',
                self::getName( 'distributionId' ),
                '',
                'CloudFront Distribution ID',
                'If using CloudFront, set this to invalidate cache after deploying files.'
            ),
            new OptionSpec(
                'integer',
                self::getName( 'maxPathsToInvalidate' ),
                '100',
                'Max CloudFront paths to invalidate',
                'Maximum number of paths to invalidate before triggering a full invalidation.',
            ),
        ];

        $ret = [];
        foreach ( $specs as $s ) {
            $ret[ $s->name ] = $s;
        }
        self::$cached_option_specs = $ret;
        return $ret;
    }

    public static function getValue( string $slug ): string {
        $name = self::getName( $slug );
        $option_spec = self::optionSpecs()[ $name ];

        if ( ! $option_spec ) {
            throw WsLog::ex( "Unknown option: $name" );
        }

        return Options::getSpecValue( $option_spec );
    }
}
