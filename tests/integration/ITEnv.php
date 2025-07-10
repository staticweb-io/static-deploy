<?php declare(strict_types=1);

namespace WP2Static;

use PHPUnit\Framework\TestCase;

/**
 * Integration test environment helpers
 */
class ITEnv {
    private static string $wordpressDir;

    final private function __construct() { }

    public static function getWordPressDir(): string
    {
        if ( ! isset( self::$wordpressDir ) ) {
            $wordpressDir = getenv( 'WORDPRESS_DIR' );
            if ( ! $wordpressDir ) {
                throw new \RuntimeException( 'WORDPRESS_DIR environment variable not set' );
            }
            self::$wordpressDir = rtrim( $wordpressDir, '/' );
        }
        return self::$wordpressDir;
    }
}
