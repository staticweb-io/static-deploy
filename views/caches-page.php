<?php
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

/**
 * @var mixed[] $view
 */

/**
 * @var int $detected_files_total
 */
$detected_files_total = $view['DetectedFilesTotal'];

/**
 * @var int $crawled_files_total
 */
$crawled_files_total = $view['crawledFilesTotal'];

/**
 * @var int $exported_site_file_count
 */
$exported_site_file_count = $view['exportedSiteFileCount'];

/**
 * @var string $uploads_path
 */
$uploads_path = $view['uploads_path'];

/**
 * @var int $processed_site_file_count
 */
$processed_site_file_count = $view['processedSiteFileCount'];

/**
 * @var mixed[] $deploy_cache_total_paths
 */
$deploy_cache_total_paths = $view['deployCacheTotalPaths'];

/**
 * @var string $exported_site_disk_space
 */
$exported_site_disk_space = $view['exportedSiteDiskSpace'];

/**
 * @var string $processed_site_disk_space
 */
$processed_site_disk_space = $view['processedSiteDiskSpace'];

?>

<style>
select.wp2static-select {
    width: 165px;
}
</style>

<div class="wrap">
    <p><i><a href="<?php echo admin_url( 'admin.php?page=wp2static-caches' ); ?>">Refresh page</a> to see latest status</i><p>

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
                <td><?php echo $detected_files_total; ?> files in database</td>
                <td>
                    <form
                        name="<?php echo Controller::getHookName( 'detected_files_delete' ); ?>"
                        method="POST"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                        <select name="action" class="wp2static-select">
                            <option value="<?php echo Controller::getHookName( 'detected_files_show' ); ?>">Show URLs</option>
                            <option value="<?php echo Controller::getHookName( 'detected_files_delete' ); ?>">Delete Detected Files</option>
                        </select>

                        <button class="button btn-danger">Go</button>

                    </form>
                </td>
            </tr>
            <tr>
                <td>Crawled Files</td>
                <td><?php echo $crawled_files_total; ?> URLs in database</td>
                <td>
                    <form
                        name="<?php echo Controller::getHookName( 'crawled_files_delete' ); ?>"
                        method="POST"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                        <select name="action" class="wp2static-select">
                            <option value="<?php echo Controller::getHookName( 'crawled_files_show' ); ?>">Show URLs</option>
                            <option value="<?php echo Controller::getHookName( 'crawled_files_delete' ); ?>">Delete Crawled Files</option>
                        </select>

                        <button class="button btn-danger">Go</button>

                    </form>
                </td>
            </tr>
            <tr>
                <td>Generated Static Site</td>
                <td><?php echo $exported_site_file_count; ?> files, using <?php echo $exported_site_disk_space; ?>
                    <br>

                    <a href="file://<?php echo \WP2Static\StaticSite::getPath(); ?>" />Path</a>

                </td>
                <td>
                    <form
                        name="<?php echo Controller::getHookName( 'static_site_delete' ); ?>"
                        method="POST"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                        <select name="action" class="wp2static-select">
                            <option value="<?php echo Controller::getHookName( 'static_site_show' ); ?>">Show Paths</option>
                            <option value="<?php echo Controller::getHookName( 'static_site_delete' ); ?>">Delete Files</option>
                        </select>

                        <button class="button btn-danger">Go</button>

                    </form>
                </td>
            </tr>
            <tr>
                <td>Post-processed Static Site</td>
                <td><?php echo $processed_site_file_count; ?> files, using <?php echo $processed_site_disk_space; ?>
                    <br>

                    <a href="file://<?php echo \WP2Static\ProcessedSite::getPath(); ?>" />Path</a>
                </td>
                <td>
                    <form
                        name="<?php echo Controller::getHookName( 'post_processed_site_delete' ); ?>"
                        method="POST"
                        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                        <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                        <select name="action" class="wp2static-select">
                            <option value="<?php echo Controller::getHookName( 'post_processed_site_show' ); ?>">Show Paths</option>
                            <option value="<?php echo Controller::getHookName( 'post_processed_site_delete' ); ?>">Delete Files</option>
                        </select>

                        <button class="button btn-danger">Go</button>

                    </form>
                </td>
            </tr>

            <?php $deploy_cache_rows = count( $deploy_cache_total_paths ); ?>
            <tr>
                <td rowspan="<?php echo $deploy_cache_rows; ?>">Deploy Cache</td>
                    <?php $namespaces = array_keys( $deploy_cache_total_paths ); ?>
                    <?php if ( $namespaces ) { ?>
                        <td><?php echo strval( $deploy_cache_total_paths[ $namespaces[0] ] ); ?> Paths in database for <code><?php echo $namespaces[0]; ?></code></td>
                    <?php } else { ?>
                        <td>0 paths in database</td>
                    <?php } ?>
                    <td>
                        <form
                            name="<?php echo Controller::getHookName( 'deploy_cache_delete' ); ?>"
                            method="POST"
                            action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                            <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                            <select name="action" class="wp2static-select">
                                <option value="<?php echo Controller::getHookName( 'deploy_cache_show' ); ?>">Show Paths</option>
                                <option value="<?php echo Controller::getHookName( 'deploy_cache_delete' ); ?>">Delete Deploy Cache</option>
                            </select>

                            <input name="deploy_namespace" type="hidden" value="<?php echo $namespaces[0]; ?>" />

                            <button class="button btn-danger">Go</button>

                        </form>
                    </td>
                    <?php for ( $i = 1; $i < $deploy_cache_rows; $i++ ) : ?>
                        </tr>
                        <tr>
                        <td><?php echo strval( $deploy_cache_total_paths[ $namespaces[ $i ] ] ); ?> Paths in database for <code><?php echo strval( $namespaces[ $i ] ); ?></code></td>
                        <td>
                            <form
                                name="<?php echo Controller::getHookName( 'deploy_cache_delete' ); ?>"
                                method="POST"
                                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                            <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

                                <select name="action" class="wp2static-select">
                                    <option value="<?php echo Controller::getHookName( 'deploy_cache_show' ); ?>">Show Paths</option>
                                    <option value="<?php echo Controller::getHookName( 'deploy_cache_delete' ); ?>">Delete Deploy Cache</option>
                                </select>

                                <input name="deploy_namespace" type="hidden" value="<?php echo $namespaces[ $i ]; ?>" />

                                <button class="button btn-danger">Go</button>

                            </form>
                        </td>
                    <?php endfor; ?>
            </tr>
            </tbody>
        </table>

        <br>

        <form
            name="<?php echo Controller::getHookName( 'delete_all_caches' ); ?>"
            method="POST"
            action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

            <?php wp_nonce_field( strval( $view['nonce_action'] ) ); ?>

            <input name="action" type="hidden" value="<?php echo Controller::getHookName( 'delete_all_caches' ); ?>" />

            <button class="button btn-danger">Delete all caches</button>

        </form>
    </div>
</div>
