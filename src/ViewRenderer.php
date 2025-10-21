<?php

namespace StaticDeploy;

class ViewRenderer {

    public static function renderOptionsPage(): void {
        Options::init();

        $view = [
            'options' => Options::getAll(),
            'nonce_action' => Controller::getHookName( 'ui_options' ),
        ];

        require_once STATIC_DEPLOY_PATH . 'views/options-page.php';
    }

    public static function renderAdvancedOptionsPage(): void {
        Options::init();

        $view = [
            'options' => Options::getAll(),
            'nonce_action' => Controller::getHookName( 'ui_advanced_options' ),
        ];

        require_once STATIC_DEPLOY_PATH . 'views/advanced-options-page.php';
    }

    public static function renderDiagnosticsPage(): void {
        $view = [];
        $view['memoryLimit'] = ini_get( 'memory_limit' );
        $view['options'] = array_values( Options::getAll() );
        $view['site_info'] = SiteInfo::getAllInfo();
        $view['phpOutOfDate'] = PHP_VERSION_ID < 80100;
        $view['uploadsWritable'] = SiteInfo::isUploadsWritable();
        $view['maxExecutionTime'] = intval( ini_get( 'max_execution_time' ) );
        $view['curlSupported'] = SiteInfo::hasCURLSupport();
        $view['permalinksAreCompatible'] = SiteInfo::permalinksAreCompatible();
        $view['domDocumentAvailable'] = class_exists( 'DOMDocument' );
        $view['extensions'] = get_loaded_extensions();

        $mc = Memcached::getMemcached();
        if ( $mc instanceof \Memcached ) {
            $view['memcachedStats'] = $mc->getStats();
        }

        require_once STATIC_DEPLOY_PATH . 'views/diagnostics-page.php';
    }

    public static function renderLogsPage(): void {
        $view = [];
        $view['nonce_action'] = Controller::getHookName( 'log_page' );
        $view['logs'] = WsLog::getAll();

        require_once STATIC_DEPLOY_PATH . 'views/logs-page.php';
    }

    public static function renderAddonsPage(): void {
        $view = [];
        $view['nonce_action'] = Controller::getHookName( 'addons_page' );
        $view['addons'] = Addons::getAll();

        require_once STATIC_DEPLOY_PATH . 'views/addons-page.php';
    }

    public static function renderDetectedFiles(): void {
        if ( ! is_admin() ) {
            http_response_code( 403 );
            die( 'Forbidden' );
        }

        $action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_URL );
        /**
         * @var string[] $url_id
         */
        $url_id = filter_input( INPUT_GET, 'id', FILTER_SANITIZE_URL );

        if ( $action === 'remove' && is_array( $url_id ) ) {
            DetectedFiles::rmUrlsById( $url_id );
        }

        $urls = iterator_to_array( DetectedFiles::getCrawlablePaths() );
        // Apply search
        $search_term = strval( filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL ) );
        if ( $search_term !== '' ) {
            $urls = array_filter(
                $urls,
                fn( $url ): bool => stripos( $url, $search_term ) !== false
            );
        }

        $page_size = 200;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for pagination, no nonce needed
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $paginator = new Paginator( $urls, $page_size, $page );
        $view = [
            'paginatorFirstPage' => $paginator->firstPage(),
            'paginatorLastPage' => $paginator->lastPage(),
            'paginatorPage' => $paginator->page(),
            'paginatorRecords' => $paginator->records(),
            'paginatorTotalRecords' => $paginator->totalRecords(),
        ];

        require_once STATIC_DEPLOY_PATH . 'views/detected-files-page.php';
    }

    public static function renderCrawledFiles(): void {
        if ( ! is_admin() ) {
            http_response_code( 403 );
            die( 'Forbidden' );
        }

        $action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_URL );
        /**
         * @var string[] $url_id
         */
        $url_id = filter_input( INPUT_GET, 'id', FILTER_SANITIZE_URL );

        if ( $action === 'remove' && is_array( $url_id ) ) {
            CrawledFiles::rmUrlsById( $url_id );
        }

        $urls = CrawledFiles::getURLs();
        // Apply search
        $search_term = strval( filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL ) );
        if ( $search_term !== '' ) {
            $urls = array_filter(
                $urls,
                fn( $url ): bool => stripos( $url->url ?? '', $search_term ) !== false
            );
        }

        $page_size = 200;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for pagination, no nonce needed
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $paginator = new Paginator( $urls, $page_size, $page );
        $view = [
            'paginatorFirstPage' => $paginator->firstPage(),
            'paginatorLastPage' => $paginator->lastPage(),
            'paginatorPage' => $paginator->page(),
            'paginatorRecords' => $paginator->records(),
            'paginatorTotalRecords' => $paginator->totalRecords(),
        ];

        require_once STATIC_DEPLOY_PATH . 'views/crawled-files-page.php';
    }

    public static function renderPostProcessedSitePaths(): void {
        if ( ! is_admin() ) {
            http_response_code( 403 );
            die( 'Forbidden' );
        }

        $paths = ProcessedSite::getPaths();

        // Apply search
        $search_term = strval( filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL ) );
        if ( $search_term !== '' ) {
            $paths = array_filter(
                $paths,
                fn( $path ): bool => stripos( $path, $search_term ) !== false
            );
        }

        $page_size = 200;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for pagination, no nonce needed
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $paginator = new Paginator( $paths, $page_size, $page );
        $view = [
            'paginatorFirstPage' => $paginator->firstPage(),
            'paginatorLastPage' => $paginator->lastPage(),
            'paginatorPage' => $paginator->page(),
            'paginatorRecords' => $paginator->records(),
            'paginatorTotalRecords' => $paginator->totalRecords(),
        ];

        require_once STATIC_DEPLOY_PATH . 'views/post-processed-site-paths-page.php';
    }

    public static function renderStaticSitePaths(): void {
        if ( ! is_admin() ) {
            http_response_code( 403 );
            die( 'Forbidden' );
        }

        $paths = StaticSite::getPaths();

        // Apply search
        $search_term = strval( filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL ) );
        if ( $search_term !== '' ) {
            $paths = array_filter(
                $paths,
                fn( $path ): bool => stripos( $path, $search_term ) !== false
            );
        }

        $page_size = 200;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for pagination, no nonce needed
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $paginator = new Paginator( $paths, $page_size, $page );
        $view = [
            'paginatorFirstPage' => $paginator->firstPage(),
            'paginatorLastPage' => $paginator->lastPage(),
            'paginatorPage' => $paginator->page(),
            'paginatorRecords' => $paginator->records(),
            'paginatorTotalRecords' => $paginator->totalRecords(),
        ];

        require_once STATIC_DEPLOY_PATH . 'views/static-site-paths-page.php';
    }

    public static function renderDeployCache(): void {
        if ( ! is_admin() ) {
            http_response_code( 403 );
            die( 'Forbidden' );
        }

        $deploy_namespace = strval(
            filter_input(
                INPUT_GET,
                'deploy_namespace',
                FILTER_SANITIZE_URL,
            )
        );
        $paths = $deploy_namespace !== ''
            ? DeployCache::getPaths( $deploy_namespace )
            : DeployCache::getPaths();

        // Apply search
        $search_term = strval( filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL ) );
        if ( $search_term !== '' ) {
            $paths = array_filter(
                $paths,
                fn( $path ): bool => stripos( $path, $search_term ) !== false
            );
        }

        $page_size = 200;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for pagination, no nonce needed
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $paginator = new Paginator( $paths, $page_size, $page );
        $view = [
            'paginatorFirstPage' => $paginator->firstPage(),
            'paginatorLastPage' => $paginator->lastPage(),
            'paginatorPage' => $paginator->page(),
            'paginatorRecords' => $paginator->records(),
            'paginatorTotalRecords' => $paginator->totalRecords(),
        ];

        require_once STATIC_DEPLOY_PATH . 'views/deploy-cache-page.php';
    }

    public static function renderJobsPage(): void {
        Options::init();
        JobQueue::markFailedJobs();
        JobQueue::squashQueue();

        $view = [];
        $view['nonce_action'] = Controller::getHookName( 'ui_job_options' );
        $view['jobs'] = JobQueue::getJobs();
        $view['jobOptions'] = Options::getAll();

        $view = apply_filters(
            Controller::getHookName( 'render_jobs_page_vars' ),
            $view
        );

        require_once STATIC_DEPLOY_PATH . 'views/jobs-page.php';
    }

    public static function renderRunPage(): void {
        $view = [];

        require_once STATIC_DEPLOY_PATH . 'views/run-page.php';
    }


    public static function renderCachesPage(): void {
        $view = [];

        // performance check vs map
        $disk_space = 0;

        $exported_site_dir = StaticSite::getPath();
        if ( is_dir( $exported_site_dir ) ) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $exported_site_dir
                )
            );

            foreach ( $files as $file ) {
                /**
                 * @var \SplFileInfo $file
                 */
                $disk_space += $file->getSize();
            }
        }

        $view['exportedSiteDiskSpace'] = sprintf( '%4.2f MB', $disk_space / 1048576 );
        // end check

        if ( is_dir( $exported_site_dir ) ) {
            $view['exportedSiteFileCount'] = iterator_count(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $exported_site_dir,
                        \FilesystemIterator::SKIP_DOTS
                    )
                )
            );
        } else {
            $view['exportedSiteFileCount'] = 0;
        }

        // performance check vs map
        $disk_space = 0;
        $processed_site_dir = ProcessedSite::getPath();

        if ( is_dir( $processed_site_dir ) ) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $processed_site_dir
                )
            );

            foreach ( $files as $file ) {
                /**
                 * @var \SplFileInfo $file
                 */
                $disk_space += $file->getSize();
            }
        }

        $view['processedSiteDiskSpace'] = sprintf( '%4.2f MB', $disk_space / 1048576 );
        // end check

        if ( is_dir( $processed_site_dir ) ) {
            $view['processedSiteFileCount'] = iterator_count(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $processed_site_dir,
                        \FilesystemIterator::SKIP_DOTS
                    )
                )
            );
        } else {
            $view['processedSiteFileCount'] = 0;
        }

        $view['DetectedFilesTotal'] = DetectedFiles::getTotal();
        $view['crawledFilesTotal'] = CrawledFiles::getTotal();
        $view['deployCacheTotalPaths'] = DeployCache::getTotal();
        $view['uploads_path'] = SiteInfo::getPath( 'uploads' );
        $view['nonce_action'] = Controller::getHookName( 'caches_page' );

        require_once STATIC_DEPLOY_PATH . 'views/caches-page.php';
    }
}
