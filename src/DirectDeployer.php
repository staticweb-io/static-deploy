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
    }

    public function deploy() : void {
        $detected = URLDetector::detectURLsIter();
        $added = CrawlQueue::addPathsIter( $detected );
        $this->deployPaths( $added );
    }

    public function deployComplete() : void {
        $this->crawler->crawlComplete();

        WsLog::l( 'Starting post-direct deployment actions' );
        do_action( 'wp2static_post_direct_deploy_trigger', $this );
    }

    public function deployPaths( \Iterator $paths ) : void {
        $crawled = $this->crawler->crawlIter( $paths );
        $processed = $this->processor->processIter( $crawled );
        $this->deployer->uploadFilesIter( $processed );
    }
}
