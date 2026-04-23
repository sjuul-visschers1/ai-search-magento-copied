<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Block\Frontend;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Springbok\SiteSearchAi\ViewModel\WidgetConfig;

class WidgetInit extends Template
{
    public function __construct(
        Context $context,
        private readonly WidgetConfig $widgetConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getWidgetConfig(): WidgetConfig
    {
        return $this->widgetConfig;
    }

    protected function _toHtml(): string
    {
        if (!$this->widgetConfig->isWidgetRenderable()) {
            return '';
        }
        return parent::_toHtml();
    }
}
