<?php declare(strict_types=1);
// phpcs:disable Generic.Files.OneObjectStructurePerFile

class StaticDeployPageCacheResponse {
    public ?string $blob;
    public int $code;
    public array $headers;
    public string $status_header;
    public string $uri;

    public function __construct(
        ?string $blob,
        int $code,
        array $headers,
        string $status_header,
        string $uri,
    ) {
        $this->blob = $blob;
        $this->code = $code;
        $this->headers = $headers;
        $this->status_header = $status_header;
        $this->uri = $uri;
    }

    public static function from_array(
        array $arr
    ): self {
        return new self(
            $arr['blob'] ?? null,
            $arr['code'],
            $arr['headers'],
            $arr['status_header'],
            $arr['uri'],
        );
    }

    public function to_array(): array {
        $arr = [
            'code' => $this->code,
            'headers' => $this->headers,
            'status_header' => $this->status_header,
            'uri' => $this->uri,
        ];

        if ( $this->blob ) {
            $arr['blob'] = $this->blob;
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
    public function get( string $key ): ?string;
    public function set( string $key, string $value ): void;
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

    public static function write_response(
        string $status_header,
        int $code,
        ?array $headers,
    ): void {
        header(
            $status_header,
            true,
            $code,
        );

        if ( $headers ) {
            foreach ( $headers as $header ) {
                header( $header[0] . ': ' . $header[1] );
            }
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

        $cached = $this->cache->get( $cache_key );
        if ( $cached ) {
            $request_headers = array_change_key_case( getallheaders() );
            $response = json_decode( $cached, true );

            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/If-None-Match
            $if_none_match = $request_headers['if-none-match'] ?? null;
            if ( $if_none_match ) {
                $etag = $response['headers']['etag'][1];
                foreach ( explode( ',', $if_none_match ) as $match_etag ) {
                    if ( $etag === trim( $match_etag ) ) {
                        $this->write_response(
                            'HTTP/1.1 304 Not Modified',
                            304,
                            $response['headers'],
                        );
                        return true;
                    }
                }
            }

            $this->write_response(
                $response['status_header'],
                $response['code'],
                $response['headers'],
            );

            // Send no body in responses to HEAD requests
            if ( $method === 'HEAD' ) {
                return true;
            } else {
                return $response['body'];
            }
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

        if ( ! isset( $this->headers['etag'] ) ) {
            $this->headers['etag'] = [
                'ETag',
                '"' . md5( $buffer ) . '"',
            ];
        }

        $response = [
            'body' => $buffer,
            'code' => $this->status_code,
            'headers' => $this->headers,
            'status_header' => $this->status_header,
            'uri' => $_SERVER['REQUEST_URI'],
        ];
        $this->cache->set(
            $cache_key,
            json_encode( $response )
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
