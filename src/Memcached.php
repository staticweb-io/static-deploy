<?php declare(strict_types=1);

namespace StaticDeploy;

class Memcached {
    /*
     * Returns the \Memcached instance used by the
     * object cache. Returns null if the object cache
     * is not loaded or is not a \StaticDeployMemcached.
     */
    public static function getMemcached(): ?\Memcached {
        global $wp_object_cache;
        if ( class_exists( 'StaticDeployMemcached' )
        && $wp_object_cache instanceof \StaticDeployMemcached ) {
            return $wp_object_cache->mc;
        }
        return null;
    }
}
