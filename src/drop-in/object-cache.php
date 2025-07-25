<?php
/**
 * Plugin Name:       Static Deploy Object Cache for Memcached (Drop-in)
 * Plugin URI:        https://github.com/staticweb-io/static-deploy
 * Description:       Object caching for Memcached.
 * Version:           9.2.1
 * Author:            StaticWeb.io
 * Author URI:        https://github.com/staticweb-io/static-deploy
 * Text Domain:       static-deploy
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * License URI:       https://github.com/staticweb-io/static-deploy/blob/develop/LICENSE
 * License:           Unlicense
 */

// phpcs:disable Squiz.PHP.DiscouragedFunctions
// Allow discouraged functions so we can use error_log here.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO

declare(strict_types=1);

if ( ! class_exists( 'Memcached' ) ) {
    wp_using_ext_object_cache( false );
    return;
}

if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
    define( 'WP_CACHE_KEY_SALT', '' );
}

if ( ! defined( 'STATIC_DEPLOY_MEMCACHED_PERSISTENT_ID' ) ) {
    define( 'STATIC_DEPLOY_MEMCACHED_PERSISTENT_ID', 'sd-mc' );
}

class StaticDeployMemcached {
    private Memcached $mc;

    // Arrays of group_name => true
    private array $global_groups;
    private array $non_persistent_groups;

    public function __construct(
        string $persistent_id,
        array $servers,
        string $cache_key_salt = '',
    ) {
        $this->cache_key_salt = $cache_key_salt;
        $this->global_groups = [];
        $this->non_persistent_groups = [];

        // https://www.php.net/manual/en/memcached.construct.php
        $mc = new Memcached( $persistent_id );

        // Since the Memcached instance persists across
        // requests, we must take care not to add servers
        // that are already in the list.
        if ( empty( $mc->getServerList() ) ) {
            $mc->addServers( $servers );
        }

        $this->mc = $mc;
    }

    private function can_add(): bool {
        if ( wp_suspend_cache_addition() ) {
            return false;
        }

        return true;
    }

    private function cache_key(
        int|string $key,
        string $group,
    ): string {
        if ( $key === '' ) {
            throw new Exception( 'Cache key cannot be empty' );
        }

        // For compatibility with WordPress
        if ( $group === '' ) {
            $group = 'default';
        }

        return $this->cache_key_salt . $group . ':' . $key;
    }

    /**
     * Adds data to the cache only if the key is not
     * already present in the cache.
     */
    public function add(
        int|string $key,
        mixed $data,
        string $group = '',
        int $expire = 0,
    ): bool {
        if ( ! $this->can_add() ) {
            return false;
        }

        $k = $this->cache_key( $key, $group );
        return $this->mc->add( $k, $data, $expire );
    }

    public function add_global_groups(
        array $groups,
    ): void {
        $this->global_groups = array_merge(
            $this->global_groups,
            array_fill_keys( $groups, true ),
        );
    }

    public function add_non_persistent_groups(
        array $groups,
    ): void {
        $this->non_persistent_groups = array_merge(
            $this->non_persistent_groups,
            array_fill_keys( $groups, true ),
        );
    }

    /**
     * Returns data from the cache, if present.
     *
     * Returns false if the key is not found. Note that
     * false could also be a value that was found in the
     * cache. In order to distinguish between these
     * circumstances, the $found parameter is set to true
     * if the key was found, false otherwise.
     *
     * $force is unused since we have no local cache.
     */
    public function get(
        int|string $key,
        string $group = '',
        bool $force = false,
        ?bool &$found = null
    ): mixed {
        $k = $this->cache_key( $key, $group );
        $data = $this->mc->get( $k );
        $found = $this->mc->getResultCode() === Memcached::RES_SUCCESS;
        return $data;
    }

    /**
     * Adds data to the cache, overwriting any existing data.
     */
    public function set(
        int|string $key,
        mixed $data,
        string $group = '',
        int $expire = 0,
    ): bool {
        if ( ! $this->can_add() ) {
            return false;
        }

        $k = $this->cache_key( $key, $group );
        return $this->mc->set( $k, $data, $expire );
    }
}

global $memcached_servers;

if ( ! isset( $memcached_servers ) ) {
    $memcached_servers = [
        [
            '127.0.0.1',
            11211,
        ],
    ];
}

global $wp_object_cache;
// phpcs:ignore WordPress.WP.GlobalVariablesOverride
$wp_object_cache = new StaticDeployMemcached(
    STATIC_DEPLOY_MEMCACHED_PERSISTENT_ID,
    $memcached_servers,
    WP_CACHE_KEY_SALT,
);

function wp_cache_add(
    int|string $key,
    mixed $data,
    string $group = '',
    int $expire = 0,
): bool {
    global $wp_object_cache;
    return $wp_object_cache->add( $key, $data, $group, $expire );
}

function wp_cache_add_global_groups(
    string|array $groups,
): void {
    global $wp_object_cache;
    $groups = (array) $groups;
    $wp_object_cache->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups(
    string|array $groups,
): void {
    global $wp_object_cache;
    $groups = (array) $groups;
    $wp_object_cache->add_non_persistent_groups( $groups );
}

function wp_cache_close(): true {
    return true;
}

function wp_cache_get(
    int|string $key,
    string $group = '',
    bool $force = false,
    ?bool &$found = null
): mixed {
    global $wp_object_cache;
    return $wp_object_cache->get( $key, $group, $force, $found );
}

function wp_cache_set(
    int|string $key,
    mixed $data,
    string $group = '',
    int $expire = 0,
): bool {
    global $wp_object_cache;
    return $wp_object_cache->set( $key, $data, $group, $expire );
}

wp_using_ext_object_cache( true );
