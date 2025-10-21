<?php

require_once __DIR__ . '/../../vendor/autoload.php';

if ( ! function_exists( 'untrailingslashit' ) ) {
    function untrailingslashit( string $str ): string {
        return rtrim( $str, '/\\' );
    }
}

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( string $str ): string {
        return rtrim( $str, '/\\' ) . '/';
    }
}
