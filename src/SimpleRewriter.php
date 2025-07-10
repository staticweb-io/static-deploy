<?php
/*
    SimpleRewriter

    A processed version of a StaticSite, with URLs rewritten, folders renamed
    and other modifications made to prepare it for a Deployer
*/

namespace WP2Static;

class SimpleRewriter {

    /**
     * @var string
     */
    private $destination_url;

    /**
     * @var array
     */
    private $hosts_to_rewrite;

    /**
     * @var string
     */
    private $site_url;

    /**
     * @var boolean
     */
    private $skip_url_rewrite;

    public function __construct() {
        $this->destination_url = apply_filters(
            Controller::getHookName( 'set_destination_url' ),
            CoreOptions::getValue( 'deploymentURL' )
        );
        $this->hosts_to_rewrite = CoreOptions::getLineDelimitedBlobValue( 'hostsToRewrite' );
        $this->site_url = apply_filters(
            Controller::getHookName( 'set_wordpress_site_url' ),
            untrailingslashit( SiteInfo::getUrl( 'site' ) )
        );
        $url_rewrite = (int) CoreOptions::getValue( 'skipURLRewrite' );
        $this->skip_url_rewrite = $url_rewrite === 1 ? true : false;
    }

    /**
     * Rewrite URLs in file to destination_url
     *
     * @param string $filename file to rewrite URLs in
     * @throws WP2StaticException
     */
    public static function rewrite( string $filename ): void {
        $rewriter = new SimpleRewriter();

        $file_contents = file_get_contents( $filename );

        if ( $file_contents === false ) {
            $file_contents = '';
        } else {
            $rewritten_contents = $rewriter->rewriteFileContents( $file_contents );
        }

        file_put_contents( $filename, $rewritten_contents );
    }

    /**
     * Rewrite URLs in a string to destination_url
     *
     * @param string $file_contents
     * @return string
     */
    public function rewriteFileContents( string $file_contents ): string
    {
        // TODO: allow empty file saving here? Exception for style.css
        if ( ! $file_contents ) {
            return '';
        }

        if ( $this->skip_url_rewrite ) {
            return $file_contents;
        }

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

        foreach ( $this->hosts_to_rewrite as $host ) {
            if ( $host ) {
                $host_rel = URLHelper::getProtocolRelativeURL( 'http://' . $host );
                $host_rel_c = addcslashes( $host_rel, '/' );

                $replacement_patterns[ 'http:' . $host_rel ] = $destination_url;
                $replacement_patterns[ 'https:' . $host_rel ] = $destination_url;
                $replacement_patterns[ $host_rel ] = $destination_url_rel;
                $replacement_patterns[ 'http:' . $host_rel_c ] = $destination_url_c;
                $replacement_patterns[ 'https:' . $host_rel_c ] = $destination_url_c;
                $replacement_patterns[ addcslashes( $host_rel, '/' ) ] = $destination_url_rel_c;
            }
        }

        $rewritten_contents = strtr(
            $file_contents,
            $replacement_patterns
        );

        return $rewritten_contents;
    }
}
