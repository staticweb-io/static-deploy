<?php
/*
    PostProcessor

    Processes each file in StaticSite, saving to ProcessedSite
*/

namespace WP2Static;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class PostProcessor {
    private int $processed = 0;
    private int $skipped = 0;

    /**
     * PostProcessor constructor
     */
    public function __construct() {
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
     * @throws WP2StaticException
     */
    public function processStaticSite(
        string $static_site_path
    ): void {
        WsLog::l(
            'Processing crawled site.'
        );

        if ( ! is_dir( $static_site_path ) ) {
            WsLog::l(
                'No static site directory to process.'
            );

            return;
        }

        $crawled = CrawledFiles::getPathsIter();
        $processed = $this->processIter( $crawled );

        foreach ( $processed as $path ) {
            $save_path = StaticSite::transformPath( $path['path'] );
            if ( $path['body'] ?? null ) {
                ProcessedSite::add( $save_path, $path['body'] );
                ++$this->processed;
            } elseif ( $path['filename'] ?? null ) {
                ProcessedSite::copy( $save_path, $path['filename'] );
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

    public function processIter(
        \Iterator $crawl_responses
    ): \Iterator {
        $rewriter = new SimpleRewriter();
        $process = function ( $crawl_responses ) use ( $rewriter ) {
            foreach ( $crawl_responses as $crawled ) {
                $content_type = $crawled['content_type'] ?? null;
                if ( $content_type && $this->processContentType( $content_type ) ) {
                    if ( $crawled['body'] ?? null ) {
                        $rewritten = $rewriter->rewriteFileContents( $crawled['body'] );
                        if ( $rewritten !== $file_contents ) {
                            $crawled['body'] = $rewritten;
                            unset( $crawled['content_hash'] );
                        }
                        ++$this->processed;
                    } elseif ( $crawled['filename'] ?? null ) {
                        $file_contents = file_get_contents( $crawled['filename'] );
                        $rewritten = $rewriter->rewriteFileContents( $file_contents );
                        if ( $rewritten !== $file_contents ) {
                            $crawled['body'] = $rewritten;
                            unset( $crawled['content_hash'] );
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
