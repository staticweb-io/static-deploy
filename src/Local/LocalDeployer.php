<?php

namespace StaticDeploy\Local;

use StaticDeploy\CrawledFiles;
use StaticDeploy\DeployerTrait;
use StaticDeploy\SiteInfo;
use StaticDeploy\WsLog;

class LocalDeployer {

    use DeployerTrait;

    const DEFAULT_NAMESPACE = 'static-deploy-addon-local/default';

    /**
     * @var integer
     */
    private $deployed_ct = 0;

    /**
     * @var integer
     */
    private $deploy_cache_ct = 0;

    /**
     * @var integer
     */
    private $deploy_error_ct = 0;

    public function __construct() {
    }

    public static function getDeployerSlug(): string {
        return 'static-deploy-addon-local';
    }

    public static function getDeployerData(): array {
        return [
            'description' => 'Deploys to a local directory',
            'name' => 'Local Deployment',
            'url' => 'https://github.com/staticweb-io/static-deploy',
        ];
    }

    /**
     * @param \Iterator<PathInfo> $path_infos
     */
    public function uploadFilesIter( \Iterator $path_infos ): void {
        $dir_path = LocalOptions::getValue( 'dirPath' );
        // Make $out_dir absolute
        if ( empty( $dir_path ) || $dir_path[0] !== '/' ) {
            $out_dir = SiteInfo::getPath( 'site' ) . $dir_path;
        } else {
            $out_dir = $dir_path;
        }
        if ( ! is_dir( $out_dir ) ) {
            mkdir( $out_dir, 0774, true );
        }
        $out_dir = realpath( $out_dir );
        $out_dir = trailingslashit( $out_dir );

        // Deployment inside the WP path is just too
        // error-prone to allow.
        // It's very easy to overwrite WordPress files
        // or to have files duplicated by crawling the
        // deployment directory.
        $site_dir = SiteInfo::getPath( 'site' );
        $site_dir = trailingslashit( realpath( $site_dir ) );

        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Site dir: ' . $site_dir );
        }

        if ( mb_strpos( $out_dir, $site_dir ) === 0 ) {
            throw WsLog::ex(
                'Local deployment directory must be outside of the WordPress directory: ' . $out_dir
            );
        }

        WsLog::l( 'Deploying to ' . $out_dir );

        $last_log_time = microtime( true );

        foreach ( $path_infos as $path_info ) {
            $now = microtime( true );
            $total = $this->deployed_ct + $this->deploy_cache_ct + $this->deploy_error_ct;
            if ( $total > 0 && $now - $last_log_time >= 60 ) {
                WsLog::l( 'Deployed ' . $path_info->path );
                $notice = "Deploy progress: $this->deployed_ct deployed," .
                    " $this->deploy_error_ct failed," .
                    " $this->deploy_cache_ct skipped (cached).";
                WsLog::l( $notice );
                $last_log_time = microtime( true );
            }

            $cache_key = $path_info->path;

            // Remove 404s
            if ( $path_info->status === 404 ) {
                $out_path = $out_dir . '/' . ltrim( $cache_key, '/' );
                if ( is_file( $out_path ) ) {
                    unlink( $out_path );
                }
                ++$this->deployed_ct;
                continue;
            }

            // Determine output path and ensure directory exists
            $out_path = $out_dir . '/' . ltrim( $cache_key, '/' );
            if ( mb_substr( $out_path, -1 ) === '/' ) {
                $out_path .= 'index.html';
            }
            $out_dirname = dirname( $out_path );
            if ( ! is_dir( $out_dirname ) ) {
                mkdir( $out_dirname, 0774, true );
            }

            // Write file contents
            if ( $path_info->body !== null ) {
                $result = file_put_contents( $out_path, $path_info->body );
            } elseif ( $path_info->filename && is_file( $path_info->filename ) ) {
                $result = copy( $path_info->filename, $out_path );
            } else {
                WsLog::l( 'No content to write for ' . $cache_key );
                ++$this->deploy_error_ct;
                continue;
            }

            if ( $result === false ) {
                WsLog::l( 'Failed to write file ' . $out_path );
                ++$this->deploy_error_ct;
            } else {
                ++$this->deployed_ct;
            }
        }

        $notice = "Deployed $this->deployed_ct files," .
            " $this->deploy_error_ct failed," .
            " $this->deploy_cache_ct skipped (cached).";
        WsLog::l( $notice );
    }
}
