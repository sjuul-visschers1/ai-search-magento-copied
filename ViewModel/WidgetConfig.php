<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Springbok\SiteSearchAi\Model\ConfigResolver;
use Springbok\SiteSearchAi\Model\ResolvedUrls;

class WidgetConfig implements ArgumentInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly ConfigResolver $configResolver,
        private readonly ResolvedUrls $resolvedUrls,
        private readonly Json $json,
        private readonly RequestInterface $request
    ) {
    }

    public function isEnabled(): bool
    {
        $s = $this->getMerged();
        $cc = trim((string) ($s['config_code'] ?? ''));
        $wc = trim((string) ($s['widget_code'] ?? ''));
        return $cc !== '' || $wc !== '';
    }

    public function isWidgetRenderable(): bool
    {
        return $this->getSerializedWidgetConfig() !== '';
    }

    /**
     * JSON string for window.SSAI_CONFIG (safe for inline script).
     */
    public function getSerializedWidgetConfig(): string
    {
        $data = $this->buildWidgetConfigArray();
        if ($data === []) {
            return '';
        }
        return $this->json->serialize($data);
    }

    /**
     * JSON string or empty when overview inject should not run.
     */
    public function getSerializedOverviewInject(): string
    {
        $payload = $this->buildOverviewInjectPayload();
        return $payload === null ? '' : $this->json->serialize($payload);
    }

    public function shouldLoadOverviewInjectScript(): bool
    {
        return $this->getSerializedOverviewInject() !== '';
    }

    public function getAssetVersion(): string
    {
        return '1.0.1';
    }

    /**
     * @return array<string,mixed>
     */
    private function getMerged(): array
    {
        $store = $this->storeManager->getStore();
        $websiteId = (int) $store->getWebsiteId();
        $storeId = (int) $store->getId();
        return $this->configResolver->getMergedSettings($websiteId, $storeId);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildWidgetConfigArray(): array
    {
        $m = $this->getMerged();
        $searchBase = (string) ($m['resolved_search_base'] ?? '');
        if (!$this->resolvedUrls->isValidApiBaseForWidget($searchBase)) {
            return [];
        }

        $searchSelector = $m['search_selector'] ?? null;
        if (!is_array($searchSelector)) {
            $searchSelector = $this->decodeSearchSelectorsJson((string) ($m['search_selector_json'] ?? ''));
        }

        $position = (string) ($m['position'] ?? 'below');
        if ($position === 'dropdown') {
            $position = 'below';
        }

        $customerId = (string) ($m['customer_id'] ?? '');

        return [
            'apiUrl' => $searchBase . '/answer_wordpress',
            'customerId' => $customerId,
            'namespace' => (string) ($m['namespace'] ?? ''),
            'searchSelector' => $searchSelector,
            'resultsPageSelector' => (string) ($m['results_page_selector'] ?? ''),
            'primaryColor' => (string) ($m['primary_color'] ?? '#0066cc'),
            'position' => $position,
            'liveSearch' => !empty($m['live_search']),
            'styling' => [
                'resultBgColor' => (string) ($m['result_bg_color'] ?? '#ffffff'),
                'resultTextColor' => (string) ($m['result_text_color'] ?? '#333333'),
                'resultFontFamily' => (string) ($m['result_font_family'] ?? 'inherit'),
                'resultFontSize' => (string) ($m['result_font_size'] ?? '15px'),
                'resultBorderRadius' => (string) ($m['result_border_radius'] ?? '8px'),
                'resultBorderWidth' => (string) ($m['result_border_width'] ?? '1px'),
                'resultBorderColor' => (string) ($m['result_border_color'] ?? '#e0e0e0'),
                'resultBoxShadow' => (string) ($m['result_box_shadow'] ?? '0 4px 12px rgba(0,0,0,0.15)'),
                'showAiEmoji' => !empty($m['show_ai_emoji']),
                'aiEmojiChar' => (string) ($m['ai_emoji_char'] ?? '✨'),
                'poweredByProminence' => (string) ($m['powered_by_prominence'] ?? 'small'),
                'overviewContainerMaxWidth' => (string) ($m['overview_container_max_width'] ?? '900px'),
                'overviewProductCardMaxWidth' => (string) ($m['overview_product_card_max_width'] ?? '160px'),
            ],
            'isLoggedIn' => $this->customerSession->isLoggedIn(),
            'feedbackUrl' => $this->buildFeedbackUrl($searchBase, (string) ($m['feedback_url'] ?? '')),
            'i18n' => [
                'loading' => (string) __('Searching...'),
                'aiOverviewTitle' => (string) __('AI Overview'),
                'noResults' => (string) __('No results found.'),
                'error' => (string) __('An error occurred. Please try again.'),
                'poweredBy' => (string) __('Powered by AI'),
                'sourcesLabel' => (string) __('Sources:'),
                'wasThisHelpful' => (string) __('Was this helpful?'),
                'feedbackThanks' => (string) __('Thanks for your feedback!'),
                'feedbackThumbsUpAria' => (string) __('Thumbs up'),
                'feedbackThumbsDownAria' => (string) __('Thumbs down'),
                'viewProduct' => (string) __('View here'),
            ],
        ];
    }

    private function buildFeedbackUrl(string $searchBase, string $configured): string
    {
        $configured = trim($configured);
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
            return $configured;
        }
        return $searchBase . '/feedback';
    }

    /**
     * @return list<array<string,string>>|array<int, array<string,string>>
     */
    private function decodeSearchSelectorsJson(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        try {
            $data = $this->json->unserialize($json);
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildOverviewInjectPayload(): ?array
    {
        $m = $this->getMerged();
        $auto = !empty($m['auto_inject_overview']);
        $target = trim((string) ($m['overview_target_selector'] ?? ''));
        if (!$auto && $target === '') {
            return null;
        }
        $q = $this->getSearchQueryFromRequest();
        if ($q === '' || strlen($q) < 3) {
            return null;
        }
        return [
            'searchQuery' => $q,
            'title' => (string) __('AI Overview'),
            'overviewTargetSelector' => $target,
            'resultsPageSelector' => (string) ($m['results_page_selector'] ?? ''),
        ];
    }

    private function getSearchQueryFromRequest(): string
    {
        foreach (['q', 's', 'query', 'search'] as $p) {
            $v = trim((string) $this->request->getParam($p, ''));
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }
}
