<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetches GET /config/get and merges with local codes (WordPress fetch_config_with_fallback parity).
 */
class ConfigResolver
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResolvedUrls $resolvedUrls,
        private readonly FirestoreMapper $firestoreMapper,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function getMergedSettings(int $websiteId, int $storeId): array
    {
        $stored = $this->readStoredLayer($websiteId, $storeId);
        $stored['resolved_search_base'] = $this->resolvedUrls->getSearchUrl($websiteId);
        $stored['resolved_backend_base'] = $this->resolvedUrls->getBackendUrl($websiteId);

        $configCode = trim((string) ($stored['config_code'] ?? ''));
        $widgetCode = trim((string) ($stored['widget_code'] ?? ''));
        if ($configCode === '' && $widgetCode === '') {
            return $stored;
        }

        $cacheKey = Config::CACHE_KEY_PREFIX . hash('sha256', $websiteId . '|' . $configCode . '|' . $widgetCode);
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            try {
                $payload = $this->json->unserialize($cached);
                if (is_array($payload) && isset($payload['config']) && is_array($payload['config'])) {
                    return $this->finalizeMerged($payload['config'], $stored, $websiteId);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('SSAI: invalid config cache: ' . $e->getMessage());
            }
        }

        $raw = $this->fetchFromRemote($websiteId, $configCode, $widgetCode);
        if ($raw !== null) {
            $this->cache->save(
                $this->json->serialize(['config' => $raw, 'ts' => time()]),
                $cacheKey,
                [Config::CACHE_TAG],
                Config::CONFIG_CACHE_TTL
            );
            return $this->finalizeMerged($raw, $stored, $websiteId);
        }

        if ($cached) {
            try {
                $payload = $this->json->unserialize($cached);
                if (is_array($payload) && isset($payload['config']) && is_array($payload['config'])) {
                    $this->logger->warning('SSAI: using stale config cache after fetch failure');
                    return $this->finalizeMerged($payload['config'], $stored, $websiteId);
                }
            } catch (\Throwable) {
            }
        }

        $this->logger->warning('SSAI: using stored Magento settings as config fallback');
        return $stored;
    }

    /**
     * @param array<string,mixed> $firestoreRaw
     * @param array<string,mixed> $stored
     * @return array<string,mixed>
     */
    private function finalizeMerged(array $firestoreRaw, array $stored, int $websiteId): array
    {
        $codesOnly = [
            'config_code' => $stored['config_code'] ?? '',
            'widget_code' => $stored['widget_code'] ?? '',
        ];
        $mapped = $this->firestoreMapper->mapFirestoreToSettings($firestoreRaw, $codesOnly);
        $merged = array_merge($stored, $mapped);
        if (($merged['customer_id'] ?? '') === '' && ($stored['customer_id'] ?? '') !== '') {
            $merged['customer_id'] = (string) $stored['customer_id'];
        }
        $merged['resolved_search_base'] = $this->resolvedUrls->getSearchUrl($websiteId);
        $merged['resolved_backend_base'] = $this->resolvedUrls->getBackendUrl($websiteId);
        return $merged;
    }

    /**
     * @return array<string,mixed>
     */
    private function readStoredLayer(int $websiteId, int $storeId): array
    {
        $w = $websiteId;
        $s = $storeId;
        $g = fn (string $path, string $scope, $scopeId) => (string) $this->scopeConfig->getValue($path, $scope, $scopeId);

        return [
            'config_code' => $g(Config::XML_PATH_SYNC_CONFIG_CODE, ScopeInterface::SCOPE_WEBSITES, $w),
            'widget_code' => $g(Config::XML_PATH_SYNC_WIDGET_CODE, ScopeInterface::SCOPE_WEBSITES, $w),
            'customer_id' => $g(Config::XML_PATH_GENERAL_CUSTOMER_ID, ScopeInterface::SCOPE_WEBSITES, $w),
            'namespace' => $g(Config::XML_PATH_GENERAL_NAMESPACE, ScopeInterface::SCOPE_WEBSITES, $w),
            'search_selector_json' => $g(Config::XML_PATH_SELECTORS_SEARCH_JSON, ScopeInterface::SCOPE_STORE, $s),
            'results_page_selector' => $g(Config::XML_PATH_SELECTORS_RESULTS_PAGE, ScopeInterface::SCOPE_STORE, $s),
            'auto_inject_overview' => $this->scopeConfig->isSetFlag(
                Config::XML_PATH_OVERVIEW_AUTO_INJECT,
                ScopeInterface::SCOPE_STORE,
                $s
            ),
            'overview_target_selector' => $g(Config::XML_PATH_OVERVIEW_TARGET, ScopeInterface::SCOPE_STORE, $s),
            'overview_container_max_width' => $g(Config::XML_PATH_OVERVIEW_CONTAINER_MAX_WIDTH, ScopeInterface::SCOPE_STORE, $s),
            'overview_product_card_max_width' => $g(Config::XML_PATH_OVERVIEW_PRODUCT_CARD_MAX_WIDTH, ScopeInterface::SCOPE_STORE, $s),
            'live_search' => $this->scopeConfig->isSetFlag(
                Config::XML_PATH_BEHAVIOR_LIVE_SEARCH,
                ScopeInterface::SCOPE_STORE,
                $s
            ),
            'position' => $g(Config::XML_PATH_BEHAVIOR_POSITION, ScopeInterface::SCOPE_STORE, $s),
            'primary_color' => $g(Config::XML_PATH_STYLING_PRIMARY_COLOR, ScopeInterface::SCOPE_STORE, $s),
            'result_bg_color' => $g(Config::XML_PATH_STYLING_RESULT_BG, ScopeInterface::SCOPE_STORE, $s),
            'result_text_color' => $g(Config::XML_PATH_STYLING_RESULT_TEXT, ScopeInterface::SCOPE_STORE, $s),
            'result_font_family' => $g(Config::XML_PATH_STYLING_RESULT_FONT_FAMILY, ScopeInterface::SCOPE_STORE, $s),
            'result_font_size' => $g(Config::XML_PATH_STYLING_RESULT_FONT_SIZE, ScopeInterface::SCOPE_STORE, $s),
            'result_border_radius' => $g(Config::XML_PATH_STYLING_RESULT_BORDER_RADIUS, ScopeInterface::SCOPE_STORE, $s),
            'result_border_width' => $g(Config::XML_PATH_STYLING_RESULT_BORDER_WIDTH, ScopeInterface::SCOPE_STORE, $s),
            'result_border_color' => $g(Config::XML_PATH_STYLING_RESULT_BORDER_COLOR, ScopeInterface::SCOPE_STORE, $s),
            'result_box_shadow' => $g(Config::XML_PATH_STYLING_RESULT_BOX_SHADOW, ScopeInterface::SCOPE_STORE, $s),
            'show_ai_emoji' => $this->scopeConfig->isSetFlag(
                Config::XML_PATH_STYLING_SHOW_AI_EMOJI,
                ScopeInterface::SCOPE_STORE,
                $s
            ),
            'ai_emoji_char' => $g(Config::XML_PATH_STYLING_AI_EMOJI_CHAR, ScopeInterface::SCOPE_STORE, $s),
            'powered_by_prominence' => $g(Config::XML_PATH_STYLING_POWERED_BY, ScopeInterface::SCOPE_STORE, $s),
            'feedback_url' => $g(Config::XML_PATH_FEEDBACK_URL, ScopeInterface::SCOPE_STORE, $s),
        ];
    }

    private function fetchFromRemote(int $websiteId, string $configCode, string $widgetCode): ?array
    {
        $base = $this->resolvedUrls->getBackendUrl($websiteId);
        $query = $configCode !== '' ? ['config_code' => $configCode] : ['widget_code' => $widgetCode];
        $url = $base . '/config/get?' . http_build_query($query);

        try {
            $this->curl->setTimeout(15);
            $this->curl->get($url);
            $status = (int) $this->curl->getStatus();
            $body = (string) $this->curl->getBody();
            if ($status !== 200) {
                $this->logger->error("SSAI: config/get HTTP {$status} for {$url}");
                return null;
            }
            $data = json_decode($body, true);
            if (!is_array($data) || empty($data['success']) || empty($data['config']) || !is_array($data['config'])) {
                $this->logger->error('SSAI: config/get invalid JSON structure');
                return null;
            }
            return $data['config'];
        } catch (\Throwable $e) {
            $this->logger->error('SSAI: config/get exception: ' . $e->getMessage());
            return null;
        }
    }
}
