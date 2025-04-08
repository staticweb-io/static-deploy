<?php
/*
    DirectDeployer

    Crawls, processes and deploys files all in one pass.
*/

namespace WP2Static;

class DirectDeployer {
    public function deploy() : void {
        $this->deployPaths( URLDetector::detectURLsIter() );
    }

    public function deployPaths( \Iterator $paths ) : void {
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

        $deployer = new $deployer_class();

        $crawler = new Crawler();
        $processor = new PostProcessor();

        $crawled = $crawler->crawlIter( $paths );
        $processed = $processor->processIter( $crawled );
        $deployer->uploadFilesIter( $processed );
    }
}