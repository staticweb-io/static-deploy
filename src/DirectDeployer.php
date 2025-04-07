<?php
/*
    DirectDeployer

    Crawls, processes and deploys files all in one pass.
*/

namespace WP2Static;

class DirectDeployer {
    public function deploy() {
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

        $urls = URLDetector::detectURLsIter();
        $crawled = $crawler->crawlIter( $urls );
        $processed = $processor->processIter( $crawled );
        $deployer->uploadFilesIter( $processed );
    }
}