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
}
