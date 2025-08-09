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
        $result = FilesHelperImpl::deleteFile( $filename );
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
