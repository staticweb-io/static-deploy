<?php declare(strict_types=1);

namespace StaticDeploy;

final class CrawlConfig {

    public readonly ?string $path_hash_prefix;

    public function __construct() {
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [];
    }
}
