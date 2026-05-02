<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/material/constants.inc.php';

final class ConstantsTest extends TestCase
{
    public function testHeroesStartWithTenHealth(): void
    {
        $this->assertSame(10, HNS_DEFAULT_HEALTH);
    }
}
