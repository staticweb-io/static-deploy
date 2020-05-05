<?php

namespace StaticWeb;

class AdminBar {

    public static function admin_bar_menu_hook( \WP_Admin_Bar $wp_admin_bar ) : void {
        // We query waiting jobs before in-progress to prevent a timing error
        // if a job changes from waiting to procressing between queries.
        $job_count = \WP2Static\JobQueue::getWaitingJobs();
        $jobs = self::get_jobs_in_progress();

        if ( empty( $jobs ) ) {
            if ( $job_count === 0) {
                $title = '<div style="background-color: green; border-radius: 5px"><div style="margin: 0 5px">StaticWeb: Deployed</div></div>';
            } else {
                $title = '<div>StaticWeb: Queued</div>';
            }
        } else {
            $job_type_labels = array(
                'detect' => 'Detecting URLs',
                'crawl' => 'Crawling Site',
                'post_process' => 'Post-Processing',
                'deploy' => 'Deploying'
            );

            $job_type = $jobs[0]->job_type;
            $title = '<div>StaticWeb: ' . $job_type_labels[$job_type] . '</div>';
        }

        $status = array(
            'id' => 'staticweb-status',
            'title' => $title
        );
        $wp_admin_bar->add_node( $status );
    }

    public static function get_jobs_in_progress() : array {
        global $wpdb;
        $jobs = [];

        $table_name = $wpdb->prefix . 'wp2static_jobs';

        $jobs_in_progress = $wpdb->get_results(
            "SELECT * FROM $table_name
            WHERE status = 'processing'"
        );

        return $jobs_in_progress;
    }

}
