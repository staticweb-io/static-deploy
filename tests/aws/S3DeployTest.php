<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

final class S3DeployTest extends TestCase {

    use ITTrait;

    public function setUpBucket(): void {
        // Create the S3 bucket if it doesn't exist
        $s3 = new \Aws\S3\S3Client(
            [
                'region' => 'us-east-1',
                'endpoint' => 'http://localhost:4668',
                'credentials' => [
                    'key' => 'test',
                    'secret' => 'test',
                ],
                'use_path_style_endpoint' => true,
            ]
        );

        $bucket = 'static-deploy-bucket';
        if ( ! $s3->doesBucketExist( $bucket ) ) {
            $s3->createBucket( [ 'Bucket' => $bucket ] );
        }
    }

    public function setUpCommonOptions(): void {
        $this->setOptionValue( 's3_bucketName', 'static-deploy-bucket' );
        $this->setOptionValue( 's3_awsAccessKeyId', 'test' );
        $this->setOptionValue( 's3_awsSecretAccessKey', 'test' );
        $this->setOptionValue( 's3_awsEndpoint', 'http://localhost:4668' );
        $this->setOptionValue( 's3_awsRegion', 'us-east-1' );
    }

    public function testDeploy(): void {
        $this->setUpBucket();
        $this->setUpCommonOptions();
        $this->pluginCli( [ 'addons', 'enable', 'static-deploy-addon-s3' ] );
        $this->pluginCli( [ 'full-workflow' ] );
    }

    public function testDirectDeploy(): void {
        $this->setUpBucket();
        $this->setUpCommonOptions();
        $this->pluginCli( [ 'addons', 'enable', 'static-deploy-addon-s3' ] );
        $this->pluginCli( [ 'direct-deploy' ] );
    }
}
