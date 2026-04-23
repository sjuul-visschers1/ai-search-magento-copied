<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Resolves customer-management and search API bases (never from Firestore).
 */
class ResolvedUrls
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getBackendUrl(?int $websiteId = null): string
    {
        $fromEnv = $this->trimUrl((string) (getenv('SSAI_BACKEND_URL') ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        $fromConfig = $this->trimUrl((string) $this->scopeConfig->getValue(
            Config::XML_PATH_SERVICE_BACKEND_URL,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        ));
        if ($fromConfig !== '') {
            return $fromConfig;
        }
        return $this->trimUrl(Config::DEFAULT_BACKEND_URL);
    }

    public function getSearchUrl(?int $websiteId = null): string
    {
        $fromEnv = $this->trimUrl((string) (getenv('SSAI_SEARCH_URL') ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        $fromConfig = $this->trimUrl((string) $this->scopeConfig->getValue(
            Config::XML_PATH_SERVICE_SEARCH_URL,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        ));
        if ($fromConfig !== '') {
            return $fromConfig;
        }
        return $this->trimUrl(Config::DEFAULT_SEARCH_URL);
    }

    public function isValidApiBaseForWidget(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }
        if (!preg_match('#^https://#i', $candidate)) {
            return false;
        }
        return (bool) filter_var($candidate, FILTER_VALIDATE_URL);
    }

    private function trimUrl(string $url): string
    {
        return rtrim($url, '/');
    }
}
