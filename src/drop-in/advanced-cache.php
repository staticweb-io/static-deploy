<?php

/**
 * Page cache for WordPress
 * Must be placed in wp-content/advanced-cache.php
 * It will be loaded when WP_CACHE is true
 * See https://developer.wordpress.org/reference/functions/_get_dropins/
 *
 * This runs before plugins and themes and most WordPress
 * code runs, so we only have access to a limited set of
 * WordPress functions.
 */

class StaticDeployPageCache {
    private int $status_code;
    private string $status_header;

    public function capture_response(): void {
        add_filter(
            'status_header',
            [ $this, 'filter_status_header' ],
            10,
            2
        );

        $buffering = ob_start( [ $this, 'receive_output' ] );
        if ( $buffering === false ) {
            error_log( 'Output buffering failed' );
        }
    }

    /*
     * https://developer.wordpress.org/reference/hooks/status_header/
     */
    public function filter_status_header(
        string $status_header,
        int $code
    ): string {
        $this->status_code = $code;
        $this->status_header = $status_header;
        return $status_header;
    }

    /**
     * Receives the PHP output, which should be the body
     * of an HTTP response, and caches it.
     *
     * This can be called whenever output is flushed,
     * and not necessarily when output is finished.
     *
     * This is the callback provided to ob_start
     * https://www.php.net/manual/en/function.ob-start.php
     *
     * The return value determines the output that is sent
     * to the client.
     *  - A string value: Sent instead of the buffer
     *  - false: Sends the original output buffer contents
     *  - true: Sends an empty string instead of the buffer
     */
    public function receive_output(
        string $buffer
    ): string|bool {
        $method = $_SERVER['REQUEST_METHOD'];
        // If not a cacheable method, return unchanged.
        if ( $method !== 'GET' && $method !== 'HEAD' ) {
            return false;
        }

        return false;
    }
}

new StaticDeployPageCache()->capture_response();
