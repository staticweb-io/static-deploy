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

    public function processContentType( string $content_type ) : bool {
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
    ) : void {
        WsLog::l(
            'Processing crawled site.'
        );

        if ( ! is_dir( $static_site_path ) ) {
            WsLog::l(
                'No static site directory to process.'
            );

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $static_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ( $iterator as $filename => $file_object ) {
            /**
             * @var string $filename
             */

            $save_path = str_replace( $static_site_path, '', $filename );

            // copy file to ProcessedSite dir, then process it
            // this allows external processors to have their way with it
            ProcessedSite::add( $filename, $save_path );

            $file_processor = new FileProcessor();

            $file_processor->processFile( ProcessedSite::getPath() . $save_path );
            $this->processed++;
        }

        $this->complete();

        do_action( 'wp2static_post_process_complete', ProcessedSite::getPath() );
    }

    public function processIter(
        \Iterator $crawl_responses
    ) : \Iterator {
        $rewriter = new SimpleRewriter();
        $process = function ( $crawl_responses) use ( $rewriter ) {
            foreach ( $crawl_responses as $crawled ) {
                $content_type = $crawled['content_type'] ?? null;
                if ( $content_type && $this->processContentType( $content_type ) ) {
                    if ( $crawled['body'] ?? null ) {
                        $rewritten = $rewriter->rewriteFileContents( $crawled['body'] );
                        if ( $rewritten !== $file_contents ) {
                            $crawled['body'] = $rewritten;
                            unset( $crawled['content_hash'] );
                        }
                        $this->processed++;
                    } else if ( $crawled['filename'] ?? null ) {
                        $file_contents = file_get_contents( $crawled['filename'] );
                        $rewritten = $rewriter->rewriteFileContents( $file_contents );
                        if ( $rewritten !== $file_contents ) {
                            $crawled['body'] = $rewritten;
                            unset( $crawled['content_hash'] );
                        }
                        $this->processed++;
                    }
                } else {
                    $this->skipped++;
                }
                yield $crawled;
            }
        };

        return $process( $crawl_responses );
    }

    public function complete() : void {
        WsLog::l(
            "Post processing complete. $this->processed processed, $this->skipped skipped."
        );
    }
}
