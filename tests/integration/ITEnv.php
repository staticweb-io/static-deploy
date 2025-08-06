<?php declare(strict_types=1);

namespace StaticDeploy;

/**
 * Integration test environment helpers
 */
class ITEnv {
    private static string $wordpress_dir;

    public static function getPluginsDir(): string {
        return self::getWordPressDir() . '/wp-content/plugins';
    }

    /**
     * Return directory used for adding test content
     * files.
     */
    public static function getTestContentDir(): string {
        $dir = self::getWordPressDir() . self::getTestContentPath();
        mkdir( $dir, 0775, true );
        return $dir;
    }

    /**
     * Return relative URL path where test content files
     * should appear on the site.
     */
    public static function getTestContentPath(): string {
        return '/wp-content/tstcontent';
    }

    public static function getWordPressDir(): string
    {
        if ( ! isset( self::$wordpress_dir ) ) {
            $wordpress_dir = getenv( 'WORDPRESS_DIR' );
            if ( ! $wordpress_dir ) {
                throw new \RuntimeException( 'WORDPRESS_DIR environment variable not set' );
            }
            self::$wordpress_dir = rtrim( $wordpress_dir, '/' );
        }
        return self::$wordpress_dir;
    }

    public static function getLocalDeployDir(): string {
        $dir = self::getWordPressDir() . '/../localdeploy';
        mkdir( $dir, 0775, true );
        return realpath( $dir );
    }
}
