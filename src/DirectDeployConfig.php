<?php declare(strict_types=1);

namespace StaticDeploy;

final readonly class DirectDeployConfig {

    public function __construct(
        public ?CrawlConfig $crawl_config = new CrawlConfig(),
        public bool $do_detect = true,
    ) {}

    /**
     * @return array<string, \StaticDeploy\CrawlConfig|bool|null>
     */
    public function toArray(): array
    {
        return [
            'crawl_config' => $this->crawl_config,
            'do_detect' => $this->do_detect,
        ];
    }
}
