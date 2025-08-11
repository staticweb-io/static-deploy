<?php declare(strict_types=1);

namespace StaticDeploy;

final class DirectDeployConfig {

    public readonly CrawlConfig $crawl_config;
    public readonly bool $do_detect;

    public function __construct(
        ?CrawlConfig $crawl_config = null,
        ?bool $do_detect = true,
    ) {
        $this->crawl_config = $crawl_config ?? new CrawlConfig();
        $this->do_detect = $do_detect;
    }

    public function toArray(): array
    {
        return [
            'crawl_config' => $this->crawl_config,
            'do_detect' => $this->do_detect,
        ];
    }
}
