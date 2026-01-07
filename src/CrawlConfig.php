<?php declare(strict_types=1);

namespace StaticDeploy;

final readonly class CrawlConfig {

    public function __construct( public ?string $path_hash_prefix = null )
    {
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [
            'path_hash_prefix' => $this->path_hash_prefix,
        ];
    }
}
