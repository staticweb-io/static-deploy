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
    private $use_crawl_cache;

    public function __construct() {
        $deployer = Addons::getDeployer();

        if ( ! $deployer ) {
            WsLog::l( 'No deployment add-ons are enabled, skipping direct deployment.' );
            return;
        }

        $deployer_class = apply_filters( 'wp2static_deployer_class', '', $deployer );
        
        if ( empty( $deployer_class ) ) {
            WsLog::l( 'No deployer class found, skipping direct deployment.' );
            return;
        }

        $this->deployer = new $deployer_class();
        $this->crawler = new Crawler();
        $this->processor = new PostProcessor();

        $this->use_crawl_cache = CoreOptions::getValue( 'useCrawlCaching' );

        WsLog::l( ( $this->use_crawl_cache ? 'Using' : 'Not using' ) . ' CrawlCache.' );
    }

    public function deploy() : void {
        $detected = URLDetector::detectURLsIter();
        $added = CrawlQueue::withPathsIter( $detected );
        $this->deployPaths( $added );
    }

    public function deployComplete() : void {
        $this->crawler->crawlComplete();

        WsLog::l( 'Starting post-direct deployment actions' );
        do_action( 'wp2static_post_direct_deploy_trigger', $this );
    }

    public function deployPaths( \Iterator $paths ) : void {
        $crawled = $this->crawler->crawlIter( $paths );

        if ( $this->use_crawl_cache ) {
            $crawled = CrawlCache::addPathsIter( $crawled );
        }

        $processed = $this->processor->processIter( $crawled );
        $this->deployer->uploadFilesIter( $processed );
    }
}
