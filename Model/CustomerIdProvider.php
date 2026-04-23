<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Model;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CustomerIdProvider
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function getForWebsite(int $websiteId): string
    {
        $id = (string) $this->scopeConfig->getValue(
            Config::XML_PATH_GENERAL_CUSTOMER_ID,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
        return trim($id);
    }

    public function ensureGeneratedForWebsite(int $websiteId): string
    {
        $existing = $this->getForWebsite($websiteId);
        if ($existing !== '') {
            return $existing;
        }
        $generated = 'cust_' . $this->randomAlphanumeric(12);
        $this->configWriter->save(
            Config::XML_PATH_GENERAL_CUSTOMER_ID,
            $generated,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
        return $generated;
    }

    private function randomAlphanumeric(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }
}
