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

        $rewriter = new SimpleRewriter();
        $process = function ( $crawl_responses ) use ( $rewriter ) {
            foreach ( $crawl_responses as $crawled ) {
                $content_type = $crawled->content_type;
                if ( $content_type && $this->processContentType( $content_type ) ) {
                    if ( $crawled->body ) {
                        $rewritten = $rewriter->rewriteFileContents(
                            $this->config,
                            $crawled->body,
                        );
                        if ( $rewritten !== $crawled->body ) {
                            $crawled = $crawled->withBody( $rewritten );
                        }
                        ++$this->processed;
                    } elseif ( $crawled->filename ) {
                        $file_contents = file_get_contents( $crawled->filename );
                        $rewritten = $rewriter->rewriteFileContents(
                            $this->config,
                            $file_contents
                        );
                        if ( $rewritten !== $file_contents ) {
                            $crawled = $crawled->withBody( $rewritten );
                        }
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

    public function complete(): void {
        WsLog::l(
            "Post processing complete. $this->processed processed, $this->skipped skipped."
        );
    }
}
