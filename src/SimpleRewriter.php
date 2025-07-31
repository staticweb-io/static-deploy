<?php
/*
    SimpleRewriter

    A processed version of a StaticSite, with URLs rewritten, folders renamed
    and other modifications made to prepare it for a Deployer
*/

namespace StaticDeploy;

class SimpleRewriter {
    /**
     * Rewrite URLs in a string to destination_url
     *
     * @param string $file_contents
     * @return string
     */
    public function rewriteFileContents(
        PostProcessConfig $config,
        string $file_contents,
    ): string {
        // TODO: allow empty file saving here? Exception for style.css
        if ( ! $file_contents ) {
            return '';
        }

        $wordpress_site_url = untrailingslashit( $config->site_url );
        $destination_url = untrailingslashit( $config->destination_url );
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

        foreach ( $config->hosts_to_rewrite as $host ) {
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
