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
                $title = '<div class="staticweb-deploy-status-container" style="background-color: green; border-radius: 5px"><div class="staticweb-deploy-status" style="margin: 0 5px">WP2Static: Deployed</div></div>';
            } else {
                $title = '<div class="staticweb-deploy-status-container" style="border-radius: 5px"><div class="staticweb-deploy-status" style="margin: 0 5px">WP2Static: Queued</div></div>';
            }
        } else {
            $job_type_labels = array(
                'detect' => 'Detecting URLs',
                'crawl' => 'Crawling Site',
                'post_process' => 'Post-Processing',
                'deploy' => 'Deploying'
            );

            $job_type = $jobs[0]->job_type;
            $title = '<div class="staticweb-deploy-status-container" style="border-radius: 5px"><div class="staticweb-deploy-status" style="margin: 0 5px">WP2Static: ' . $job_type_labels[$job_type] . '</div></div>';
        }

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
                     style["background-color"] = "green";
                     text = "Deployed"
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

     setTimeout(staticweb_update_status, 30000)
    </script>
<?php
    }

    public static function ajax_staticweb_job_queue() : void {
        $job_count = \WP2Static\JobQueue::getWaitingJobs();
        $jobs = self::get_jobs_in_progress();
        $arr = array('job_count'=>$job_count, 'jobs'=>$jobs);
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

}
