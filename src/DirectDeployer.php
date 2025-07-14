<?php
/*
    DirectDeployer

    Crawls, processes and deploys files all in one pass.
*/

namespace WP2Static;

class DirectDeployer {
    private $crawler;
    private $deployer;
    private $processor;
    private $url_discovery;

    public function __construct() {
        $deployer = Addons::getDeployer();

        if ( ! $deployer ) {
            WsLog::l( 'No deployment add-ons are enabled, skipping direct deployment.' );
            return;
        }

        $deployer_class = apply_filters(
            Controller::getHookName( 'deployer_class' ),
            '',
            $deployer
        );

        if ( empty( $deployer_class ) ) {
            WsLog::l( 'No deployer class found, skipping direct deployment.' );
            return;
        }

        $this->deployer = new $deployer_class();
        $this->crawler = new Crawler();
        $this->url_discovery = new URLDiscovery();
        $this->processor = new PostProcessor();
    }

    public function deploy(): void {
        global $wpdb;

        $queue_table = CrawlQueue::getTableName();
        $last_now = $wpdb->get_var( 'SELECT NOW()' );

        $detected = URLDetector::detectURLsIter();
        $added = CrawlQueue::withPathsIter( $detected );
        $this->deployPaths( $added );

        while ( true ) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM $queue_table WHERE detected_at > %s",
                $last_now
            );
            $new_ct = intval( $wpdb->get_var( $sql ) );
            if ( 0 === $new_ct ) {
                break;
            }
            WsLog::l( "Found $new_ct new URLs during crawling." );
            $detected = CrawlQueue::getPathsIter( $last_now );
            $last_now = $wpdb->get_var( 'SELECT NOW()' );
            $added = CrawlQueue::withPathsIter( $detected );
            $this->deployPaths( $added, false );
        }
    }

    public function deployComplete(): void {
        $this->crawler->crawlComplete();
        $this->processor->complete();

        WsLog::l( 'Starting post-direct deployment actions' );
        do_action(
            Controller::getHookName( 'post_direct_deploy_trigger' ),
            $this
        );
    }

    public function deployPaths( \Iterator $paths, bool $remove_404s = true ): void {
        $crawled = $this->crawler->crawlIter( $paths );
        if ( $remove_404s ) {
            $crawled = CrawlCache::remove404s( $crawled );
        }
        $crawled = CrawlCache::addPathsIter( $crawled );
        $crawled = $this->url_discovery->discoverURLs( $crawled );

        $processed = $this->processor->processIter( $crawled );
        $this->deployer->uploadFilesIter( $processed );
    }
}
