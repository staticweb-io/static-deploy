<?php

namespace StaticDeploy;

use Aws\Exception\AwsException;

class AdminBar {
    public static function registerHooks(): void {
        add_action(
            'admin_bar_menu',
            self::adminBarMenuHook( ... ),
            100
        );
        add_action(
            'wp_after_admin_bar_render',
            self::afterAdminBarRender( ... )
        );
        $hook_name = 'wp_ajax_' . Controller::getHookName( 'job_queue' );
        add_action(
            $hook_name,
            self::ajaxJobQueue( ... )
        );
    }

    public static function adminBarMenuHook( \WP_Admin_Bar $wp_admin_bar ): void {
        $deployment_url = Options::getValue( 'deploymentURL' );

        $title = '<div class="static-deploy-deploy-status-container" ' .
            'style="border-radius: 5px; link-color: #fff; visibility: hidden">' .
            '<a style="color: white; display: flex; align-items: center;" ' .
            'href="' . $deployment_url . '" target="_blank">' .
            '<span class="wp-menu-image dashicons-before dashicons-shield-alt" ' .
            'aria-hidden="true" style="display: flex; align-items: center;"></span>' .
            '<span class="static-deploy-deploy-status" ' .
            'style="margin: 0 5px">Static Deploy: Checking status...</span></a></div>';

        $group = [
            'id' => 'static-deploy-group',
        ];
        $wp_admin_bar->add_group( $group );

        $node = [
            'id' => 'static-deploy-status',
            'parent' => 'static-deploy-group',
            'title' => $title,
        ];
        $wp_admin_bar->add_node( $node );

        $menu_items = Options::getBlobValue( 'adminBarMenuItems' );
        $menu_items = (array) json_decode( $menu_items );

        // Sort alphabetically by label
        uasort(
            $menu_items,
            fn( $a, $b ): int => strcmp( (string) $a->label, (string) $b->label )
        );

        foreach ( $menu_items as $id => $item ) {
            $label = sanitize_text_field( $item->label );
            $href = esc_url( $item->href );
            $node = [
                'id' => $id,
                'parent' => 'static-deploy-status',
                'title' => '<a href="' . $href . '" target="_blank">' . $label . '</a>',
            ];
            $wp_admin_bar->add_node( $node );
        }
    }

    public static function afterAdminBarRender(): void {
        $ajax_job_queue_url = Controller::getAdminAjaxUrl( 'job_queue' );
        ?>
    <script>
    var static_deploy_job_queue_url = "<?php echo esc_url( $ajax_job_queue_url ); ?>";
    var static_deploy_last_interval = 30000;
    var static_deploy_job_type_labels = {
        detect: "Detecting URLs",
        crawl: "Crawling Site",
        post_process: "Post-Processing",
        deploy: "Deploying",
        direct_deploy: "Deploying (Direct)",
        direct_deploy_post: "Deploying (Single Post)",
    };
    var static_deploy_idle = false;

    function static_deploy_update_status_button(text, bgcolor) {
        document.querySelectorAll(".static-deploy-deploy-status-container").forEach(el => {
            el.style.backgroundColor = bgcolor;
            el.style.borderRadius = "5px";
        });

        document.querySelectorAll(".static-deploy-deploy-status").forEach(el => {
            el.textContent = "Static Deploy: " + text;
        });
    }

    function static_deploy_check_idle() {
        if ( static_deploy_idle && document.visibilityState == 'visible' ) {
            static_deploy_update_status_button('Checking status...', '');
            static_deploy_update_status();
        }
    }

    function static_deploy_process_job_queue_data(data) {
        static_deploy_last_interval = 30000;
        setTimeout(static_deploy_update_status, 30000);

        // Cache data to localStorage so we can show it immediately
        // when page is loaded or refreshed
        data.timestamp = Date.now();
        try {
            localStorage.setItem('static_deploy_job_queue', JSON.stringify(data));
        } catch (e) {
            console.warn('Could not write to localStorage:', e);
        }

        let bgcolor = "";
        let text;

        if (data.jobs.length === 0) {
            if (data.job_count === 0) {
                if (data.invalidations) {
                    text = "Refreshing CDN cache";
                } else {
                    bgcolor = "green";
                    text = "Deployed";
                }
            } else {
                text = "Queued";
            }
        } else {
            let type = data.jobs[0].job_type;
            text = static_deploy_job_type_labels[type] || type;
        }

        static_deploy_update_status_button(text, bgcolor);
    }

    function static_deploy_update_status() {
        if ( document.visibilityState != 'visible' ) {
            static_deploy_idle = true;
            static_deploy_last_interval = 30000;
            setTimeout(static_deploy_update_status, 30000);
            static_deploy_update_status_button("Idle", "");
            return;
        }

        static_deploy_idle = false;
        fetch(static_deploy_job_queue_url, {
            method: "GET",
        })
        .then(response => response.json())
        .then(static_deploy_process_job_queue_data)
        .catch(error => {
            console.error(error);
            static_deploy_last_interval *= 2;
            setTimeout(static_deploy_update_status, static_deploy_last_interval);
        });
    }

    function static_deploy_init() {
        // Apply cached data
        try {
            const cached = JSON.parse(localStorage.getItem('static_deploy_job_queue'));
            // Don't use cached data older than one week
            const millis_in_week = 7 * 86400 * 1000;
            const millis_since = Date.now() - cached.timestamp; // NaN if no cached data
            if (cached && millis_since < millis_in_week) {
                static_deploy_process_job_queue_data(cached);
            }
        } catch (e) {
            console.warn('Could not read from localStorage:', e);
        }

        document.querySelectorAll(".static-deploy-deploy-status-container").forEach(el => {
            el.style.visibility = "visible";
        });

        static_deploy_update_status();
    }

    window.onload = (event) => {
        setInterval(static_deploy_check_idle, 1000);
        setTimeout(static_deploy_init, 1);
    };
    </script>
        <?php
    }

    public static function ajaxJobQueue(): void {
        $job_count = JobQueue::getWaitingJobs();
        $jobs = self::getJobsInProgress();
        $invalidations = self::listInvalidationsInProgress();
        if ( $invalidations
        && array_key_exists( 'Invalidations', $invalidations )
        && 0 < count( $invalidations['Invalidations'] ) ) {
            $in_progress = true;
        } else {
            $in_progress = false;
        }
        $arr = [
            'invalidations' => $in_progress,
            'job_count' => $job_count,
            'jobs' => $jobs,
        ];
        echo( json_encode( $arr ) );
        die();
    }

    public static function getJobsInProgress(): array {
        global $wpdb;

        $table_name = JobQueue::getTableName();

        return $wpdb->get_results(
            "SELECT * FROM $table_name
            WHERE status = 'processing'"
        );
    }

    public static function listInvalidations( int $max_items = 5 ) {
        $distribution_id = S3\S3Options::getValue( 'distributionId' );

        if ( ! $distribution_id ) {
            return;
        }

        try {
            $cloudfront = S3\Deployer::cloudfrontClient();
            return $cloudfront->listInvalidations(
                [
                    'DistributionId' => $distribution_id,
                    'MaxItems' => "$max_items",
                ]
            );
        } catch ( AwsException $e ) {
            return $e;
        }
    }

    public static function listInvalidationsInProgress( int $max_items = 5 ) {
        $invalidations = self::listInvalidations( $max_items );
        if ( ! $invalidations ) {
            return;
        }

        if ( is_a( $invalidations, 'Aws\Exception\AwsException' ) ) {
            return [ 'Exception' => $invalidations ];
        }

        $inv_items = $invalidations['InvalidationList']['Items'];
        $arr = [];
        foreach ( $inv_items as $inv_item ) {
            if ( 'InProgress' === $inv_item['Status'] ) {
                array_push( $arr, $inv_item );
            }
        }
        return [ 'Invalidations' => $arr ];
    }
}
