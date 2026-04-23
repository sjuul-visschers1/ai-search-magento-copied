<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Plugin;

use Magento\Config\Model\Config as ConfigModel;
use Magento\Framework\App\CacheInterface;
use Springbok\SiteSearchAi\Model\Config;

class InvalidateRemoteConfigCachePlugin
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function afterSave(ConfigModel $subject, $result)
    {
        if ($subject->getSection() === 'ssai') {
            $this->cache->clean([Config::CACHE_TAG]);
        }
        return $result;
    }
}
