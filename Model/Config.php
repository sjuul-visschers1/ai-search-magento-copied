<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Model;

/**
 * Paths under ssai/* in system.xml / config.xml.
 */
class Config
{
    public const XML_PATH_SYNC_CONFIG_CODE = 'ssai/sync/config_code';
    public const XML_PATH_SYNC_WIDGET_CODE = 'ssai/sync/widget_code';
    public const XML_PATH_GENERAL_CUSTOMER_ID = 'ssai/general/customer_id';
    public const XML_PATH_GENERAL_NAMESPACE = 'ssai/general/namespace';
    public const XML_PATH_SELECTORS_SEARCH_JSON = 'ssai/selectors/search_selector_json';
    public const XML_PATH_SELECTORS_RESULTS_PAGE = 'ssai/selectors/results_page_selector';
    public const XML_PATH_OVERVIEW_AUTO_INJECT = 'ssai/overview/auto_inject';
    public const XML_PATH_OVERVIEW_TARGET = 'ssai/overview/overview_target_selector';
    public const XML_PATH_OVERVIEW_CONTAINER_MAX_WIDTH = 'ssai/overview/overview_container_max_width';
    public const XML_PATH_OVERVIEW_PRODUCT_CARD_MAX_WIDTH = 'ssai/overview/overview_product_card_max_width';
    public const XML_PATH_BEHAVIOR_LIVE_SEARCH = 'ssai/behavior/live_search';
    public const XML_PATH_BEHAVIOR_POSITION = 'ssai/behavior/position';
    public const XML_PATH_STYLING_PRIMARY_COLOR = 'ssai/styling/primary_color';
    public const XML_PATH_STYLING_RESULT_BG = 'ssai/styling/result_bg_color';
    public const XML_PATH_STYLING_RESULT_TEXT = 'ssai/styling/result_text_color';
    public const XML_PATH_STYLING_RESULT_FONT_FAMILY = 'ssai/styling/result_font_family';
    public const XML_PATH_STYLING_RESULT_FONT_SIZE = 'ssai/styling/result_font_size';
    public const XML_PATH_STYLING_RESULT_BORDER_RADIUS = 'ssai/styling/result_border_radius';
    public const XML_PATH_STYLING_RESULT_BORDER_WIDTH = 'ssai/styling/result_border_width';
    public const XML_PATH_STYLING_RESULT_BORDER_COLOR = 'ssai/styling/result_border_color';
    public const XML_PATH_STYLING_RESULT_BOX_SHADOW = 'ssai/styling/result_box_shadow';
    public const XML_PATH_STYLING_SHOW_AI_EMOJI = 'ssai/styling/show_ai_emoji';
    public const XML_PATH_STYLING_AI_EMOJI_CHAR = 'ssai/styling/ai_emoji_char';
    public const XML_PATH_STYLING_POWERED_BY = 'ssai/styling/powered_by_prominence';
    public const XML_PATH_FEEDBACK_URL = 'ssai/feedback/feedback_url';
    public const XML_PATH_SERVICE_BACKEND_URL = 'ssai/service/backend_url';
    public const XML_PATH_SERVICE_SEARCH_URL = 'ssai/service/search_url';

    public const DEFAULT_BACKEND_URL = 'https://customer-management-backend-jkwzc77jbq-ez.a.run.app';
    public const DEFAULT_SEARCH_URL = 'https://search.oryonx.nl';

    public const CACHE_KEY_PREFIX = 'springbok_ssai_cfg_';
    public const CACHE_TAG = 'SPRINGBOK_SSAI_CONFIG';
    public const CONFIG_CACHE_TTL = 300;
}
