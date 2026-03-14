<?php

declare(strict_types=1);

namespace App\Tests\Unit\General\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use PHPUnit\Framework\TestCase;

final class CacheKeyConventionServiceTest extends TestCase
{
    public function testCrmTaskListTagIsScopedByApplicationSlug(): void
    {
        $service = new CacheKeyConventionService();

        $tagA = $service->crmTaskListTag('crm-app-alpha');
        $tagB = $service->crmTaskListTag('crm-app-beta');

        self::assertNotSame($tagA, $tagB);
        self::assertSame('cache_crm_task_list_crm-app-alpha', $tagA);
        self::assertSame('cache_crm_task_list_crm-app-beta', $tagB);
    }
}
