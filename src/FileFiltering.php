<?php

namespace StaticDeploy;

class FileFiltering {

    /**
     * @var array<FileIgnorePattern>
     * Files and directories to ignore
     */
    private $patterns_to_ignore;

    public function __construct() {
        $this->patterns_to_ignore = [];

        $paths_to_ignore = Options::getLineDelimitedBlobValue( 'pathsToIgnore' );

        foreach ( $paths_to_ignore as $path_pattern ) {
            $this->patterns_to_ignore[] = new FileIgnorePattern( $path_pattern );
        }
    }

    /**
     * Returns crawlable files in a given directory, recursively.
     */
    public function crawlableFiles(
        string $directory,
    ): \Iterator {
        $site_root = SiteInfo::getPath( 'site' );
        $abs_base_dir = ( new \SplFileInfo( $site_root ) )->getPathname();

        $dir_iter = new \RecursiveDirectoryIterator(
            $directory,
            \FilesystemIterator::FOLLOW_SYMLINKS | \FilesystemIterator::SKIP_DOTS,
        );

        // Using a callback filter is more efficient than
        // filtering later because we avoid recursing into
        // blocked directories.
        $filter_iter = new \RecursiveCallbackFilterIterator(
            $dir_iter,
            // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
            function ( $current, $key, $iterator ) use ( $abs_base_dir ) {
                // Filter out both directories and files
                foreach ( $this->patterns_to_ignore as $pattern ) {
                    if ( $pattern->matches( $abs_base_dir, $current ) ) {
                        return false;
                    }
                }

                if ( $current->isLink() ) {
                    // Filter out broken links
                    if ( ! $current->isReadable() ) {
                        return false;
                    }
                }

                return true;
            }
        );

        return new \RecursiveIteratorIterator( $filter_iter );
    }

    /**
     * Filters out PathInfos that don't look crawlable.
     *
     * @param \Iterator<PathInfo>
     * @return \Iterator<PathInfo>
     */
    public function filterLooksCrawlable( \Iterator $iterator ): \Iterator {
        foreach ( $iterator as $path_info ) {
            if ( $this->pathLooksCrawlable( $path_info->path ) ) {
                yield $path_info;
            }
        }
    }

    /**
     * Get public URLs for all files in a local directory.
     *
     * @return \Iterator<PathInfo>
     */
    public function getListOfLocalFilesByDir(
        string $dir,
    ): \Iterator {
        $site_path = SiteInfo::getPath( 'site' );

        if ( is_dir( $dir ) ) {
            $iterator = $this->crawlableFiles( $dir );

            foreach ( $iterator as $filename => $file_object ) {
                $url = str_replace( $site_path, '/', $filename );

                yield new PathInfo(
                    $url,
                    filename: $filename,
                );
            }
        }
    }

    /**
     * @return bool  True if the given path does not match an ignore pattern
     */
    public function pathLooksCrawlable(
        string $path,
    ): bool {
        foreach ( $this->patterns_to_ignore as $pattern ) {
            if ( $pattern->matchesPath( $path ) ) {
                return false;
            }
        }

        return true;
    }
}
