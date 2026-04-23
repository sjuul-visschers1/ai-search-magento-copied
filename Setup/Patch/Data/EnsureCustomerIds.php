<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;
use Springbok\SiteSearchAi\Model\CustomerIdProvider;

class EnsureCustomerIds implements DataPatchInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerIdProvider $customerIdProvider
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        foreach ($this->storeManager->getWebsites() as $website) {
            $this->customerIdProvider->ensureGeneratedForWebsite((int) $website->getId());
        }
        return $this;
    }
}
