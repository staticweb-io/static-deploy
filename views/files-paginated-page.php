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
 * @var string $static_deploy_paginator_index
 */
$static_deploy_paginator_index = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_URL );

/**
 * @var int $static_deploy_paginator_page
 */
$static_deploy_paginator_page = $view['paginatorPage'];

/**
 * @var string $static_deploy_search_term
 */
$static_deploy_search_term = filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL ) ?? '';

/**
 * @var int $static_deploy_paginator_total_records
 */
$static_deploy_paginator_total_records = $view['paginatorTotalRecords'];

/**
 * @var int $static_deploy_paginator_first_page
 */
$static_deploy_paginator_first_page = $view['paginatorFirstPage'];

/**
 * @var int $static_deploy_paginator_last_page
 */
$static_deploy_paginator_last_page = $view['paginatorLastPage'];

$static_deploy_uri = URLHelper::getCurrent();
if ( defined( 'STATIC_DEPLOY_WP_ORG_MODE' ) && STATIC_DEPLOY_WP_ORG_MODE ) {
    $static_deploy_nonce = wp_create_nonce( Controller::getHookName( 'caches_page' ) );
    $static_deploy_uri = URLHelper::modifyUrl( [ '_wpnonce' => $static_deploy_nonce ], $static_deploy_uri );
}

?>

<div class="wrap">
    <br>

    <form id="posts-filter" method="GET">
        <input type="hidden" name="page" value="<?php echo esc_attr( $static_deploy_paginator_index ); ?>" />
        <input type="hidden" name="paged" value="<?php echo (int) $static_deploy_paginator_page; ?>" />

        <p class="search-box">
            <label class="screen-reader-text" for="post-search-input">Search <?php echo esc_html( $view['title'] ); ?>:</label>
            <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr( $static_deploy_search_term ); ?>">
            <input type="submit" id="search-submit" class="button" value="Search">
        </p>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1">Bulk Actions</option>
                    <option value="remove">Remove</option>
                </select>
                <input type="submit" id="doaction" class="button action" value="Apply">
            </div>
        
            <!-- start Paginator template partial -->
            <h2 class="screen-reader-text"><?php echo esc_html( $view['title'] ); ?> list navigation</h2>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo number_format( $static_deploy_paginator_total_records ); ?> items</span>
                <span class="pagination-links">
                    <?php if ( $static_deploy_paginator_page === $static_deploy_paginator_first_page ) : ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                    <?php else : ?>
                        <a class="first-page button" href="<?php echo esc_url( URLHelper::modifyUrl( [ 'paged' => 1 ], $static_deploy_uri ) ); ?>"><span class="screen-reader-text">First page</span><span aria-hidden="true">«</span></a>
                        <a class="prev-page button" href="<?php echo esc_url( URLHelper::modifyUrl( [ 'paged' => $static_deploy_paginator_page - 1 ], $static_deploy_uri ) ); ?>"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></a>
                    <?php endif; ?>
                    <span class="paging-input">
                        <label for="current-page-selector" class="screen-reader-text">Current Page</label>
                        <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo (int) $static_deploy_paginator_page; ?>" size="3" aria-describedby="table-paging">
                        <span class="tablenav-paging-text"> of
                            <span class="total-pages"><?php echo (int) $static_deploy_paginator_last_page; ?></span>
                        </span>
                    </span>
                    <?php if ( $static_deploy_paginator_page === $static_deploy_paginator_last_page ) : ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                    <?php else : ?>
                        <a class="next-page button" href="<?php echo esc_url( URLHelper::modifyUrl( [ 'paged' => $static_deploy_paginator_page + 1 ], $static_deploy_uri ) ); ?>"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>
                        <a class="last-page button" href="<?php echo esc_url( URLHelper::modifyUrl( [ 'paged' => $static_deploy_paginator_last_page ], $static_deploy_uri ) ); ?>"><span class="screen-reader-text">Last page</span><span aria-hidden="true">»</span></a>
                    <?php endif; ?>
                </span>
            </div>
            <!-- end Paginator template partial -->
            <br class="clear">
        </div>

        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <?php
                    foreach ( $view['colHeadings'] as $static_deploy_col_heading ) {
                        echo '<th>' . esc_html( $static_deploy_col_heading ) . '</th>';
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! $view['paginatorTotalRecords'] ) : ?>
                    <tr>
                        <td colspan="3"><?php echo esc_html( $view['emptyMessage'] ); ?></td>
                    </tr>
                <?php endif; ?>

                <?php
                foreach ( $view['paginatorRecords'] as $static_deploy_paginator_id => $static_deploy_record ) :
                    // Plugin Check Plugin forces us to repeatedly escape
                    // the same values.
                    ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $static_deploy_paginator_id ); ?>">
                                    Select <?php echo esc_html( $static_deploy_record[0] ); ?>
                                </label>
                                <input id="cb-select-<?php echo esc_attr( $static_deploy_paginator_id ); ?>" type="checkbox" name="id[]" value="<?php echo esc_attr( $static_deploy_paginator_id ); ?>">
                                <div class="locked-indicator">
                                    <span class="locked-indicator-icon" aria-hidden="true"></span>
                                    <span class="screen-reader-text"><?php echo esc_html( $static_deploy_record->path ); ?></span>
                                </div>
                            </th>
                            <?php
                            foreach ( $static_deploy_record as $static_deploy_v ) {
                                echo '<td>' . esc_html( $static_deploy_v ) . '</td>';
                            }
                            ?>
                        </tr>
                    <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</div>
