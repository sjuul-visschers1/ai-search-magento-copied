<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Model;

/**
 * Maps Firestore /config/get `config` object to snake_case settings (WordPress map_firestore_to_wordpress parity).
 */
class FirestoreMapper
{
    /**
     * @param array<string,mixed> $firestoreConfig
     * @param array<string,mixed> $existingOptions Only config_code / widget_code are read from here.
     * @return array<string,mixed>
     */
    public function mapFirestoreToSettings(array $firestoreConfig, array $existingOptions = []): array
    {
        $settings = [];
        if (isset($existingOptions['config_code'])) {
            $settings['config_code'] = $existingOptions['config_code'];
        }
        if (isset($existingOptions['widget_code'])) {
            $settings['widget_code'] = $existingOptions['widget_code'];
        }
        if (isset($firestoreConfig['customerId'])) {
            $settings['customer_id'] = (string) $firestoreConfig['customerId'];
        }
        if (isset($firestoreConfig['namespace'])) {
            $settings['namespace'] = (string) $firestoreConfig['namespace'];
        }
        if (isset($firestoreConfig['llmProvider'])) {
            $settings['llm_provider'] = $firestoreConfig['llmProvider'];
        }
        if (isset($firestoreConfig['llmApiKey'])) {
            $settings['llm_api_key'] = $firestoreConfig['llmApiKey'];
        }
        if (isset($firestoreConfig['llmModel'])) {
            $settings['llm_model'] = $firestoreConfig['llmModel'];
        }
        if (isset($firestoreConfig['brandPrompt'])) {
            $settings['brand_prompt'] = $firestoreConfig['brandPrompt'];
        }
        if (isset($firestoreConfig['primaryColor'])) {
            $settings['primary_color'] = (string) $firestoreConfig['primaryColor'];
        }
        if (isset($firestoreConfig['position'])) {
            $position = (string) $firestoreConfig['position'];
            $settings['position'] = $position === 'dropdown' ? 'below' : $position;
        }
        if (isset($firestoreConfig['liveSearch'])) {
            $settings['live_search'] = (bool) $firestoreConfig['liveSearch'];
        }
        if (isset($firestoreConfig['autoInjectOverview'])) {
            $settings['auto_inject_overview'] = (bool) $firestoreConfig['autoInjectOverview'];
        }
        if (isset($firestoreConfig['overviewTargetSelector'])) {
            $settings['overview_target_selector'] = (string) $firestoreConfig['overviewTargetSelector'];
        }
        if (isset($firestoreConfig['searchSelectors']) && is_array($firestoreConfig['searchSelectors'])) {
            $settings['search_selector'] = $firestoreConfig['searchSelectors'];
        }
        if (isset($firestoreConfig['features']) && is_array($firestoreConfig['features'])) {
            $features = $firestoreConfig['features'];
            if (isset($features['intentionRecognition'])) {
                $settings['intention_recognition'] = (bool) $features['intentionRecognition'];
            }
            if (isset($features['ctaGeneration'])) {
                $settings['cta_generation'] = (bool) $features['ctaGeneration'];
            }
            if (isset($features['answerCaching'])) {
                $settings['answer_caching'] = (bool) $features['answerCaching'];
            }
            if (isset($features['productFeed'])) {
                $settings['product_feed'] = (bool) $features['productFeed'];
            }
            if (isset($features['judgeAddon'])) {
                $settings['judge_addon'] = (bool) $features['judgeAddon'];
            }
        }
        if (isset($firestoreConfig['styling']) && is_array($firestoreConfig['styling'])) {
            $styling = $firestoreConfig['styling'];
            if (isset($styling['resultBgColor'])) {
                $settings['result_bg_color'] = (string) $styling['resultBgColor'];
            }
            if (isset($styling['resultTextColor'])) {
                $settings['result_text_color'] = (string) $styling['resultTextColor'];
            }
            if (isset($styling['resultFontFamily'])) {
                $settings['result_font_family'] = (string) $styling['resultFontFamily'];
            }
            if (isset($styling['resultFontSize'])) {
                $settings['result_font_size'] = (string) $styling['resultFontSize'];
            }
            if (isset($styling['resultBorderRadius'])) {
                $settings['result_border_radius'] = (string) $styling['resultBorderRadius'];
            }
            if (isset($styling['resultBorderWidth'])) {
                $settings['result_border_width'] = (string) $styling['resultBorderWidth'];
            }
            if (isset($styling['resultBorderColor'])) {
                $settings['result_border_color'] = (string) $styling['resultBorderColor'];
            }
            if (isset($styling['resultBoxShadow'])) {
                $settings['result_box_shadow'] = (string) $styling['resultBoxShadow'];
            }
            if (isset($styling['showAiEmoji'])) {
                $settings['show_ai_emoji'] = (bool) $styling['showAiEmoji'];
            }
            if (isset($styling['aiEmojiChar'])) {
                $settings['ai_emoji_char'] = (string) $styling['aiEmojiChar'];
            }
            if (isset($styling['poweredByProminence'])) {
                $settings['powered_by_prominence'] = (string) $styling['poweredByProminence'];
            }
            if (isset($styling['overviewContainerMaxWidth'])) {
                $settings['overview_container_max_width'] = (string) $styling['overviewContainerMaxWidth'];
            }
            if (isset($styling['overviewProductCardMaxWidth'])) {
                $settings['overview_product_card_max_width'] = (string) $styling['overviewProductCardMaxWidth'];
            }
        }
        if (isset($firestoreConfig['customCtas']) && is_array($firestoreConfig['customCtas'])) {
            $settings['custom_ctas'] = $firestoreConfig['customCtas'];
        }
        return $settings;
    }
}
