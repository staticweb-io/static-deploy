<?php declare(strict_types=1);

namespace StaticDeploy\CLI;

use StaticDeploy\JobQueue;
use StaticDeploy\WsLog;
use WP_CLI;

/**
 * Manage the job queue
 */
class Jobs {
    public static function registerCommands(): void {
        Subcommand::register(
            'jobs',
            self::class,
        );
    }

    /**
     * Add a job to the queue
     *
     * ## OPTIONS
     *
     * <job-type>
     * : Type of job to add
     * ---
     * options:
     *  - detect
     *  - crawl
     *  - post_process
     *  - deploy
     *  - direct_deploy
     * ---
     */
    public function add( array $args, array $assoc_args ): void {
        $cfg = Args::parse(
            $args,
            $assoc_args,
            [ 'job-type' => [] ],
        );

        $id = JobQueue::addJob( $cfg['job-type'] );
        WP_CLI::success( 'Added job ' . $id );
    }
}
