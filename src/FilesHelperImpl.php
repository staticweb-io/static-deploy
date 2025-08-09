<?php

namespace StaticDeploy;

/**
 * Implementation backing FilesHelper functions.
 * This uses WordPress functions to perform file operations.
 * Can be swapped out during build for another implementaton such
 * as doing direct file access.
 */

class FilesHelperImpl {
    /**
     * Deletes a file
     *
     * @param string $filename Path to the file.
     * @return bool True on success or false on failure.
     */
    public static function deleteFile( string $filename ): bool {
        return wp_delete_file( $filename );
    }
}
