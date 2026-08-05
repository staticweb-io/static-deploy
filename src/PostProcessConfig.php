<?php declare(strict_types=1);

namespace StaticDeploy;

final readonly class PostProcessConfig {

    public ?string $destination_url;

    public ?array $hosts_to_rewrite;

    /**
     * A regular expression matching the port on any port-less host in
     * the hostsToRewrite option, used to strip the port before replacement,
     * or null when there are no rewrite targets.
     * Only used when the rewriteHostPorts option is enabled.
     */
    public ?string $port_pattern;

    public ?array $replacement_patterns;

    public ?string $site_url;

    public function __construct() {
        if ( Options::getValue( 'skipURLRewrite' ) === '1' ) {
            $this->destination_url = null;
            $this->hosts_to_rewrite = null;
            $this->port_pattern = null;
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

        $strip_host_ports = Options::getValue( 'rewriteHostPorts' ) === '1';

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
        $this->port_pattern = $strip_host_ports
            ? $this->buildPortPattern( $this->hosts_to_rewrite )
            : null;

        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::l( 'PostProcessConfig: ' . json_encode( $this->toArray() ) );
        }
    }

    /**
     * Build a regular expression that matches the `:port` immediately
     * following any of the hosts to rewrite when the host appears in a URL,
     * i.e. preceded by `//` or an escaped `\/\/`. The host itself is captured
     * in group 1 so the match can be replaced with `$1`, dropping only the
     * port.
     *
     * Empty hosts, and hosts that already include a port, are skipped: ported
     * hosts keep their exact-match behaviour.
     *
     * Returns null when there are no hosts to build a pattern for.
     *
     * @param string[] $hosts_to_rewrite
     */
    private function buildPortPattern( array $hosts_to_rewrite ): ?string {
        $quoted_hosts = [];
        foreach ( $hosts_to_rewrite as $host ) {
            if ( $host && ! preg_match( '/:\d+$/', $host ) ) {
                $quoted_hosts[] = preg_quote( $host, '#' );
            }
        }

        if ( $quoted_hosts === [] ) {
            return null;
        }

        // (?:\\?/){2} matches `//` as well as the backslash-escaped `\/\/`
        // form found in JSON-encoded URLs.
        return '#((?:\\\\?/){2}(?:' . implode( '|', $quoted_hosts ) . ')):\d+#';
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

        if ( $this->port_pattern ) {
            $arr['port_pattern'] = $this->port_pattern;
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
