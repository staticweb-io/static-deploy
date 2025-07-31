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

        $rewritten_contents = strtr(
            $file_contents,
            $config->replacement_patterns
        );

        return $rewritten_contents;
    }
}
