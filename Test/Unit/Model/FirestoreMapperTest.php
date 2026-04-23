<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Springbok\SiteSearchAi\Model\FirestoreMapper;

class FirestoreMapperTest extends TestCase
{
    public function testMapsCustomerIdAndPositionDropdown(): void
    {
        $mapper = new FirestoreMapper();
        $out = $mapper->mapFirestoreToSettings(
            [
                'customerId' => 'cust_abc',
                'position' => 'dropdown',
                'primaryColor' => '#ff0000',
            ],
            ['config_code' => 'c1', 'widget_code' => 'w1']
        );
        $this->assertSame('cust_abc', $out['customer_id']);
        $this->assertSame('below', $out['position']);
        $this->assertSame('#ff0000', $out['primary_color']);
        $this->assertSame('c1', $out['config_code']);
        $this->assertSame('w1', $out['widget_code']);
    }
}
