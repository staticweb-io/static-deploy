<?php
/*
    PostProcessor

    Processes each file in StaticSite, saving to ProcessedSite
*/

namespace StaticDeploy;

class PostProcessor {
    public PostProcessConfig $config;
    private int $processed = 0;
    private int $skipped = 0;

    /**
     * PostProcessor constructor
     */
    public function __construct() {
        $this->config = new PostProcessConfig();
    }

    public function processContentType( string $content_type ): bool {
        if ( str_starts_with( $content_type, 'text/html' ) ||
            str_starts_with( $content_type, 'text/css' ) ||
            str_starts_with( $content_type, 'application/xml' ) ||
            str_starts_with( $content_type, 'text/plain' ) ||
            str_starts_with( $content_type, 'application/javascript' ) ) {
            return true;
        }
        return false;
    }

    /**
     * Process StaticSite
     *
     * Iterates on each file, not directory
     *
     * @param string $static_site_path Static site path
     * @throws StaticDeployException
     */
    public function processStaticSite(
        string $static_site_path
    ): void {
        WsLog::l(
            'Processing crawled site.'
        );

        if ( ! is_dir( $static_site_path ) ) {
            WsLog::w( 'No static site directory to process.' );

            return;
        }

        $crawled = CrawledFiles::getPathsIter();
        $processed = $this->processIter( $crawled );

        foreach ( $processed as $path ) {
            $save_path = StaticSite::transformPath( $path->path );
            if ( $path->body ) {
                ProcessedSite::add( $save_path, $path->body );
                ++$this->processed;
            } elseif ( $path->filename ) {
                ProcessedSite::copy( $save_path, $path->filename );
                ++$this->skipped;
            } else {
                WsLog::w(
                    'No contents found for crawled path: ' . json_encode( $path )
                );
                ++$this->skipped;
            }
        }

        $this->complete();

        do_action(
            Controller::getHookName( 'post_process_complete' ),
            ProcessedSite::getPath()
        );
    }

    /**
     * @param \Iterator<PathInfo>
     * @return \Iterator<PathInfo>
     */
    public function processIter(
        \Iterator $crawl_responses
    ): \Iterator {
        if ( ! $this->config->replacement_patterns ) {
            return $crawl_responses;
        }

        $process = function ( $crawl_responses ) {
            foreach ( $crawl_responses as $crawled ) {
                $content_type = $crawled->content_type;
                if ( $content_type && $this->processContentType( $content_type ) ) {
                    if ( $crawled->body || $crawled->filename ) {
                        $crawled = $this->rewriteFileContents( $crawled );
                        ++$this->processed;
                    }
                } else {
                    ++$this->skipped;
                }
                yield $crawled;
            }
        };

        return $process( $crawl_responses );
    }

    /**
     * Rewrite strings in the body of a PathInfo
     * according to $this->config->replacement_patterns
     */
    public function rewriteFileContents(
        PathInfo $path_info
    ): PathInfo {
        if ( $path_info->body !== null ) {
            $s = $path_info->body;
        } elseif ( $path_info->filename ) {
            $s = file_get_contents( $path_info->filename );
            if ( $s === false ) {
                throw WsLog::ex( 'Error reading file ' . $path_info->filename );
            }
        }

        $rewritten = strtr(
            $s,
            $this->config->replacement_patterns
        );

        if ( $rewritten !== $s ) {
            return $path_info->withBody( $rewritten );
        }

        return $path_info;
    }

    public function complete(): void {
        WsLog::l(
            "Post processing complete. $this->processed processed, $this->skipped skipped."
        );
    }
}
