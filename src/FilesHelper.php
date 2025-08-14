<?php

namespace StaticDeploy;

class FilesHelper {
    /**
     * Creates a directory at path and any parent directories that
     * don't already exist. Returns true if the directory already
     * exists.
     *
     * @return bool true if the directory was created or already exists.
     *   false otherwise.
     */
    public static function createDir(
        string $directory,
    ): bool {
        if ( ! defined( 'STATIC_DEPLOY_DIRECT_FILE_ACCESS' )
        || ! STATIC_DEPLOY_DIRECT_FILE_ACCESS ) {
            return wp_mkdir_p( $directory );
        }

        // @phpcs:disable WordPress.PHP.NoSilencedErrors
        if ( @file_exists( $directory ) ) {
            // @phpcs:disable WordPress.PHP.NoSilencedErrors
            return @is_dir( $directory );
        }
        // @phpcs:disable WordPress.PHP.NoSilencedErrors
        $result = @mkdir( $directory, 0775, true );
        if ( $result ) {
            return true;
        }
        // @phpcs:disable WordPress.PHP.NoSilencedErrors
        if ( @file_exists( $directory ) ) {
            // @phpcs:disable WordPress.PHP.NoSilencedErrors
            return @is_dir( $directory );
        }
        return false;
    }

    /**
     * Recursively delete a directory
     *
     * @throws StaticDeployException
     */
    public static function deleteDirWithFiles( string $dir ): void {
        if ( is_dir( $dir ) ) {
            $dir_files = scandir( $dir );

            if ( ! $dir_files ) {
                $err = 'Trying to delete nonexistent dir: ' . $dir;
                throw WsLog::ex( $err );
            }

            $files = array_diff( $dir_files, [ '.', '..' ] );

            foreach ( $files as $file ) {
                ( is_dir( "$dir/$file" ) ) ?
                self::deleteDirWithFiles( "$dir/$file" ) :
                self::deleteFile( "$dir/$file" );
            }

            rmdir( $dir );
        }
    }

    /**
     * Delete a file.
     *
     * @param string $filename Path to the file.
     */
    public static function deleteFile( string $filename ): void {
        if ( ! defined( 'STATIC_DEPLOY_DIRECT_FILE_ACCESS' )
        || ! STATIC_DEPLOY_DIRECT_FILE_ACCESS ) {
            $result = wp_delete_file( $filename );
        } else {
            // @phpcs:disable WordPress.PHP.NoSilencedErrors
            $result = @unlink( $filename );
        }

        if ( ! $result ) {
            throw WsLog::ex( 'Failed to delete file: ' . $filename );
        }
    }

    /**
     * Returns the full path to where a static site file's contents
     * should be stored.
     */
    public static function getFilePath(
        string $base_dir,
        string $relative_path,
    ): string {
        if ( mb_substr( $relative_path, -1 ) === '/' ) {
            return $base_dir . $relative_path . 'index.html';
        }
        return $base_dir . $relative_path;
    }

    /**
     * Write the body or file contents of a PathInfo to disk
     */
    public static function writePathInfo(
        string $base_dir,
        PathInfo $path_info,
    ): void {
        $full_path = self::getFilePath( $base_dir, $path_info->path );
        $directory = dirname( $full_path );

        if ( ! is_dir( $directory ) ) {
            if ( ! self::createDir( $directory ) ) {
                WsLog::w( 'Couldn\'t make directory: ' . $directory );
            }
        }

        try {
            if ( $path_info->body ) {
                $result = file_put_contents( $full_path, $path_info->body );
            } elseif ( $path_info->filename ) {
                $result = copy( $path_info->filename, $full_path );
            } else {
                throw WsLog::ex(
                    'No contents found for PathInfo: ' . json_encode( $path_info )
                );
            }
        } catch ( \Throwable $e ) {
            if ( file_exists( $full_path ) ) {
                self::deleteFile( $full_path );
            }
            throw $e;
        }

        if ( $result === false ) {
            if ( file_exists( $full_path ) ) {
                self::deleteFile( $full_path );
            }
            throw WsLog::ex(
                'Unable to write file ' . $full_path
            );
        }
    }
}
