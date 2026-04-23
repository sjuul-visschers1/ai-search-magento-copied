<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PoweredByProminence implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'small', 'label' => __('Small')],
            ['value' => 'medium', 'label' => __('Medium')],
            ['value' => 'large', 'label' => __('Large')],
        ];
    }
}
