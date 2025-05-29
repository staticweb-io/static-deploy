<?php

namespace WP2Static;

class DetectCategoryPaginationURLs {

    /**
     * Detect Category Pagination URLs
     *
     * @return \Iterator<array> list of URLs
     */
    public static function detect( bool $log = false ) : \Iterator {
        if ( $log ) {
            WsLog::l( 'Detecting category pagination URLs' );
        }

        global $wp_rewrite, $wpdb;

        // first we get each category with total posts as an array
        // similar to getting regular category URLs, but with extra
        // info we need to get correct pagination URLs
        $args = [ 'public' => true ];

        $category_links = [];
        $taxonomies = get_taxonomies( $args, 'objects' );
        $pagination_base = $wp_rewrite->pagination_base;
        $default_posts_per_page = get_option( 'posts_per_page' );

        foreach ( $taxonomies as $taxonomy ) {
            /** @var list<\WP_Term> $terms */
            $terms = get_terms(
                // @phpstan-ignore-next-line
                $taxonomy->name,
                [ 'hide_empty' => true ]
            );

            foreach ( $terms as $term ) {
                $term_link = get_term_link( $term );

                if ( ! is_string( $term_link ) ) {
                    continue;
                }
                $permalink = trim( $term_link );

                $total_posts = $term->count;

                $term_url = $permalink;

                $category_links[ $term_url ] = $total_posts;
            }
        }

        foreach ( $category_links as $term => $total_posts ) {
            $total_pages = ceil( $total_posts / $default_posts_per_page );

            for ( $page = 1; $page <= $total_pages; $page++ ) {
                yield [ 'url' => "{$term}{$pagination_base}/{$page}/" ];
            }
        }
    }
}
