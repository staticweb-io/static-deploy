<?php declare(strict_types=1);

namespace StaticDeploy;

class Memcached {
    /*
     * Returns the \Memcached instance used by the
     * object cache. Returns null if the object cache
     * is not loaded or is not managed by this plugin.
     *
     * @param bool $required If true, throw an exception
     * if unable to find a \Memcached instance.
     */
    public static function getMemcached(
        $required = false,
    ): ?\Memcached {
        global $wp_object_cache;
        if ( class_exists( 'StaticDeployMemcached' )
        && $wp_object_cache instanceof \StaticDeployMemcached ) {
            return $wp_object_cache->mc;
        }
        if ( $required ) {
            throw WsLog::ex( 'Memcached is not enabled or is not managed by this plugin.' );
        }
        return null;
    }
}
