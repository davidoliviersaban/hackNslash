<?php

use PHPUnit\Framework\TestCase;

final class GameinfosTest extends TestCase
{
    public function testFirstPlayableScopeIsOneOrTwoPlayerCooperativeMode(): void
    {
        include dirname(__DIR__) . '/gameinfos.inc.php';

        $this->assertSame([1, 2], $gameinfos['players']);
        $this->assertSame(2, $gameinfos['suggest_player_number']);
        $this->assertSame(1, $gameinfos['is_coop']);
        $this->assertTrue($gameinfos['solo_mode_ranked']);
    }
}
