<?php declare(strict_types=1);

namespace StaticDeploy\CLI;

use StaticDeploy\Controller;
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

        // Deprecated aliases
        Subcommand::registerHidden(
            'process-queue',
            [ self::class, '__deprecatedProcessQueue' ],
        );
        Subcommand::registerHidden(
            'process_queue',
            [ self::class, '__deprecatedProcessQueue' ],
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

    /**
     * Process any jobs waiting in the queue.
     *
     * @deprecated
     */
    public function __deprecatedProcessQueue(): void {
        WsLog::w( 'The "process-queue" command is deprecated. Use "jobs process" instead.' );
        // Ignoring all arguments for backwards-compatibility.
        $this->process( [], [] );
    }

    /**
     * Process any jobs waiting in the queue.
     */
    public function process( array $args, array $assoc_args ): void {
        $cfg = Args::parse( $args, $assoc_args );

        $job_count = JobQueue::getWaitingJobsCount();

        if ( $job_count === 0 ) {
            WP_CLI::success( 'No jobs in queue' );
        } else {
            WP_CLI::log( ' Processing ' . $job_count . ' job' . ( $job_count > 1 ? 's' : '' ) );

            Controller::processQueue();

            WP_CLI::success( 'Done processing queue' );
        }
    }
}
