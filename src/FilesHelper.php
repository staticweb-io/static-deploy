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
}
