<?php

namespace StaticDeploy;

class FilesHelper {

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
                unlink( "$dir/$file" );
            }

            rmdir( $dir );
        }
    }

    /**
     * Returns the full path to where a PathInfo's contents should
     * be stored.
     */
    public static function getPathInfoFilePath(
        string $base_dir,
        PathInfo $path_info,
    ): string {
        $file_path = $path_info->path;
        if ( mb_substr( $file_path, -1 ) === '/' ) {
            return $base_dir . $file_path . 'index.html';
        }
        return $base_dir . $file_path;
    }

    /**
     * Write the body or file contents of a PathInfo to disk
     */
    public static function writePathInfo(
        string $base_dir,
        PathInfo $path_info,
    ): void {
        $full_path = self::getPathInfoFilePath( $base_dir, $path_info );
        $directory = dirname( $full_path );

        if ( ! is_dir( $directory ) ) {
            if ( ! wp_mkdir_p( $directory ) ) {
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
                unlink( $full_path );
            }
            throw $e;
        }

        if ( $result === false ) {
            if ( file_exists( $full_path ) ) {
                unlink( $full_path );
            }
            throw WsLog::ex(
                'Unable to write file ' . $full_path
            );
        }
    }
}
