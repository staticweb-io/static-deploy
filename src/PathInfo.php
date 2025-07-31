<?php

namespace StaticDeploy;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriNormalizer;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use Psr\Http\Message\UriInterface;

class PathInfo {
    // Values known upon detection.

    // If the path corresponds to a file on disk,
    // this will be the absolute path to the file.
    public ?string $filename;

    // The relative URL path, not containing the host.
    // E.g., "/author/user/"
    public readonly string $path;

    // Values not known until crawling.
    public ?string $body;
    public ?string $content_type;
    public ?string $redirect_to;
    public ?int $status;

    private ?string $content_hash;

    public function __construct(
        string|UriInterface $path,
        ?string $filename = null,
        ?string $body = null,
        ?string $content_hash = null,
        ?string $content_type = null,
        ?string $redirect_to = null,
        ?int $status = null,
    ) {
        $uri = Psr7Utils::uriFor( $path );
        $msg = self::pathErrorMessage( $uri );
        if ( $msg ) {
            throw WsLog::ex( "$msg: $path" );
        }

        if ( $filename === '' ) {
            $this->filename = null;
        } else {
            $this->filename = $filename;
        }

        $this->path = $path;
        $this->body = $body;
        $this->content_type = $content_type;
        $this->redirect_to = $redirect_to;
        $this->status = $status;

        if ( $content_hash !== null ) {
            $this->content_hash = $content_hash;
        }
    }

    /**
     * Check if a path is valid for use in a PathInfo object.
     *
     * Returns error message if any, or false if there are no errors.
     */
    public static function pathErrorMessage(
        UriInterface $uri,
    ): false|string {
        if ( ! Uri::isAbsolutePathReference( $uri ) ) {
            return 'Not an absolute path reference';
        }

        if ( $uri->getQuery() !== '' ) {
            return 'Path cannot contain query string';
        }

        if ( $uri->getFragment() !== '' ) {
            return 'Path cannot contain fragment';
        }

        return false;
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

        if ( isset( $this->body ) ) {
            $this->content_hash = md5( $this->body );
        }

        if ( isset( $this->filename ) ) {
            $this->content_hash = md5_file( $this->filename );
        }

        return $this->content_hash;
    }

    public function withBody( ?string $new_body ): self {
        $copy = clone $this;
        $copy->body = $new_body;
        $copy->content_hash = null;
        $copy->filename = null;
        return $copy;
    }
}
