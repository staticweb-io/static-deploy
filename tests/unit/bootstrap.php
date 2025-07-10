<?php

require_once __DIR__ . '/../../vendor/autoload.php';

WP_Mock::bootstrap();

if ( ! function_exists( 'untrailingslashit' ) ) {
    function untrailingslashit( $str ) {
        return rtrim( $str, '/\\' );
    }
}

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $str ) {
        return rtrim( $str, '/\\' ) . '/';
    }
}
