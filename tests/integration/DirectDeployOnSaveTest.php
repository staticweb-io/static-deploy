<?php declare(strict_types=1);

namespace StaticDeploy;

use PHPUnit\Framework\TestCase;

/**
 * Saving a post with the Direct Deploy and Direct Deploy Post job
 * types enabled, and no others.
 */
final class DirectDeployOnSaveTest extends TestCase {
    use ITTrait {
        setUp as traitSetUp;
    }

    private const AUTO_JOB_OPTIONS = [
        'autoJobQueueCrawling' => [ '0', '1' ],
        'autoJobQueueDeployment' => [ '0', '1' ],
        'autoJobQueueDetection' => [ '0', '1' ],
        'autoJobQueueDirectDeploy' => [ '1', '0' ],
        'autoJobQueueDirectDeployPost' => [ '1', '1' ],
        'autoJobQueuePostProcessing' => [ '0', '1' ],
    ];

    /** @var string[] */
    private array $post_ids = [];

    protected function setUp(): void {
        $this->traitSetUp();
        $this->pluginCli( [ 'addons', 'enable', 'static-deploy-addon-local' ] );
        $this->pluginCli( [ 'options', 'set', 'local_dirPath', '../localdeploy' ] );
        foreach ( self::AUTO_JOB_OPTIONS as $name => [ $value ] ) {
            $this->setOptionValue( $name, $value );
        }
    }

    protected function tearDown(): void {
        foreach ( self::AUTO_JOB_OPTIONS as $name => [ , $default ] ) {
            $this->setOptionValue( $name, $default );
        }
        foreach ( $this->post_ids as $post_id ) {
            $this->wpCli( [ 'post', 'delete', $post_id, '--force' ] );
        }
        $this->pluginCli( [ 'jobs', 'delete', '--yes' ] );
    }

    private function createPost(
        string $slug,
        string $post_type = 'page',
        bool $cleanup = true,
    ): string {
        $post_id = $this->wpCli(
            [
                'post',
                'create',
                '--post_content=content-of-' . $slug,
                '--post_name=' . $slug,
                '--post_status=publish',
                '--post_title=' . $slug,
                '--post_type=' . $post_type,
                '--porcelain',
            ]
        )['final_line'];
        if ( $cleanup ) {
            $this->post_ids[] = $post_id;
        }
        return $post_id;
    }

    private function assertDeployed( string $slug ): void {
        $this->assertStringContainsString(
            'content-of-' . $slug,
            $this->getLocalDeployFileContents( $slug . '/index.html' ),
        );
    }


    /**
     * A post without a public URL has nothing to deploy.
     * It must not take down pages queued after it.
     */
    public function testPrivatePostTypeDoesNotBlockLaterPages(): void {
        $this->installStringPlugin(
            'private-post-type',
            '<?php
            /* Plugin Name: private-post-type */
            add_action( "init", function () {
                register_post_type( "sd_private", [ "public" => false, "rewrite" => false ] );
            } );'
        );
        $this->createPost( 'private-post', 'sd_private' );
        $this->createPost( 'after-private-post' );

        $this->pluginCli( [ 'jobs', 'process' ] );

        $this->assertDeployed( 'after-private-post' );
    }

    public function testTrashedPostDoesNotBlockLaterPages(): void {
        $trashed_id = $this->createPost( 'trashed-page' );
        $this->wpCli( [ 'post', 'delete', $trashed_id ] );
        $this->createPost( 'after-trashed-page' );

        $this->pluginCli( [ 'jobs', 'process' ] );

        $this->assertDeployed( 'after-trashed-page' );
    }

    public function testDeletedPostDoesNotBlockLaterPages(): void {
        $deleted_id = $this->createPost( 'deleted-page', cleanup: false );
        $this->wpCli( [ 'post', 'delete', $deleted_id, '--force' ] );
        $this->createPost( 'after-deleted-page' );

        $this->pluginCli( [ 'jobs', 'process' ] );

        $this->assertDeployed( 'after-deleted-page' );
    }
}
