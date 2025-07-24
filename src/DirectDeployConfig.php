<?php declare(strict_types=1);

namespace StaticDeploy;

final class DirectDeployConfig {

    public readonly CrawlConfig $crawl_config;

    public function __construct(
        ?CrawlConfig $crawl_config = null,
    ) {
        $this->crawl_config = $crawl_config ?? new CrawlConfig();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'crawl_config' => $this->crawl_config,
        ];
    }
}
