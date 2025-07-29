<?php

namespace StaticDeploy;

class JobQueue {

    public static function getTableName(): string {
        return Db::getTableName( 'jobs' );
    }

    public static function createTable(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            job_type VARCHAR(30) NOT NULL,
            status VARCHAR(30) NOT NULL,
            status_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            triggering_post_id BIGINT(20) UNSIGNED NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        Db::ensureIndex(
            $table_name,
            'status',
            "CREATE INDEX status ON $table_name (status)"
        );
    }

    /**
     * Add Job to queue
     *
     * @param string $job_type Type of job
     * ie detect, crawl, post_process, deploy
     * @return int ID of the row added
     */
    public static function addJob( string $job_type, ?int $post_id = null ): int {
        WsLog::l( 'Adding job: ' . $job_type );

        global $wpdb;

        $table_name = self::getTableName();

        $query_string = "INSERT INTO $table_name
        (job_type, status, triggering_post_id)
        VALUES (%s, 'waiting', %s);";
        $query = $wpdb->prepare( $query_string, $job_type, $post_id );

        Db::query( $query );
        return $wpdb->insert_id;
    }

    /**
     * Add Job to queue
     *
     * @param string $job_type Type of job
     * ie detect, crawl, post_process, deploy
     * @return int ID of the row added
     */
    public static function addCompletedJob(
        string $job_type,
        \DateTime $started_at,
        ?int $post_id = null
    ): int {
        global $wpdb;

        $table_name = self::getTableName();

        $query_string = "INSERT INTO $table_name
        (job_type,status,triggering_post_id,created_at,status_updated_at)
        VALUES (%s,'completed',%s,%s,NOW());";
        $query = $wpdb->prepare(
            $query_string,
            $job_type,
            $post_id,
            $started_at->format( 'Y-m-d H:i:s' ),
        );

        Db::query( $query );
        return $wpdb->insert_id;
    }

    /**
     *  Get all jobs
     *
     *  @return string[] All jobs
     */
    public static function getJobs(): array {
        global $wpdb;
        $urls = [];

        $table_name = self::getTableName();

        $rows = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );

        foreach ( $rows as $row ) {
            $urls[] = $row;
        }

        return $urls;
    }

    /**
     *  Check for any jobs in progress
     *
     *  @return bool All waiting jobs
     */
    public static function jobsInProgress(): bool {
        global $wpdb;
        $jobs = [];

        $table_name = self::getTableName();

        $jobs_in_progress = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name
            WHERE status = 'processing'"
        );

        return $jobs_in_progress > 0;
    }

    /**
     *  Get all waiting jobs
     *
     *  @return mixed[] All waiting jobs
     */
    public static function getProcessableJobs(): array {
        global $wpdb;
        $jobs = [];

        $table_name = self::getTableName();

        $rows = $wpdb->get_results(
            "SELECT * FROM $table_name
            WHERE status = 'waiting'
            ORDER BY id ASC"
        );

        foreach ( $rows as $row ) {
            $jobs[] = $row;
        }

        return $jobs;
    }

    /**
     * Get count of jobs organized by type
     *
     * @return int[] keys are job type and values are count
     */
    public static function getJobCountByType(): array {
        global $wpdb;
        $jobs = [];

        $table_name = self::getTableName();
        $query = "SELECT job_type, count(*) FROM $table_name GROUP BY job_type";

        $rows = $wpdb->get_results( $query, 'ARRAY_N' );
        foreach ( $rows as $row ) {
            $jobs[ $row[0] ] = $row[1];
        }

        return $jobs;
    }

    /*
        Skip processing jobs where a more recent job of same type exists

    */
    public static function squashQueue(): void {
        global $wpdb;

        $table_name = self::getTableName();

        $job_types = [
            'detect',
            'crawl',
            'post_process',
            'deploy',
            'direct_deploy',
            'direct_deploy_post',
        ];

        foreach ( $job_types as $job_type ) {
            // get all jobs for a type where status is 'waiting'
            if ( $job_type === 'direct_deploy_post' ) {
                // Don't collapse direct_deploy_post jobs that target a specific
                // single post
                $waiting_jobs = $wpdb->get_results(
                    "SELECT * FROM $table_name
                    WHERE job_type = '$job_type'
                    AND status = 'waiting'
                    AND triggering_post_id IS NULL
                    ORDER BY created_at DESC"
                );
            } else {
                $waiting_jobs = $wpdb->get_results(
                    "SELECT * FROM $table_name
                    WHERE job_type = '$job_type'
                    AND status = 'waiting'
                    ORDER BY created_at DESC"
                );
            }

            // abort if less than 2 jobs of same type in waiting status
            if ( $waiting_jobs < 2 ) {
                WsLog::l( 'less than 2 jobs for this type, continuing' );
                WsLog::l( (string) count( $waiting_jobs ) );
                continue;
            }

            // remove latest one
            array_shift( $waiting_jobs );

            // set all but most recent one to 'skipped'
            foreach ( $waiting_jobs as $waiting_job ) {
                $wpdb->update(
                    $table_name,
                    [ 'status' => 'skipped' ],
                    [ 'id' => $waiting_job->id ]
                );

            }
        }
    }

    public static function setStatus( int $id, string $status ): void {
        global $wpdb;

        $table_name = self::getTableName();

        $query = $wpdb->prepare(
            "UPDATE $table_name SET status = %s, status_updated_at = NOW() WHERE id = %d",
            $status,
            $id
        );
        Db::query(
            $query,
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            on_error: function ( $error ) use ( $query ) {
                // Try to create status_update_at column
                self::createTable();
                return Db::query( $query );
            },
        );
    }

    /**
     *  Get total count of jobs
     *
     *  @return int Total jobs
     */
    public static function getTotalJobs(): int {
        global $wpdb;

        $table_name = self::getTableName();

        $total_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        return $total_jobs;
    }

    public static function getWaitingJobs(): int {
        return static::getWaitingJobsCount();
    }

    /**
     *  Get count of waiting jobs
     *
     *  @return int Waiting jobs
     */
    public static function getWaitingJobsCount(): int {
        global $wpdb;

        $table_name = self::getTableName();

        $total_jobs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'waiting'" );

        return $total_jobs;
    }

    /**
     *  Clear JobQueue via truncate or deletion
     */
    public static function truncate(): void {
        WsLog::l( 'Deleting all jobs from JobQueue' );

        global $wpdb;

        $table_name = self::getTableName();

        $wpdb->query( "TRUNCATE TABLE $table_name" );

        $total_jobs = self::getTotalJobs();

        if ( $total_jobs > 0 ) {
            WsLog::l( 'failed to truncate JobQueue: try deleting instead' );
        }
    }

    /**
     *  Detect any 'processing' jobs that are not running and change status to 'failed'.
     *
     *  @throws \Throwable
     */
    public static function markFailedJobs(): void {
        global $wpdb;

        $job_types = [ 'detect', 'crawl', 'post_process', 'deploy', 'direct_deploy' ];
        $table_name = self::getTableName();

        $wpdb->query( 'START TRANSACTION' );

        foreach ( $job_types as $type ) {
            try {
                $lock = Db::getLockName( self::getTableName(), $type );
                $query = "SELECT IS_FREE_LOCK('$lock') AS free";
                $free = intval( $wpdb->get_row( $query )->free );

                if ( $free ) {
                    $failed_jobs = $wpdb->query(
                        "UPDATE $table_name
                         SET status = 'failed'
                         WHERE job_type = '$type' AND status = 'processing'"
                    );
                    if ( $failed_jobs ) {
                        $s = $failed_jobs === 1 ? '' : 's';
                        WsLog::l( "$failed_jobs processing $type job$s marked as failed." );
                    }
                }

                $wpdb->query( 'COMMIT' );
            } catch ( \Throwable $e ) {
                $wpdb->query( 'ROLLBACK' );
                throw $e;
            }
        }
    }

    /**
     * Process a single job
     */
    public static function process( \stdClass $job ): void {
        global $wpdb;

        $lock = Db::getLockName( self::getTableName(), $job->job_type );
        $query = "SELECT GET_LOCK('$lock', 30) AS lck";
        $locked = intval( $wpdb->get_row( $query )->lck );
        if ( ! $locked ) {
            WsLog::l( "Failed to acquire \"$lock\" lock." );
            return;
        }
        try {
            self::setStatus( $job->id, 'processing' );

            switch ( $job->job_type ) {
                case 'detect':
                    WsLog::l( 'Starting URL detection' );
                    $detected_count = URLDetector::enqueueURLs();
                    WsLog::l( "URL detection completed ($detected_count URLs detected)" );
                    break;
                case 'crawl':
                    Controller::crawl();
                    break;
                case 'post_process':
                    WsLog::l( 'Starting post-processing' );
                    $post_processor = new PostProcessor();
                    $post_processor->processStaticSite( StaticSite::getPath() );
                    WsLog::l( 'Post-processing completed' );
                    break;
                case 'deploy':
                    $deployer = Addons::getDeployer();

                    if ( ! $deployer ) {
                        WsLog::l( 'No deployment add-ons are enabled, skipping deployment.' );
                    } else {
                        WsLog::l( 'Starting deployment' );
                        do_action(
                            Controller::getHookName( 'deploy' ),
                            ProcessedSite::getPath(),
                            $deployer
                        );
                    }
                    WsLog::l( 'Starting post-deployment actions' );
                    do_action(
                        Controller::getHookName( 'post_deploy_trigger' ),
                        $deployer
                    );
                    break;
                case 'direct_deploy':
                    $deployer = new DirectDeployer();
                    WsLog::l( 'Starting direct deployment' );
                    $deployer->deploy();
                    $deployer->deployComplete();
                    break;
                case 'direct_deploy_post':
                    $deployer = new DirectDeployer();
                    $post_id = $job->triggering_post_id;
                    if ( $post_id ) {
                        $path = wp_make_link_relative( get_permalink( $post_id ) );
                        $paths = new \ArrayIterator( [ new PathInfo( $path ) ] );
                        $detected = DetectedFiles::addPathsIter( $paths );
                        WsLog::l( 'Starting direct deployment for path ' . $path );
                        $deployer->deployPaths( $detected );
                    } else {
                        WsLog::w( 'No post ID found for direct deployment post' );
                    }
                    $deployer->deployComplete();
                    break;
                default:
                    WsLog::l( 'Trying to process unknown job type' );
            }
            self::setStatus( $job->id, 'completed' );
        } catch ( \Throwable $e ) {
            self::setStatus( $job->id, 'failed' );
            // We don't want to crawl and deploy if the detect step fails.
            // Skip all waiting jobs when one fails.
            $table_name = self::getTableName();
            $wpdb->query(
                "UPDATE $table_name
                    SET status = 'skipped'
                    WHERE status = 'waiting'"
            );
            throw $e;
        } finally {
            $wpdb->query( "DO RELEASE_LOCK('$lock')" );
        }
    }
}
