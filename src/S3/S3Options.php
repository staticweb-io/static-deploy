<?php

namespace StaticDeploy\S3;

use StaticDeploy\Controller;
use StaticDeploy\OptionsControllerTrait;
use StaticDeploy\OptionSpec;

/*
 * Options for the S3 deployer
*/
class S3Options {

    use OptionsControllerTrait;

    /**
     * @var array<string, OptionSpec>
     */
    private static $cached_option_specs;

    public static function getAdminAction(): string {
        return Controller::getHookName( 's3_save_options' );
    }

    public static function getOptionsPageSlug(): string {
        return 'static-deploy-addon-s3';
    }

    /**
     * Returns namespaced option name from a slug
     *
     * @var string $slug
     */

    public static function getName( string $slug ): string {
        return 's3_' . $slug;
    }

    /**
     * @return array<string, OptionSpec>
     */
    public static function getSpecs(): array {
        if ( isset( self::$cached_option_specs ) ) {
            return self::$cached_option_specs;
        }

        $wp2static_table = 'wp2static_addon_s3_options';

        // Gathered from:
        // aws account list-regions --output json
        $aws_regions = [
            'af-south-1',
            'ap-east-1',
            'ap-northeast-1',
            'ap-northeast-2',
            'ap-northeast-3',
            'ap-south-1',
            'ap-south-2',
            'ap-southeast-1',
            'ap-southeast-2',
            'ap-southeast-3',
            'ap-southeast-4',
            'ap-southeast-5',
            'ap-southeast-7',
            'ca-central-1',
            'ca-west-1',
            'eu-central-1',
            'eu-central-2',
            'eu-north-1',
            'eu-south-1',
            'eu-south-2',
            'eu-west-1',
            'eu-west-2',
            'eu-west-3',
            'il-central-1',
            'me-central-1',
            'me-south-1',
            'mx-central-1',
            'sa-east-1',
            'us-east-1',
            'us-east-2',
            'us-west-1',
            'us-west-2',
        ];

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
                '',
                'AWS Region',
                'Region',
                allowed_values: array_merge( [ '' ], $aws_regions ),
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
                allowed_values: [ 'private', 'public-read' ],
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

    public static function getPageData(): array {
        return [
            'title' => 'S3 Deployment Options',
            'sections' => [
                [
                    'title' => 'AWS Credentials',
                    'options' => [
                        self::getName( 'awsAccessKeyId' ),
                        self::getName( 'awsSecretAccessKey' ),
                        self::getName( 'awsRegion' ),
                        self::getName( 'awsProfile' ),
                    ],
                ],
                [
                    'title' => 'S3',
                    'options' => [
                        self::getName( 'bucketName' ),
                        self::getName( 'bucketPrefix' ),
                        self::getName( 'headerCacheControl' ),
                        self::getName( 'objectAcl' ),
                        self::getName( 'concurrency' ),
                    ],
                ],
                [
                    'title' => 'CloudFront',
                    'options' => [
                        self::getName( 'distributionId' ),
                        self::getName( 'maxPathsToInvalidate' ),
                    ],
                ],
            ],
        ];
    }
}
