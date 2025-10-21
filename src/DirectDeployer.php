<?php
/*
    DirectDeployer

    Crawls, processes and deploys files all in one pass.
*/

namespace StaticDeploy;

class DirectDeployer {
    private readonly Crawler $crawler;
    private readonly object $deployer;
    public DirectDeployConfig $config;
    private readonly PostProcessor $processor;
    public bool $ready = false;
    private readonly URLDiscovery $url_discovery;

    public function __construct(
        ?DirectDeployConfig $config = null,
    ) {
        $this->config = $config ?? new DirectDeployConfig();
        $deployer = Addons::getDeployer();

        if ( ! $deployer ) {
            WsLog::w( 'No deployment add-ons are enabled, skipping direct deployment.' );
            return;
        }

        $deployer_class = apply_filters(
            Controller::getHookName( 'deployer_class' ),
            '',
            $deployer
        );

        if ( empty( $deployer_class ) ) {
            WsLog::w( 'No deployer class found, skipping direct deployment.' );
            return;
        }

        $this->deployer = new $deployer_class();
        $this->crawler = new Crawler( $this->config->crawl_config );
        $this->url_discovery = new URLDiscovery();
        $this->processor = new PostProcessor();
        $this->ready = true;
    }

    public function deploy(): void {
        global $wpdb;

        $queue_table = DetectedFiles::getTableName();
        $last_now = Db::now();

        if ( $this->config->do_detect ) {
            $detected = URLDetector::detectURLsIter();
            $detected = DetectedFiles::withPathsIter( $detected );
        } else {
            $detected = DetectedFiles::getPathsIter();
        }

        $this->deployPaths( $detected );

        while ( true ) {
            $new_ct = intval(
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->get_var(
                    $wpdb->prepare(
                        'SELECT COUNT(*) FROM %i WHERE detected_at > %s',
                        $queue_table,
                        $last_now,
                    ),
                ),
            );
            if ( 0 === $new_ct ) {
                break;
            }
            WsLog::l( "Found {$new_ct} new URLs during crawling." );
            $detected = DetectedFiles::getPathsIter( $last_now );
            $last_now = Db::now();
            $added = DetectedFiles::withPathsIter( $detected );
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

    /**
     * Deploy specific paths
     *
     * @param \Iterator<PathInfo> $paths
     */
    public function deployPaths(
        \Iterator $path_infos,
        bool $remove_404s = true,
    ): void {
        $filtering = new FileFiltering();
        $path_infos = $filtering->filterLooksCrawlable( $path_infos );
        $crawled = $this->crawler->crawlIter( $path_infos );
        if ( $remove_404s ) {
            $crawled = CrawledFiles::removeOutdated( $crawled );
        }
        $crawled = CrawledFiles::addPathsIter( $crawled );
        $crawled = $this->url_discovery->discoverURLs( $crawled );

        $pis = $this->processor->processIter( $crawled );
        $pis = DeployCache::addCacheData( $pis );
        $this->deployer->uploadFilesIter( $pis );
    }
}
