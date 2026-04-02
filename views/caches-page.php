<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use StaticDeploy\Controller;
use StaticDeploy\URLHelper;

/**
 * @var mixed[] $view
 */

/**
 * @var int $static_deploy_detected_files_total
 */
$static_deploy_detected_files_total = $view['detectedFilesTotal'];

/**
 * @var int $static_deploy_crawled_files_total
 */
$static_deploy_crawled_files_total = $view['crawledFilesTotal'];

/**
 * @var int $static_deploy_exported_site_file_count
 */
$static_deploy_exported_site_file_count = $view['exportedSiteFileCount'];

/**
 * @var string $static_deploy_uploads_path
 */
$static_deploy_uploads_path = $view['uploads_path'];

/**
 * @var int $static_deploy_processed_site_file_count
 */
$static_deploy_processed_site_file_count = $view['processedSiteFileCount'];

/**
 * @var mixed[] $static_deploy_deploy_cache_total_paths
 */
$static_deploy_deploy_cache_total_paths = $view['deployCacheTotalPaths'];

/**
 * @var string $static_deploy_exported_site_disk_space
 */
$static_deploy_exported_site_disk_space = $view['exportedSiteDiskSpace'];

/**
 * @var string $static_deploy_processed_site_disk_space
 */
$static_deploy_processed_site_disk_space = $view['processedSiteDiskSpace'];

wp_register_style(
    'static-deploy-select',
    '',
    [],
    1,
);
wp_enqueue_style( 'static-deploy-select' );
wp_add_inline_style(
    'static-deploy-select',
    '.static-deploy-select {
        width: 165px;
    }'
);

$static_deploy_uri = URLHelper::getCurrent();
if ( defined( 'STATIC_DEPLOY_WP_ORG_MODE' ) && STATIC_DEPLOY_WP_ORG_MODE ) {
    $static_deploy_uri = URLHelper::modifyUrl( [ '_wpnonce' => wp_create_nonce( strval( $view['nonce_action'] ) ) ], $static_deploy_uri );
}

?>

<div class="wrap">
    <p><i><a href="<?php echo esc_url( Controller::getAdminUrl( 'caches' ) ); ?>">Refresh page</a> to see latest status</i><p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Cache Type</th>
                <th>Statistics</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Detected Files</td>
                <td><?php echo (int) $static_deploy_detected_files_total; ?> files in database</td>
                <td>
                    <form
                        name="<?php echo esc_attr( Controller::getHookName( 'detected_files_delete' ) ); ?>"
                        method="POST"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                        <select name="action" class="static-deploy-select">
                            <option value="<?php echo esc_attr( Controller::getHookName( 'detected_files_show' ) ); ?>">Show</option>
                            <option value="<?php echo esc_attr( Controller::getHookName( 'detected_files_delete' ) ); ?>">Delete</option>
                        </select>

                        <button class="button btn-danger">Go</button>

                    </form>
                </td>
            </tr>
            <tr>
                <td>Crawled Files</td>
                <td>
                    <?php echo (int) $static_deploy_crawled_files_total; ?> URLs in database
                    <br>
                    <?php echo (int) $static_deploy_exported_site_file_count; ?> files, using <?php echo esc_html( $static_deploy_exported_site_disk_space ); ?>
                </td>
                <td>
                    <form
                        name="<?php echo esc_attr( Controller::getHookName( 'crawled_files_delete' ) ); ?>"
                        method="POST"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                        <select name="action" class="static-deploy-select">
                            <option value="<?php echo esc_attr( Controller::getHookName( 'crawled_files_show' ) ); ?>">Show</option>
                            <option value="<?php echo esc_attr( Controller::getHookName( 'crawled_files_delete' ) ); ?>">Delete</option>
                        </select>

                        <button class="button btn-danger">Go</button>

                    </form>
                </td>
            </tr>
            <tr>
                <td>Post-Processed Files</td>
                <td><?php echo (int) $static_deploy_processed_site_file_count; ?> files, using <?php echo esc_attr( $static_deploy_processed_site_disk_space ); ?></td>
                <td>
                    <form
                        name="<?php echo esc_attr( Controller::getHookName( 'post_processed_site_delete' ) ); ?>"
                        method="POST"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                        <select name="action" class="static-deploy-select">
                            <option value="<?php echo esc_attr( Controller::getHookName( 'post_processed_site_show' ) ); ?>">Show</option>
                            <option value="<?php echo esc_attr( Controller::getHookName( 'post_processed_site_delete' ) ); ?>">Delete</option>
                        </select>

                        <button class="button btn-danger">Go</button>

                    </form>
                </td>
            </tr>

            <tr>
                <td rowspan="<?php echo count( $static_deploy_deploy_cache_total_paths ); ?>">Deployed Files</td>
                    <?php $static_deploy_namespaces = array_keys( $static_deploy_deploy_cache_total_paths ); ?>
                    <?php if ( $static_deploy_namespaces !== [] ) { ?>
                        <td><?php echo esc_html( strval( $static_deploy_deploy_cache_total_paths[ $static_deploy_namespaces[0] ] ) ); ?> Paths in database for <code><?php echo esc_html( $static_deploy_namespaces[0] ); ?></code></td>
                    <?php } else { ?>
                        <td>0 paths in database</td>
                    <?php } ?>
                    <td>
                        <form
                            name="<?php echo esc_attr( Controller::getHookName( 'deploy_cache_delete' ) ); ?>"
                            method="POST"
                            action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                            <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                            <select name="action" class="static-deploy-select">
                                <option value="<?php echo esc_attr( Controller::getHookName( 'deploy_cache_show' ) ); ?>">Show</option>
                                <option value="<?php echo esc_attr( Controller::getHookName( 'deploy_cache_delete' ) ); ?>">Delete</option>
                            </select>

                            <input name="deploy_namespace" type="hidden" value="<?php echo esc_attr( $static_deploy_namespaces[0] ); ?>" />

                            <button class="button btn-danger">Go</button>

                        </form>
                    </td>
                    <?php
                    $static_deploy_deploy_cache_rows = count( $static_deploy_deploy_cache_total_paths );
                    for ( $static_deploy_i = 1; $static_deploy_i < $static_deploy_deploy_cache_rows; $static_deploy_i++ ) :
                        ?>
                        </tr>
                        <tr>
                        <td><?php echo esc_attr( strval( $static_deploy_deploy_cache_total_paths[ $static_deploy_namespaces[ $static_deploy_i ] ] ) ); ?> Paths in database for <code><?php echo esc_html( strval( $static_deploy_namespaces[ $static_deploy_i ] ) ); ?></code></td>
                        <td>
                            <form
                                name="<?php echo esc_attr( Controller::getHookName( 'deploy_cache_delete' ) ); ?>"
                                method="POST"
                                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                            <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                                <select name="action" class="static-deploy-select">
                                    <option value="<?php echo esc_attr( Controller::getHookName( 'deploy_cache_show' ) ); ?>">Show</option>
                                    <option value="<?php echo esc_attr( Controller::getHookName( 'deploy_cache_delete' ) ); ?>">Delete</option>
                                </select>

                                <input name="deploy_namespace" type="hidden" value="<?php echo esc_attr( $static_deploy_namespaces[ $static_deploy_i ] ); ?>" />

                                <button class="button btn-danger">Go</button>

                            </form>
                        </td>
                    <?php endfor; ?>
            </tr>
            </tbody>
        </table>

        <br>

        <form
            name="<?php echo esc_attr( Controller::getHookName( 'delete_all_caches' ) ); ?>"
            method="POST"
            action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

            <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

            <input name="action" type="hidden" value="<?php echo esc_attr( Controller::getHookName( 'delete_all_caches' ) ); ?>" />

            <button class="button btn-danger">Delete all caches</button>

        </form>
    </div>
</div>
