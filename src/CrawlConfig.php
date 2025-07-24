<?php declare(strict_types=1);

namespace StaticDeploy;

final class CrawlConfig {

    public readonly ?string $path_hash_prefix;

    public function __construct(
        ?string $path_hash_prefix = null,
    ) {
        $this->path_hash_prefix = $path_hash_prefix;
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
