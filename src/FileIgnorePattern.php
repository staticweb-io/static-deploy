<?php

namespace StaticDeploy;

use PHLAK\Splat\Anchors;
use PHLAK\Splat\Pattern;

class FileIgnorePattern {
    /**
     * @var bool
     * Match only directories and not files.
     */
    private $only_directories;

    /**
     * @var string
     * Regex tested against relative URLs.
     */
    private $url_regex;

    /**
     * Following gitignore rules:
     * - Patterns ending in / match only directories.
     * - Patterns with a / at the start or middle match
     *   from the site root only.
     *   - Other patterns can match starting from any subdirectory.
     *
     * Splat handles the rest of the logic of turning glob
     * patterns into regexes.
     */
    public function __construct(
        string $pattern,
    ) {
        if ( substr( $pattern, -1 ) === '/' ) {
            $this->only_directories = true;
            $pattern = substr( $pattern, 0, -1 );
        } else {
            $this->only_directories = false;
        }

        if ( strpos( $pattern, '/' ) === false ) {
            $pattern = '**/' . $pattern;
        } elseif ( substr( $pattern, 0, 1 ) !== '/' ) {
            $pattern = '/' . $pattern;
        }
        Pattern::make( $pattern )->toRegex( Anchors::BOTH );

        // URL paths can match with or without trailing /
        // We add a .* to make directory patterns also match
        // child paths
        $url_regex = Pattern::make( $pattern )->toRegex( Anchors::START );
        $this->url_regex = substr( $url_regex, 0, -1 ) . '(/.*)?$#i';
    }

    public function matches(
        string $abs_base_dir,
        \SplFileInfo $file,
    ): bool {
        if ( $this->only_directories && ! $file->isDir() ) {
            return false;
        }

        $path = $file->getPathname();
        $path = Utils::strReplaceFirst( $abs_base_dir, '', $path );

        return self::matchesPath( $path );
    }

    public function matchesPath(
        string $path,
    ): bool {
        if ( preg_match( $this->url_regex, $path ) ) {
            if ( STATIC_DEPLOY_DEBUG ) {
                WsLog::d( "Ignoring $path with regex $this->url_regex" );
            }
            return true;
        }

        return false;
    }
}
