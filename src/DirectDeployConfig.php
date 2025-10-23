<?php declare(strict_types=1);

namespace StaticDeploy;

final class DirectDeployConfig {

    public function __construct(
        public readonly ?CrawlConfig $crawl_config = new CrawlConfig(),
        public readonly bool $do_detect = true,
    ) {}

    public function toArray(): array
    {
        return [
            'crawl_config' => $this->crawl_config,
            'do_detect' => $this->do_detect,
        ];
    }
}
