<?php declare(strict_types=1);
// phpcs:disable Generic.Files.OneObjectStructurePerFile
// phpcs:disable Squiz.PHP.DiscouragedFunctions
// Allow discouraged functions so we can use error_log here.

/**
 * Plugin Name:       Static Deploy Page Cache (Drop-in)
 * Plugin URI:        https://github.com/staticweb-io/static-deploy
 * Description:       Advanced page caching and optimization.
 * Version:           9.2.1
 * Author:            StaticWeb.io
 * Author URI:        https://github.com/staticweb-io/static-deploy
 * Text Domain:       static-deploy
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * License URI:       https://github.com/staticweb-io/static-deploy/blob/develop/LICENSE
 * License:           Unlicense
 */

class StaticDeployPageCacheResponse {
    public ?string $blob_key;
    public int $code;
    public string $hash_algo;
    public array $headers;
    public int $max_age;
    public string $status_header;
    public int $time;
    public string $uri;

    public function __construct(
        int $code,
        string $status_header,
        string $uri,
        array $headers,
        int $max_age,
        ?string $blob_key = null,
        ?int $time = null,
    ) {
        $this->blob_key = $blob_key;
        $this->code = $code;
        $this->headers = $headers;
        $this->max_age = $max_age;
        $this->status_header = $status_header;
        $this->time = $time ?? time();
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
            $arr['max_age'],
            $arr['blob_key'] ?? null,
            $arr['time'],
        );
    }

    public function to_array(): array {
        $arr = [
            'code' => $this->code,
            'headers' => $this->headers,
            'max_age' => $this->max_age,
            'status_header' => $this->status_header,
            'time' => $this->time,
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
        string $value,
        int $ttl,
    ): void;

    /*
     * Even though we can compute the key from the response,
     * we always have computed the key previously in order
     * to check for cache hits. For efficiency, we pass it in
     * rather than computing it again.
     */
    public function set_response(
        string $key,
        StaticDeployPageCacheResponse $response
    ): void;
}

class StaticDeployFileCache implements StaticDeployCacheInterface {
    public string $dir;
    public float $min_free_space;
    private string $temp_dir;

    public function __construct(
        string $dir,
        string $temp_dir,
        float $min_free_space,
    ) {
        // If the directory doesn't exist, try to create it
        if ( ! is_dir( $dir ) && ! mkdir( $dir, 0700, true ) ) {
            die( 'Failed to create cache directory' );
        }
        $this->dir = $dir;
        $this->min_free_space = $min_free_space;

        if ( ! is_dir( $temp_dir ) && ! mkdir( $temp_dir, 0700, true ) ) {
            die( 'Failed to create temp directory' );
        }
        $this->temp_dir = $temp_dir;
    }

    private function check_free_space(
        ?int $plus_bytes = 0,
    ): bool {
        $free = ( disk_free_space( $this->dir ) - $plus_bytes )
            / disk_total_space( $this->dir );
        return $free > $this->min_free_space;
    }

    /**
     * Check if there is enough free disk pace. If not,
     * attempt to free up space by randomly deleting files
     * until the minimum free space is reached or until we
     * can't delete any more files.
     *
     * Returns true if the minimum free space was reached,
     * false otherwise.
     */
    private function ensure_free_space(
        ?int $plus_bytes = 0,
    ): bool {
        $free_space_check = $this->check_free_space( $plus_bytes );
        if ( $free_space_check ) {
            return true;
        }

        $files = glob( $this->dir . '/*' );

        if ( empty( $files ) ) {
            error_log(
                'Free disk space below ' . $this->min_free_space * 100 .
                '%, but there are no files in the cache. Caching disabled.'
            );
            return false;
        } else {
            error_log(
                'Free disk space below ' . $this->min_free_space * 100 .
                '%. Deleting files from cache to free up space.'
            );
        }

        // Start off by decimating files and increase chance
        // by 10% each time.
        $delete_chance = 1;
        $files_deleted = 0;
        while ( ! $free_space_check && $delete_chance <= 10 ) {
            foreach ( $files as $file ) {
                if ( ! is_file( $file ) ) {
                    continue;
                }
                if ( random_int( 1, 10 ) <= $delete_chance ) {
                    unlink( $file );
                    ++$files_deleted;
                }
            }
            ++$delete_chance;
            $free_space_check = $this->check_free_space( $plus_bytes );
            $files = glob( $this->dir . '/*' );
        }

        if ( $free_space_check ) {
            error_log( 'Deleted ' . $files_deleted . ' files from cache.' );
        } else {
            error_log(
                'Deleted ' . $files_deleted . ' files from cache, ' .
                'but could not free up enough disk space. Caching disabled.'
            );
        }
        return $free_space_check;
    }

    public function get_response(
        string $key
    ): ?StaticDeployPageCacheResponse {
        $path = $this->dir . '/' . $key;
        if ( ! is_file( $path ) ) {
            return null;
        }
        $json = file_get_contents( $path );
        if ( $json === false ) {
            return null;
        }
        $arr = json_decode( $json, true );
        return StaticDeployPageCacheResponse::from_array( $arr );
    }

    public function get_blob(
        string $key
    ): ?string {
        $path = $this->dir . '/' . $key;
        if ( ! is_file( $path ) ) {
            return null;
        }
        $content = file_get_contents( $path );
        if ( $content === false ) {
            return null;
        }
        return $content;
    }

    public function set_blob(
        string $key,
        string $value,
        int $ttl,
    ): void {
        $temp_path = $this->temp_dir . DIRECTORY_SEPARATOR . $key . uniqid();
        $path = $this->dir . '/' . $key;
        if ( ! is_file( $path )
            && $this->ensure_free_space( strlen( $value ) )
            && file_put_contents( $temp_path, $value ) !== false ) {
            // Since writing could result in partial files,
            // we write to a temp file and move it atomically.
            rename( $temp_path, $path );
        }
    }

    public function set_response(
        string $key,
        StaticDeployPageCacheResponse $response
    ): void {
        $temp_path = $this->temp_dir . DIRECTORY_SEPARATOR . $key . uniqid();
        $path = $this->dir . '/' . $key;
        $value = json_encode( $response->to_array() );
        if ( $this->ensure_free_space( strlen( $value ) ) ) {
            if ( file_put_contents( $temp_path, $value ) !== false ) {
                // Since writing could result in partial files,
                // we write to a temp file and move it atomically.
                rename( $temp_path, $path );
            }
        } else {
            unlink( $path );
        }
    }
}

class StaticDeployTransientCache implements StaticDeployCacheInterface {
    public string $prefix;

    public function __construct(
        string $prefix,
    ) {
        $this->prefix = $prefix;
    }

    public function get_response(
        string $key
    ): ?StaticDeployPageCacheResponse {
        $result = get_transient( $this->prefix . $key );
        return $result === false ? null : $result;
    }

    public function get_blob(
        string $key
    ): ?string {
        $result = get_transient( $this->prefix . $key );
        return $result === false ? null : $result;
    }

    public function set_blob(
        string $key,
        string $value,
        int $ttl,
    ): void {
        set_transient(
            $this->prefix . $key,
            $value,
            $ttl,
        );
    }

    public function set_response(
        string $key,
        StaticDeployPageCacheResponse $response
    ): void {
        set_transient(
            $this->prefix . $key,
            $response,
            $response->max_age,
        );
    }
}

/**
 * A cache that combines two other caches, using one for
 * responses and one for blobs.
 */
class StaticDeployCombinedCache implements StaticDeployCacheInterface {
    public StaticDeployCacheInterface $blob_cache;
    public StaticDeployCacheInterface $response_cache;

    public function __construct(
        StaticDeployCacheInterface $blob_cache,
        StaticDeployCacheInterface $response_cache,
    ) {
        $this->blob_cache = $blob_cache;
        $this->response_cache = $response_cache;
    }

    public function get_response(
        string $key
    ): ?StaticDeployPageCacheResponse {
        return $this->response_cache->get_response( $key );
    }

    public function get_blob(
        string $key
    ): ?string {
        return $this->blob_cache->get_blob( $key );
    }

    public function set_blob(
        string $key,
        string $value,
        int $ttl,
    ): void {
        $this->blob_cache->set_blob( $key, $value, $ttl );
    }

    public function set_response(
        string $key,
        StaticDeployPageCacheResponse $response
    ): void {
        $this->response_cache->set_response( $key, $response );
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
    public StaticDeployCacheInterface $cache;
    private string $default_cache_control;
    public string $hash_algo;

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

    private array $headers_cache_control;

    private int $max_age;
    private int $status_code;
    private string $status_header;

    public function __construct(
        StaticDeployCacheInterface $cache,
        string $default_cache_control,
        string $hash_algo,
    ) {
        $this->cache = $cache;
        $this->default_cache_control = $default_cache_control;
        $this->hash_algo = $hash_algo;
        $this->headers = [];
    }

    public function add_get_instance_hook(): void {
        add_filter(
            'static_deploy_page_cache_get_instance',
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            function ( $instance = null ) {
                return $this;
            },
            10,
            1
        );
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
        $cc = $this->headers_cache_control;

        if ( $this->max_age === 0 ) {
            return false;
        }

        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control
        // Even though no-cache actually permits caching,
        // we don't because the required validation is as
        // much work for us as just regenerating the page.
        if ( ( $cc['no-cache'] ?? false )
        || ( $cc['no-store'] ?? false )
        || ( $cc['private'] ?? false ) ) {
            return false;
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

        if ( ! isset( $this->headers['cache-control'] ) ) {
            $this->headers['cache-control'] = [
                'Cache-Control',
                $this->default_cache_control,
            ];
        }

        $this->headers_cache_control = [];
        $header = $this->headers['cache-control'];
        $value = $header[1];
        $parts = explode( ',', $value );
        foreach ( $parts as $part ) {
            $part = explode( '=', $part, 2 );
            $name = strtolower( trim( $part[0] ) );
            $val = isset( $part[1] ) ? trim( $part[1] ) : true;
            $this->headers_cache_control[ $name ] = $val;
        }

        // Since we are a shared cache, s-maxage overrides
        // max-age.
        $max_age = $this->headers_cache_control['s-maxage']
            ?? $this->headers_cache_control['max-age']
            ?? null;
        if ( $max_age !== null ) {
            // Negative and non-integer max-ages are treated as 0
            // See https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control#response_directives
            $max_age = filter_var(
                $max_age,
                FILTER_VALIDATE_INT,
                [
                    'options' => [ 'min_range' => 0 ],
                ],
            );
            $this->max_age = $max_age === false ? 0 : $max_age;
        } else {
            $this->max_age = 0;
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

        // If user is authenticated, we can't cache.
        // WP adds no-store to all authenticated responses,
        // so there is no point in processing further.
        if ( is_user_logged_in() ) {
            return false;
        }

        $uri_hash = hash( $this->hash_algo, $_SERVER['REQUEST_URI'] );
        $cache_key = $method . $uri_hash;

        $response = $this->cache->get_response( $cache_key );

        if ( $response ) {
            $age = max( 0, ( time() - $response->time ) );

            if ( $age > $response->max_age ) {
                $response = null;
            } else {
                $response->headers['age'] = [ 'Age', $age ];
            }
        }

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

            // Send no body in responses to HEAD requests
            if ( $response->blob_key && $method === 'GET' ) {
                $blob = $this->cache->get_blob( $response->blob_key );

                // If we get a cache miss on the blob, we
                // continue on to the uncached response.
                if ( $blob !== null ) {
                    $response->write_output();
                    return $blob;
                }
            } else {
                $response->write_output();
                return true;
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

        if ( $buffer ) {
            $etag = hash( $this->hash_algo, $buffer );
            if ( ! isset( $this->headers['etag'] ) ) {
                $this->headers['etag'] = [
                    'ETag',
                    '"' . $etag . '"',
                ];
            }
            $blob_key = 'blob' . $etag;
        } else {
            $blob_key = null;
        }

        $response = new StaticDeployPageCacheResponse(
            $this->status_code,
            $this->status_header,
            $_SERVER['REQUEST_URI'],
            $this->headers,
            $this->max_age,
            $blob_key,
        );
        if ( $blob_key ) {
            $this->cache->set_blob(
                $blob_key,
                $buffer,
                $this->max_age,
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

if ( ! defined( 'STATIC_DEPLOY_PAGE_CACHE_DIR' ) ) {
    define(
        'STATIC_DEPLOY_PAGE_CACHE_DIR',
        sys_get_temp_dir() . DIRECTORY_SEPARATOR .
        'sd-cache-' . md5( WP_CACHE_KEY_SALT . $_SERVER['HTTP_HOST'] )
    );
}

if ( ! defined( 'STATIC_DEPLOY_PAGE_CACHE_TEMP_DIR' ) ) {
    define(
        'STATIC_DEPLOY_PAGE_CACHE_TEMP_DIR',
        sys_get_temp_dir() . DIRECTORY_SEPARATOR .
        'sd-cache-tmp-' . md5( WP_CACHE_KEY_SALT . $_SERVER['HTTP_HOST'] )
    );
}

if ( ! defined( 'STATIC_DEPLOY_MIN_DISK_FREE_SPACE' ) ) {
    define( 'STATIC_DEPLOY_MIN_DISK_FREE_SPACE', 0.1 );
}

if ( ! defined( 'STATIC_DEPLOY_PAGE_CACHE_PREFIX' ) ) {
    define( 'STATIC_DEPLOY_PAGE_CACHE_PREFIX', 'sd_pc_' );
}

if ( ! defined( 'STATIC_DEPLOY_PAGE_CACHE_DEFAULT_CACHE_CONTROL' ) ) {
    define( 'STATIC_DEPLOY_PAGE_CACHE_DEFAULT_CACHE_CONTROL', 'max-age=600' );
}

if ( ! defined( 'STATIC_DEPLOY_PAGE_CACHE_HASH_ALGO' ) ) {
    define( 'STATIC_DEPLOY_PAGE_CACHE_HASH_ALGO', 'sha256' );
}

$static_deploy_page_cache = new StaticDeployPageCache(
    new StaticDeployCombinedCache(
        new StaticDeployFileCache(
            STATIC_DEPLOY_PAGE_CACHE_DIR,
            STATIC_DEPLOY_PAGE_CACHE_TEMP_DIR,
            STATIC_DEPLOY_MIN_DISK_FREE_SPACE,
        ),
        new StaticDeployTransientCache(
            STATIC_DEPLOY_PAGE_CACHE_PREFIX,
        ),
    ),
    STATIC_DEPLOY_PAGE_CACHE_DEFAULT_CACHE_CONTROL,
    STATIC_DEPLOY_PAGE_CACHE_HASH_ALGO,
);
$static_deploy_page_cache->add_get_instance_hook();

// CLI code may manually load this file in order to
// access the cache, but we don't want to capture
// the output buffer in that case.
if ( ! defined( 'WP_CLI' ) ) {
    $static_deploy_page_cache->capture_response();
}
