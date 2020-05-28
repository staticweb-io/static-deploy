<?php

namespace StaticWeb;

use Aws\Exception\AwsException;

class AdminBar {

    public static function admin_bar_menu_hook( \WP_Admin_Bar $wp_admin_bar ) : void {
        if ( ! defined( 'WP2STATIC_PATH' ) ) {
            return;
        }

        $title = '<div class="staticweb-deploy-status-container" style="border-radius: 5px"><div class="staticweb-deploy-status" style="margin: 0 5px">WP2Static: Checking status...</div></div>';

        $status = array(
            'id' => 'staticweb-status',
            'title' => $title
        );
        $wp_admin_bar->add_node( $status );
    }

    public static function after_admin_bar_render() : void {
?>
    <script>
     var staticweb_last_interval = 30000;
     var staticweb_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
     var staticweb_job_type_labels = {
         detect: "Detecting URLs",
         crawl: "Crawling Site",
         post_process: "Post-Processing",
         deploy: "Deploying"
     }

     function staticweb_update_status() {
         jQuery.ajax({
             url: staticweb_ajax_url,
             method: "GET",
             data: {action: "staticweb_job_queue"}
         }).done(function(msg) {
             staticweb_last_interval = 30000
             setTimeout(staticweb_update_status, 30000)

             var data = JSON.parse(msg)
             var style = {"background-color": "", "border-radius": "5px"};
             var text;

             if (0 == data.jobs.length) {
                 if (0 == data.job_count) {
                     if (data.invalidations) {
                         text = "Refreshing CDN cache"
                     } else {
                         style["background-color"] = "green";
                         text = "Deployed"
                     }
                 } else {
                     text = "Queued"
                 }
             } else {
                 text = staticweb_job_type_labels[data.jobs[0].job_type]
             }

             var container = jQuery(".staticweb-deploy-status-container")
             container.css(style);
             var status = jQuery(".staticweb-deploy-status")
             status.text("WP2Static: " + text);
         }).fail(function(msg) {
             staticweb_last_interval = 2 * staticweb_last_interval
             setTimeout(staticweb_update_status, staticweb_last_interval)
         })
     }

     staticweb_update_status()
    </script>
<?php
    }

    public static function ajax_staticweb_job_queue() : void {
        $job_count = \WP2Static\JobQueue::getWaitingJobs();
        $jobs = self::get_jobs_in_progress();
        $invalidations = self::list_invalidations_in_progress();
        if ($invalidations
            && array_key_exists( 'Invalidations', $invalidations)
            && 0 < count( $invalidations['Invalidations'] ) ) {
            $in_progress = true;
        } else {
            $in_progress = false;
        }
        $arr = ['invalidations' => $in_progress,
                'job_count' => $job_count,
                'jobs' => $jobs];
        echo( json_encode ( $arr ) );
        die();
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

    public static function list_invalidations( int $max_items = 5) {
        $cloudfront = \WP2StaticS3\Deployer::cloudfront_client();
        $distribution_id = \WP2StaticS3\Controller::getValue( 'cfDistributionID' );

        if ( ! $distribution_id ) {
            return;
        }

        try {
            return $cloudfront->listInvalidations(
                ['DistributionId' => $distribution_id,
                 'MaxItems' => "$max_items"] );
        } catch ( AwsException $e ) {
            return $e;
        }
    }

    public static function list_invalidations_in_progress( int $max_items = 5) {
        $invalidations = self::list_invalidations( $max_items );

        if ( ! $invalidations ) {
            return;
        } else if ( is_a( $invalidations, 'Aws\Exception\AwsException' ) ) {
            return ['Exception' => $invalidations];
        } else {
            $inv_items = $invalidations['InvalidationList']['Items'];

            $arr = [];
            foreach( $inv_items as $inv) {
                if ( "InProgress" === $inv['Status'] ) {
                    array_push( $arr, $inv );
                }
            }
            return ['Invalidations' => $arr];
        }
    }

}
