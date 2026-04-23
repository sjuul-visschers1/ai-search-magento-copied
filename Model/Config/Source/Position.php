<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Position implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'below', 'label' => __('Below (dropdown)')],
            ['value' => 'overlay', 'label' => __('Overlay')],
        ];
    }
}
