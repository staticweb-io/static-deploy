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

    /**
     * If PHP dies during a job, such as by hitting a time limit, the
     * row stays in 'processing'. That must not block the queue forever.
     */
    public function testKilledDirectDeployPostJobDoesNotBlockQueue(): void {
        $this->createPost( 'after-killed-job' );
        $this->wpCli(
            [
                'eval',
                '$jobs = ' . \StaticDeploy\JobQueue::class . '::getProcessableJobs();
                 ' . \StaticDeploy\JobQueue::class . '::setStatus( (int) $jobs[0]->id, "processing" );',
            ]
        );

        $this->pluginCli( [ 'jobs', 'process' ] );

        $this->assertDeployed( 'after-killed-job' );
    }

    /**
     * A concurrent process declines to start while a job is running,
     * so the running process has to pick up what was queued meanwhile.
     */
    public function testPageSavedDuringProcessingIsDeployed(): void {
        $this->installStringPlugin(
            'save-during-deploy',
            '<?php
            /* Plugin Name: save-during-deploy */
            add_action( "static_deploy_post_direct_deploy_trigger", function () {
                // Save during the last of the two queued jobs, so that
                // nothing already picked up can deploy the page.
                static $calls = 0;
                if ( ++$calls === 2 && ! get_option( "sd_saved_during_deploy" ) ) {
                    update_option( "sd_saved_during_deploy", "1" );
                    wp_insert_post( [
                        "post_content" => "content-of-saved-during-deploy",
                        "post_name" => "saved-during-deploy",
                        "post_status" => "publish",
                        "post_title" => "saved-during-deploy",
                        "post_type" => "page",
                    ] );
                }
            } );'
        );
        $this->createPost( 'before-deploy' );

        $this->pluginCli( [ 'jobs', 'process' ] );

        $saved_id = $this->wpCli(
            [ 'post', 'list', '--post_type=page', '--name=saved-during-deploy', '--field=ID' ]
        )['final_line'];
        $this->post_ids[] = $saved_id;
        $this->wpCli( [ 'option', 'delete', 'sd_saved_during_deploy' ] );
        $this->assertDeployed( 'saved-during-deploy' );
    }
}
