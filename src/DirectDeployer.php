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
        $this->deployPaths( URLDetector::detectURLsIter() );
    }

    public function deployPaths( \Iterator $paths ) : void {
        $crawled = $this->crawler->crawlIter( $paths );
        $processed = $this->processor->processIter( $crawled );
        $this->deployer->uploadFilesIter( $processed );

        $this->crawler->crawlComplete();
    }
}
