<?php declare(strict_types=1);

namespace StaticDeploy;

final class PostProcessConfig {

    public readonly ?string $destination_url;

    public readonly ?array $hosts_to_rewrite;

    public readonly ?array $replacement_patterns;

    public readonly ?string $site_url;

    public function __construct() {
        if ( Options::getValue( 'skipURLRewrite' ) === '1' ) {
            $this->destination_url = null;
            $this->hosts_to_rewrite = null;
            $this->replacement_patterns = null;
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

        $wordpress_site_url = untrailingslashit( $this->site_url );
        $destination_url = untrailingslashit( $this->destination_url );
        $destination_url_c = addcslashes( $destination_url, '/' );
        $destination_url_rel = URLHelper::getProtocolRelativeURL( $destination_url );
        $destination_url_rel_c = addcslashes( $destination_url_rel, '/' );

        $replacement_patterns = [
            $wordpress_site_url => $destination_url,
            URLHelper::getProtocolRelativeURL( $wordpress_site_url ) =>
                URLHelper::getProtocolRelativeURL( $destination_url ),
            addcslashes( URLHelper::getProtocolRelativeURL( $wordpress_site_url ), '/' ) =>
                addcslashes( URLHelper::getProtocolRelativeURL( $destination_url ), '/' ),
        ];

        foreach ( $this->hosts_to_rewrite as $host_to_rewrite ) {
            if ( $host_to_rewrite ) {
                $host_rel = URLHelper::getProtocolRelativeURL( 'http://' . $host_to_rewrite );
                $host_rel_c = addcslashes( $host_rel, '/' );

                $replacement_patterns[ 'http:' . $host_rel ] = $destination_url;
                $replacement_patterns[ 'https:' . $host_rel ] = $destination_url;
                $replacement_patterns[ $host_rel ] = $destination_url_rel;
                $replacement_patterns[ 'http:' . $host_rel_c ] = $destination_url_c;
                $replacement_patterns[ 'https:' . $host_rel_c ] = $destination_url_c;
                $replacement_patterns[ addcslashes( $host_rel, '/' ) ] = $destination_url_rel_c;
            }
        }

        $this->replacement_patterns = $replacement_patterns;

        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::l( 'PostProcessConfig: ' . json_encode( $this->toArray() ) );
        }
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

        if ( $this->replacement_patterns ) {
            $arr['replacement_patterns'] = $this->replacement_patterns;
        }

        if ( $this->site_url ) {
            $arr['site_url'] = $this->site_url;
        }

        return $arr;
    }
}
