<?php

use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    private static function readFile(string $path): string
    {
        $contents = '';
        $file = new SplFileObject($path);
        while (!$file->eof()) {
            $contents .= $file->fgets();
        }
        return $contents;
    }

    public function testMonsterAttackNotificationAddsPlayerNameSubstitution(): void
    {
        $dispatcher = self::readFile(dirname(__DIR__) . '/modules/HNS_EventDispatcher.php');

        $this->assertStringContainsString('hydrateEngineEventForNotification', $dispatcher);
        $this->assertStringContainsString("\$event['player_name']", $dispatcher);
        $this->assertStringContainsString('playerNameForEntityId', $dispatcher);
    }

    public function testMonsterAttackNotificationAddsTargetHealth(): void
    {
        $dispatcher = self::readFile(dirname(__DIR__) . '/modules/HNS_EventDispatcher.php');

        $this->assertStringContainsString("\$event['target_health']", $dispatcher);
        $this->assertStringContainsString('entityHealthForEntityId', $dispatcher);
    }

    public function testCardPlayedNotificationsAddPlayerNameSubstitution(): void
    {
        $dispatcher = self::readFile(dirname(__DIR__) . '/modules/HNS_EventDispatcher.php');

        $this->assertStringContainsString("in_array(\$event['type'] ?? '', ['cardPlayed', 'afterCardPlayed'], true)", $dispatcher);
        $this->assertStringContainsString("(int) (\$event['source_entity_id'] ?? 0)", $dispatcher);
    }

    public function testCardPlayedLogDoesNotRequirePlayerNameSubstitution(): void
    {
        $dispatcher = self::readFile(dirname(__DIR__) . '/modules/HNS_EventDispatcher.php');

        $this->assertStringNotContainsString("'cardPlayed' => clienttranslate('\${player_name} plays \${power_key}')", $dispatcher);
        $this->assertStringNotContainsString("'afterCardPlayed' => clienttranslate('\${player_name} plays \${power_key}')", $dispatcher);
    }
}
