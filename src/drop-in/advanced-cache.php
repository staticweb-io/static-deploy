<?php declare(strict_types=1);
// phpcs:disable Generic.Files.OneObjectStructurePerFile

interface StaticDeployCacheInterface {
    public function get( string $key ): ?string;
    public function set( string $key, string $value ): void;
}

class StaticDeployFileCache implements StaticDeployCacheInterface {
    public string $dir;

    public function __construct(
        string $dir,
    ) {
        mkdir( $dir, 0700, true );
        if ( ! is_dir( $dir ) ) {
            die( 'Failed to create cache directory' );
        }
        $this->dir = $dir;
    }

    public function get(
        string $key
    ): ?string {
        if ( ! file_exists( $this->dir . '/' . $key ) ) {
            return null;
        }
        return file_get_contents( $this->dir . '/' . $key );
    }

    public function set(
        string $key,
        string $value
    ): void {
        file_put_contents(
            $this->dir . '/' . $key,
            $value
        );
    }
}

/**
 * Page cache for WordPress
 * Must be placed in wp-content/advanced-cache.php
 * It will be loaded when WP_CACHE is true
 * See https://developer.wordpress.org/reference/functions/_get_dropins/
 *
 * This runs before plugins and themes and most WordPress
 * code runs, so we only have access to a limited set of
 * WordPress functions.
 *
 * To understand the behavior of the page cache, one must
 * understand HTTP caching.
 * See https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Caching
 * Note that rather than "revalidating" cached data, we simply
 * regenerate it. Since we are the origin server as well
 * as the cache, there is no point in revalidating.
 */

class StaticDeployPageCache {
    private StaticDeployCacheInterface $cache;
    private int $status_code;
    private string $status_header;

    public function __construct(
        StaticDeployCacheInterface $cache,
    ) {
        $this->cache = $cache;
    }

    public function capture_response(): void {
        add_filter(
            'status_header',
            [ $this, 'filter_status_header' ],
            10,
            2
        );

        $buffering = ob_start( [ $this, 'receive_output' ] );
        if ( $buffering === false ) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions
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
     * Returns true if the headers permit caching.
     */
    public static function headers_should_cache(
        array $headers,
    ): bool {
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control
        // Even though no-cache actually permits caching,
        // we don't because the required validation is as
        // much work for us as just regenerating the page.
        foreach ( $headers as $header ) {
            if ( stripos( $header, 'Cache-Control:' ) === 0 ) {
                $value = substr( $header, strlen( 'Cache-Control:' ) );
                $parts = explode( ',', $value );
                $parts = array_map( 'trim', $parts );
                $parts = array_map( 'strtolower', $parts );
                $disallowed = [ 'no-cache', 'no-store', 'private' ];
                if ( array_intersect( $disallowed, $parts ) ) {
                    return false;
                }
            }
        }

        // If no headers prohibit caching, we can allow it.
        return true;
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

        $cache_key = $method . md5( $_SERVER['REQUEST_URI'] );

        $cached = $this->cache->get( $cache_key );
        if ( $cached ) {
            $response = json_decode( $cached, true );

            header( $response['status_header'] );
            foreach ( $response['headers'] as $header ) {
                header( $header );
            }

            return $response['body'];
        }

        $headers = headers_list();

        if ( ! self::headers_should_cache( $headers ) ) {
            return false;
        }

        $response = [
            'body' => $buffer,
            'code' => $this->status_code,
            'headers' => $headers,
            'status_header' => $this->status_header,
            'uri' => $_SERVER['REQUEST_URI'],
        ];
        $this->cache->set(
            $cache_key,
            json_encode( $response )
        );

        return false;
    }
}

$static_deploy_page_cache = new StaticDeployPageCache(
    new StaticDeployFileCache(
        sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sd-cache-' . md5( $_SERVER['HTTP_HOST'] )
    )
);
$static_deploy_page_cache->capture_response();
