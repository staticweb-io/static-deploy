<?php

namespace StaticDeploy;

/**
 * Implementation backing FilesHelper functions.
 * This direct file access rather than going through
 * WordPress functions.
 */

class FilesHelperImpl {
    /**
     * Deletes a file
     *
     * @param string $filename Path to the file.
     * @return bool True on success or false on failure.
     */
    public static function deleteFile( string $filename ): bool {
        return @unlink( $filename );
    }
}
