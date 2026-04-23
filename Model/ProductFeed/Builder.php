<?php

declare(strict_types=1);

namespace Springbok\SiteSearchAi\Model\ProductFeed;

/**
 * Optional catalog → product_feed JSON (phase 2 / cron). Stub for packaging.
 */
class Builder
{
    /**
     * @return list<array<string,mixed>>
     */
    public function buildBatch(int $storeId, int $offset, int $limit): array
    {
        return [];
    }
}
