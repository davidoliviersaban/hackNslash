<?php

use PHPUnit\Framework\TestCase;

final class StructureTest extends TestCase
{
    public function testRequiredBgaFilesExist(): void
    {
        $root = dirname(__DIR__);
        $required = [
            'gameinfos.inc.php',
            'dbmodel.sql',
            'material.inc.php',
            'states.inc.php',
            'hacknslash.game.php',
            'hacknslash.action.php',
            'hacknslash.view.php',
            'hacknslash.js',
            'hacknslash.css',
            'hacknslash_hacknslash.tpl',
        ];

        foreach ($required as $file) {
            $this->assertFileExists($root . '/' . $file);
        }
    }

    public function testStateMachineContainsFullRoundCycleStates(): void
    {
        $states = file_get_contents(dirname(__DIR__) . '/states.inc.php');

        $this->assertStringContainsString("'name' => 'cooldown'", $states);
        $this->assertStringContainsString("'name' => 'activateTraps'", $states);
        $this->assertStringContainsString("'name' => 'activateMonsters'", $states);
        $this->assertStringContainsString("'name' => 'levelEndCheck'", $states);
        $this->assertStringContainsString("'gameEnd' => 99", $states);
    }
}
