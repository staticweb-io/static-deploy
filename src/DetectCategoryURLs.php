<?php

namespace WP2Static;

class DetectCategoryURLs {

    /**
     * Detect Category URLs
     *
     * @return \Iterator<array> list of URLs
     */
    public static function detect(): \Iterator {
        WsLog::d( 'Detecting category URLs' );

        global $wp_rewrite, $wpdb;

        $args = [ 'public' => true ];

        $taxonomies = get_taxonomies( $args, 'objects' );

        foreach ( $taxonomies as $taxonomy ) {
            /** @var list<\WP_Term> $terms */
            $terms = get_terms(
                // @phpstan-ignore-next-line
                $taxonomy->name,
            );

            foreach ( $terms as $term ) {
                $term_link = get_term_link( $term );

                if ( ! is_string( $term_link ) ) {
                    continue;
                }

                $permalink = trim( $term_link );

                yield [ 'url' => $permalink ];
            }
        }
    }
}
