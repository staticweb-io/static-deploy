<?php

namespace WP2Static;

use PHLAK\Splat\Pattern;
use WP2Static\Utils;
use WP2Static\WsLog;

class FileIgnorePattern {
    /**
     * @var bool
     * Match only directories and not files.
     */
    private $only_directories;

    /**
     * @var string
     * Regex tested against file and directory paths.
     */
    private $regex;

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

        $regex = Pattern::make( $pattern )->toRegex( Pattern::BOTH_ANCHORS );
        // Make it case-insensitive
        $this->regex = $regex . 'i';

        // WsLog::l("Created regex $this->regex for pattern $pattern");
    }

    public function matches(
        string $abs_base_dir,
        \SplFileInfo $file,
    ): bool {
        if ( $this->only_directories && ! $file->isDir() ) {
            return false;
        }

        $path = $file->getPathname();
        $path = Utils::str_replace_first( $abs_base_dir, '', $path );

        return self::matchesPath( $path );
    }

    public function matchesPath(
        string $path,
    ): bool {
        if ( preg_match( $this->regex, $path ) ) {
            WsLog::d( "Ignoring $path with regex $this->regex" );
            return true;
        }

        return false;
    }
}
