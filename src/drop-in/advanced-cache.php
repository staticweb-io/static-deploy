<?php declare(strict_types=1);
// phpcs:disable Generic.Files.OneObjectStructurePerFile

class StaticDeployPageCacheResponse {
    public ?string $blob_key;
    public int $code;
    public array $headers;
    public string $status_header;
    public string $uri;

    public function __construct(
        int $code,
        string $status_header,
        string $uri,
        array $headers,
        ?string $blob_key = null,
    ) {
        $this->blob_key = $blob_key;
        $this->code = $code;
        $this->headers = $headers;
        $this->status_header = $status_header;
        $this->uri = $uri;
    }

    public static function from_array(
        array $arr
    ): self {
        return new self(
            $arr['code'],
            $arr['status_header'],
            $arr['uri'],
            $arr['headers'],
            $arr['blob_key'] ?? null,
        );
    }

    public function to_array(): array {
        $arr = [
            'code' => $this->code,
            'headers' => $this->headers,
            'status_header' => $this->status_header,
            'uri' => $this->uri,
        ];

        if ( $this->blob_key ) {
            $arr['blob_key'] = $this->blob_key;
        }

        return $arr;
    }

    public function write_output(): void {
        header(
            $this->status_header,
            true,
            $this->code,
        );

        foreach ( $this->headers as $header ) {
            header( $header[0] . ': ' . $header[1] );
        }
    }
}

interface StaticDeployCacheInterface {
    public function get_response(
        string $key
    ): ?StaticDeployPageCacheResponse;

    public function get_blob(
        string $key
    ): ?string;

    public function set_blob(
        string $key,
        string $value
    ): void;

    public function set_response(
        string $key,
        StaticDeployPageCacheResponse $response
    ): void;
}

class StaticDeployFileCache implements StaticDeployCacheInterface {
    public string $dir;

    public function __construct(
        string $dir,
    ) {
        // If the directory doesn't exist, try to create it
        if ( ! is_dir( $dir ) && ! mkdir( $dir, 0700, true ) ) {
            die( 'Failed to create cache directory' );
        }
        $this->dir = $dir;
    }

    public function get_response(
        string $key
    ): ?StaticDeployPageCacheResponse {
        if ( ! file_exists( $this->dir . '/' . $key ) ) {
            return null;
        }
        $json = file_get_contents( $this->dir . '/' . $key );
        if ( $json === false ) {
            return null;
        }
        $arr = json_decode( $json, true );
        return StaticDeployPageCacheResponse::from_array( $arr );
    }

    public function get_blob(
        string $key
    ): ?string {
        $key = 'blob' . $key;
        if ( ! file_exists( $this->dir . '/' . $key ) ) {
            return null;
        }
        $content = file_get_contents( $this->dir . '/' . $key );
        if ( $content === false ) {
            return null;
        }
        return $content;
    }

    public function set_blob(
        string $key,
        string $value
    ): void {
        $key = 'blob' . $key;
        if ( ! file_exists( $this->dir . '/' . $key ) ) {
            file_put_contents( $this->dir . '/' . $key, $value );
        }
    }

    public function set_response(
        string $key,
        StaticDeployPageCacheResponse $response
    ): void {
        file_put_contents(
            $this->dir . '/' . $key,
            json_encode( $response->to_array() )
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

    /**
     * An array of headers in the format
     * [ 'content-type' => [ 'Content-Type', 'text/html' ] ]
     *
     * Created by parse_headers()
     *
     * For efficiency, we access the array directly.
     * If we were to create functions, they would need to
     * ensure that the keys were lowercased. Instead we access
     * directly via lowercased constants.
     *
     * @var array<string, array<string, string>>
     */
    private array $headers;

    private int $status_code;
    private string $status_header;

    public function __construct(
        StaticDeployCacheInterface $cache,
    ) {
        $this->cache = $cache;
        $this->headers = [];
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
    public function headers_should_cache(): bool {
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control
        // Even though no-cache actually permits caching,
        // we don't because the required validation is as
        // much work for us as just regenerating the page.
        $header = $this->headers['cache-control'] ?? null;
        if ( $header ) {
            $value = $header[1];
            $parts = explode( ',', $value );
            $parts = array_map( 'trim', $parts );
            $parts = array_map( 'strtolower', $parts );
            $disallowed = [ 'no-cache', 'no-store', 'private' ];
            if ( array_intersect( $disallowed, $parts ) ) {
                return false;
            }
        }

        // If no headers prohibit caching, we can allow it.
        // See https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Caching#heuristic_caching
        return true;
    }

    /**
     * Parse headers into an array of lowercase name to
     * an array of [ $original_name, $value ];
     * e.g. [ 'content-type' => [ 'Content-Type', 'text/html' ] ]
     */
    public function parse_headers(): void {
        foreach ( headers_list() as $header ) {
            $header = explode( ':', $header, 2 );
            $this->headers[ strtolower( $header[0] ) ] = [
                $header[0],
                trim( $header[1] ),
            ];
        }
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

        $response = $this->cache->get_response( $cache_key );
        if ( $response ) {
            $request_headers = array_change_key_case( getallheaders() );

            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/If-None-Match
            $if_none_match = $request_headers['if-none-match'] ?? null;
            if ( $if_none_match ) {
                $etag = $response->headers['etag'][1];
                foreach ( explode( ',', $if_none_match ) as $match_etag ) {
                    if ( $etag === trim( $match_etag ) ) {
                        $response->code = 304;
                        $response->status_header = 'HTTP/1.1 304 Not Modified';
                        $response->write_output();
                        return true;
                    }
                }
            }

            $response->write_output();

            // Send no body in responses to HEAD requests
            if ( $response->blob_key && $method === 'GET' ) {
                return $this->cache->get_blob( $response->blob_key );
            }
            return true;
        }

        $this->parse_headers();

        if ( ! $this->headers_should_cache() ) {
            return false;
        }

        if ( ! isset( $this->headers['content-length'] ) ) {
            $this->headers['content-length'] = [
                'Content-Length',
                strlen( $buffer ),
            ];
        }

        if ( $buffer ) {
            $blob_key = md5( $buffer );
        } else {
            $blob_key = null;
        }

        if ( ! isset( $this->headers['etag'] ) ) {
            $this->headers['etag'] = [
                'ETag',
                '"' . $blob_key . '"',
            ];
        }

        $response = new StaticDeployPageCacheResponse(
            $this->status_code,
            $this->status_header,
            $_SERVER['REQUEST_URI'],
            $this->headers,
            $blob_key,
        );
        if ( $blob_key ) {
            $this->cache->set_blob(
                $blob_key,
                $buffer
            );
        }
        $this->cache->set_response(
            $cache_key,
            $response
        );

        // Send no body in responses to HEAD requests
        return $method === 'HEAD';
    }
}

$static_deploy_page_cache = new StaticDeployPageCache(
    new StaticDeployFileCache(
        sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sd-cache-' . md5( $_SERVER['HTTP_HOST'] )
    )
);
$static_deploy_page_cache->capture_response();
