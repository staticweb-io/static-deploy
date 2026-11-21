<?php declare(strict_types=1);

namespace StaticDeploy;

use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

/**
 * Deleting objects from the bucket that are not part of the
 * deployed site.
 */
final class S3SyncTest extends TestCase {
    use ITTrait {
        setUp as traitSetUp;
    }

    private const BUCKET = 'static-deploy-sync';

    private S3Client $s3;

    protected function setUp(): void {
        $this->traitSetUp();

        $this->s3 = new S3Client(
            [
                'credentials' => [
                    'key' => 'test',
                    'secret' => 'test',
                ],
                'endpoint' => 'http://localhost:4668',
                'region' => 'us-east-1',
                'use_path_style_endpoint' => true,
            ]
        );
        if ( ! $this->s3->doesBucketExist( self::BUCKET ) ) {
            $this->s3->createBucket( [ 'Bucket' => self::BUCKET ] );
        }
        foreach ( $this->listKeys() as $key ) {
            $this->s3->deleteObject(
                [
                    'Bucket' => self::BUCKET,
                    'Key' => $key,
                ]
            );
        }

        $this->setOptionValue( 's3_awsAccessKeyId', 'test' );
        $this->setOptionValue( 's3_awsEndpoint', 'http://localhost:4668' );
        $this->setOptionValue( 's3_awsRegion', 'us-east-1' );
        $this->setOptionValue( 's3_awsSecretAccessKey', 'test' );
        $this->setOptionValue( 's3_bucketName', self::BUCKET );
        $this->setOptionValue( 's3_deleteStaleObjectsCooldown', '0' );
        $this->pluginCli( [ 'addons', 'enable', 'static-deploy-addon-s3' ] );
        $this->wpCli(
            [ 'eval', "delete_option( 'static_deploy_s3_deleteStaleObjectsLastRun' );" ]
        );
    }

    protected function tearDown(): void {
        $this->setOptionValue( 's3_bucketPrefix', '' );
        $this->setOptionValue( 's3_deleteStaleObjects', 'off' );
        $this->setOptionValue( 's3_deleteStaleObjectsCooldown', '24' );
    }

    /**
     * @return string[]
     */
    private function listKeys(): array {
        $keys = [];
        $pages = $this->s3->getPaginator(
            'ListObjectsV2',
            [ 'Bucket' => self::BUCKET ]
        );
        foreach ( $pages as $page ) {
            foreach ( $page['Contents'] ?? [] as $object ) {
                $keys[] = $object['Key'];
            }
        }
        sort( $keys );
        return $keys;
    }

    private function putObject( string $key ): void {
        $this->s3->putObject(
            [
                'Body' => 'stray',
                'Bucket' => self::BUCKET,
                'Key' => $key,
            ]
        );
    }

    /**
     * @param string[] $expected
     */
    private function assertKeysPresent( array $expected ): void {
        $keys = $this->listKeys();
        foreach ( $expected as $key ) {
            $this->assertContains( $key, $keys );
        }
    }

    /**
     * @param string[] $expected
     */
    private function assertKeysAbsent( array $expected ): void {
        $keys = $this->listKeys();
        foreach ( $expected as $key ) {
            $this->assertNotContains( $key, $keys );
        }
    }

    public function testStaleObjectsAreKeptByDefault(): void {
        $this->putObject( 'stray.html' );

        $this->pluginCli( [ 'full-workflow' ] );

        $this->assertKeysPresent( [ 'index.html', 'stray.html' ] );
    }

    public function testDeleteAllDeletesStaleObjects(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        $this->putObject( 'old/backup.sql' );
        $this->putObject( 'stray.html' );

        $this->pluginCli( [ 'full-workflow' ] );

        $this->assertKeysAbsent( [ 'old/backup.sql', 'stray.html' ] );
        $this->assertKeysPresent( [ 'index.html', 'robots.txt' ] );
    }

    /**
     * Like rsync --delete without --delete-excluded
     */
    public function testExceptIgnoredKeepsIgnoredObjects(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'except-ignored' );
        $this->putObject( 'old/backup.sql' );
        $this->putObject( 'stray.html' );

        $this->pluginCli( [ 'full-workflow' ] );

        $this->assertKeysAbsent( [ 'stray.html' ] );
        $this->assertKeysPresent( [ 'index.html', 'old/backup.sql' ] );
    }

    public function testObjectsOutsidePrefixAreKept(): void {
        $this->setOptionValue( 's3_bucketPrefix', 'site' );
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        $this->putObject( 'outside.html' );
        $this->putObject( 'site-other/stray.html' );
        $this->putObject( 'site/stray.html' );

        $this->pluginCli( [ 'full-workflow' ] );

        $this->assertKeysAbsent( [ 'site/stray.html' ] );
        $this->assertKeysPresent(
            [ 'outside.html', 'site-other/stray.html', 'site/index.html' ]
        );
    }

    public function testStaleObjectsAreKeptDuringCooldown(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        $this->setOptionValue( 's3_deleteStaleObjectsCooldown', '24' );
        $this->putObject( 'stray.html' );
        $this->pluginCli( [ 'full-workflow' ] );
        $this->assertKeysAbsent( [ 'stray.html' ] );
        $this->putObject( 'stray.html' );

        $this->pluginCli( [ 'full-workflow' ] );

        $this->assertKeysPresent( [ 'stray.html' ] );
    }

    /**
     * The cooldown applies to the bucket and prefix that were
     * cleaned up, not to a new deployment target.
     */
    public function testCooldownIsResetByChangingPrefix(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        $this->setOptionValue( 's3_deleteStaleObjectsCooldown', '24' );
        $this->pluginCli( [ 'full-workflow' ] );
        $this->setOptionValue( 's3_bucketPrefix', 'site' );
        $this->putObject( 'site/stray.html' );

        $this->pluginCli( [ 'full-workflow' ] );

        $this->assertKeysAbsent( [ 'site/stray.html' ] );
    }

    /**
     * A deploy that has nothing to upload must not empty the bucket.
     */
    public function testNothingIsDeletedWithoutProcessedSite(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        $this->pluginCli( [ 'full-workflow' ] );
        $this->putObject( 'stray.html' );
        $this->pluginCli( [ 'processed-site', 'delete', '--force' ] );

        $this->pluginCli( [ 'deploy' ] );

        $this->assertKeysPresent( [ 'index.html', 'stray.html' ] );
    }

    public function testDirectDeployDeletesStaleObjects(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        $this->putObject( 'stray.html' );

        $this->pluginCli( [ 'direct-deploy' ] );

        $this->assertKeysAbsent( [ 'stray.html' ] );
        $this->assertKeysPresent( [ 'index.html', 'robots.txt' ] );
    }

    /**
     * A shard only deploys some of the site's paths.
     */
    public function testShardedDirectDeployDeletesNothing(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        $this->pluginCli( [ 'direct-deploy' ] );
        $this->putObject( 'stray.html' );

        $this->pluginCli( [ 'direct-deploy', '--path-hash-prefix=0' ] );

        $this->assertKeysPresent( [ 'index.html', 'robots.txt', 'stray.html' ] );
    }

    public function testDirectDeployPostDeletesNothing(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        $this->putObject( 'stray.html' );

        $this->pluginCli( [ 'direct-deploy', '1' ] );

        $this->assertKeysPresent( [ 'hello-world/index.html', 'stray.html' ] );
    }

    /**
     * A path that matched an ignore pattern after it was deployed
     * is no longer part of the site.
     */
    public function testDirectDeployDeletesNewlyIgnoredObject(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        file_put_contents( ITEnv::getTestContentDir() . '/sync.txt', 'sync' );
        $key = ltrim( ITEnv::getTestContentPath(), '/' ) . '/sync.txt';
        $this->pluginCli( [ 'direct-deploy' ] );
        $this->assertKeysPresent( [ $key ] );

        $paths_to_ignore = implode(
            "\n",
            $this->pluginCli( [ 'options', 'get', 'pathsToIgnore' ] )['output']
        );
        try {
            $this->setOptionValue( 'pathsToIgnore', $paths_to_ignore . "\nsync.txt" );
            $this->pluginCli( [ 'direct-deploy' ] );
        } finally {
            $this->setOptionValue( 'pathsToIgnore', $paths_to_ignore );
        }

        $this->assertKeysAbsent( [ $key ] );
    }

    /**
     * A deleted object's deploy cache entry must be removed too,
     * or the file would be skipped as already deployed if it
     * became part of the site again.
     */
    public function testDeletedObjectIsRedeployed(): void {
        $this->setOptionValue( 's3_deleteStaleObjects', 'all' );
        file_put_contents( ITEnv::getTestContentDir() . '/sync.txt', 'sync' );
        $key = ltrim( ITEnv::getTestContentPath(), '/' ) . '/sync.txt';
        $this->pluginCli( [ 'full-workflow' ] );
        $this->assertKeysPresent( [ $key ] );

        $paths_to_ignore = implode(
            "\n",
            $this->pluginCli( [ 'options', 'get', 'pathsToIgnore' ] )['output']
        );
        try {
            $this->setOptionValue( 'pathsToIgnore', $paths_to_ignore . "\nsync.txt" );
            $this->pluginCli( [ 'full-workflow' ] );
        } finally {
            $this->setOptionValue( 'pathsToIgnore', $paths_to_ignore );
        }
        $this->assertKeysAbsent( [ $key ] );

        $this->pluginCli( [ 'full-workflow' ] );
        $this->assertKeysPresent( [ $key ] );
    }
}
