<?php

namespace StaticDeploy;

class PathInfo {
    // If the path corresponds to a file on disk,
    // this will be the absolute path to the file.
    public readonly ?string $filename;

    // The relative URL path, not containing the host.
    // E.g., "/author/user/"
    public readonly string $path;

    public function __construct(
        string $path,
        ?string $filename = null,
    ) {
        if ( strpos( $path, '/' ) !== 0 ) {
            throw WsLog::ex( 'Not a relative path: ' . $path );
        }

        if ( strpos( $path, '?' ) !== false ) {
            throw WsLog::ex( 'Path cannot contain query string: ' . $path );
        }

        $this->filename = $filename;
        $this->path = $path;
    }

    public function toArray(): array {
        $arr = [
            'path' => $this->path,
        ];
        if ( $this->filename ) {
            $arr['filename'] = $this->filename;
        }
        return $arr;
    }
}
