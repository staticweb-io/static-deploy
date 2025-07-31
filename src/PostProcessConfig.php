<?php declare(strict_types=1);

namespace StaticDeploy;

final class PostProcessConfig {

    public readonly ?string $destination_url;
    public readonly ?array $hosts_to_rewrite;
    public readonly ?string $site_url;

    public function __construct() {
        if ( Options::getValue( 'skipURLRewrite' ) === '1' ) {
            $this->destination_url = null;
            $this->hosts_to_rewrite = null;
            $this->site_url = null;
            return;
        }

        $this->destination_url = apply_filters(
            Controller::getHookName( 'set_destination_url' ),
            Options::getValue( 'deploymentURL' )
        );
        $this->hosts_to_rewrite = Options::getLineDelimitedBlobValue( 'hostsToRewrite' );
        $this->site_url = apply_filters(
            Controller::getHookName( 'set_wordpress_site_url' ),
            untrailingslashit( SiteInfo::getUrl( 'site' ) )
        );
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        $arr = [];

        if ( $this->destination_url ) {
            $arr['destination_url'] = $this->destination_url;
        }

        if ( $this->hosts_to_rewrite ) {
            $arr['hosts_to_rewrite'] = $this->hosts_to_rewrite;
        }

        if ( $this->site_url ) {
            $arr['site_url'] = $this->site_url;
        }

        return $arr;
    }
}
