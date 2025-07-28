<?php

namespace StaticDeploy;

class PathInfo {
    // Values known upon detection.

    // If the path corresponds to a file on disk,
    // this will be the absolute path to the file.
    public readonly ?string $filename;

    // The relative URL path, not containing the host.
    // E.g., "/author/user/"
    public readonly string $path;

    // Values not known until crawling.
    public readonly ?string $body;
    public readonly ?string $content_type;
    public readonly ?string $redirect_to;
    public readonly ?int $status;

    private ?string $content_hash;

    public function __construct(
        string $path,
        ?string $filename = null,
        ?string $body = null,
        ?string $content_type = null,
        ?string $redirect_to = null,
        ?int $status = null,
    ) {
        if ( strpos( $path, '/' ) !== 0 ) {
            throw WsLog::ex( 'Not a relative path: ' . $path );
        }

        if ( strpos( $path, '?' ) !== false ) {
            throw WsLog::ex( 'Path cannot contain query string: ' . $path );
        }

        $this->filename = $filename;
        $this->path = $path;
        $this->body = $body;
        $this->content_type = $content_type;
        $this->redirect_to = $redirect_to;
        $this->status = $status;
    }

    public function toArray(): array {
        $arr = [
            'path' => $this->path,
        ];
        if ( isset( $this->body ) ) {
            $arr['body'] = $this->body;
        }
        if ( isset( $this->content_hash ) ) {
            $arr['content_hash'] = $this->content_hash;
        }
        if ( isset( $this->content_type ) ) {
            $arr['content_type'] = $this->content_type;
        }
        if ( isset( $this->filename ) ) {
            $arr['filename'] = $this->filename;
        }
        if ( isset( $this->redirect_to ) ) {
            $arr['redirect_to'] = $this->redirect_to;
        }
        if ( isset( $this->status ) ) {
            $arr['status'] = $this->status;
        }
        return $arr;
    }

    public function getContentHash(): ?string {
        if ( isset( $this->content_hash ) ) {
            return $this->content_hash;
        }

        if ( ! isset( $this->body ) ) {
            return null;
        }

        $hash = md5( $this->body );
        $this->content_hash = $hash;
        return $hash;
    }
}
