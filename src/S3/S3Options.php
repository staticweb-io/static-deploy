<?php

namespace StaticDeploy\S3;

use StaticDeploy\Options;
use StaticDeploy\OptionSpec;

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

        $wp2static_table = 'wp2static_addon_s3_options';

        $specs = [
            // AWS credentials
            new OptionSpec(
                'string',
                self::getName( 'awsAccessKeyId' ),
                '',
                'AWS Access Key ID',
                'Access Key ID',
                wp2static_name: 's3AccessKeyId',
                wp2static_table: $wp2static_table,
            ),
            new OptionSpec(
                'string',
                self::getName( 'awsProfile' ),
                '',
                'AWS Profile',
                'Specifies which profile to use when credentials are ' .
                'created from the AWS credentials file in your HOME directory.',
                wp2static_name: 's3Profile',
                wp2static_table: $wp2static_table,
            ),
            new OptionSpec(
                'string',
                self::getName( 'awsRegion' ),
                'us-east-1',
                'AWS Region',
                'Region',
                wp2static_name: 's3Region',
                wp2static_table: $wp2static_table,
            ),
            new OptionSpec(
                'string',
                self::getName( 'awsSecretAccessKey' ),
                '',
                'AWS Secret Access Key',
                'Secret Access Key',
                wp2static_name: 's3SecretAccessKey',
                wp2static_table: $wp2static_table,
            ),

            // S3 settings
            new OptionSpec(
                'string',
                self::getName( 'bucketName' ),
                '',
                'S3 Bucket',
                'Bucket name',
                wp2static_name: 's3Bucket',
                wp2static_table: $wp2static_table,
            ),
            new OptionSpec(
                'string',
                self::getName( 'bucketPrefix' ),
                '',
                'Path prefix in bucket',
                'If set, uploads files to this path within the bucket.',
                wp2static_name: 's3RemotePath',
                wp2static_table: $wp2static_table,
            ),
            new OptionSpec(
                'integer',
                self::getName( 'concurrency' ),
                '4',
                'Maximum number of files that will be uploaded at the same time',
                '',
                wp2static_name: 's3Concurrency',
                wp2static_table: $wp2static_table,
            ),
            new OptionSpec(
                'string',
                self::getName( 'headerCacheControl' ),
                'public, max-age=900',
                'Cache-Control header value',
                '',
                '',
                wp2static_name: 's3CacheControl',
                wp2static_table: $wp2static_table,
            ),
            new OptionSpec(
                'string',
                self::getName( 'objectAcl' ),
                'public-read',
                'Object ACL',
                '',
                '',
                wp2static_name: 's3ObjectACL',
                wp2static_table: $wp2static_table,
            ),

            // CloudFront settings
            new OptionSpec(
                'string',
                self::getName( 'distributionId' ),
                '',
                'CloudFront Distribution ID',
                'If using CloudFront, set this to invalidate cache after deploying files.',
                wp2static_name: 'cfDistributionID',
                wp2static_table: $wp2static_table,
            ),
            new OptionSpec(
                'integer',
                self::getName( 'maxPathsToInvalidate' ),
                '100',
                'Max CloudFront paths to invalidate',
                'Maximum number of paths to invalidate before triggering a full invalidation.',
                wp2static_name: 'cfMaxPathsToInvalidate',
                wp2static_table: $wp2static_table,
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
