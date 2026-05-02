<?php

/**
 * Translates engine events into BGA notifications.
 *
 * Engines (HNS_PowerResolver, HNS_MonsterAi, HNS_BossEngine, HNS_RoundEngine)
 * are pure: they produce arrays describing what happened. This trait is
 * responsible for forwarding those arrays as `notifyAllPlayers()` calls so the
 * client can update its state.
 *
 * Each event has a `type` string (mapped one-to-one to the BGA notification
 * name) and arbitrary payload keys carried verbatim as notif args.
 */
trait HNS_EventDispatcher
{
    /**
     * @param array<int, array<string, mixed>> $events
     */
    protected function notifyEngineEvents(array $events): void
    {
        foreach ($events as $event) {
            $type = (string) ($event['type'] ?? '');
            if ($type === '') {
                continue;
            }

            $message = self::engineEventMessage($type);
            $this->notifyAllPlayers($type, $message, $event);
        }
    }

    /**
     * Default short message bound to each event type. Falls back to an empty
     * string for unknown types: the client mostly cares about args.
     */
    private static function engineEventMessage(string $type): string
    {
        static $messages = null;
        if ($messages === null) {
            $messages = [
                'cardPlayed' => clienttranslate('${player_name} plays ${power_key}'),
                'afterCardPlayed' => clienttranslate('${player_name} plays ${power_key}'),
                'afterDash' => '',
                'afterKill' => '',
                'afterPushOrPull' => '',
                'thornsDamage' => clienttranslate('Thorns retaliate'),
                'shieldBroken' => clienttranslate('Shield is broken'),
                'monsterAttack' => clienttranslate('${player_name} is hit by a monster'),
                'monsterMove' => '',
                'monsterExplode' => clienttranslate('A kamikaze explodes'),
                'trapDamage' => clienttranslate('A trap triggers'),
                'bossPhaseDefeated' => clienttranslate('The boss enters a new phase'),
                'bossPhaseStarted' => clienttranslate('The boss attacks'),
                'bossSpawnMinion' => clienttranslate('The boss summons reinforcements'),
                'levelCleared' => clienttranslate('Level cleared!'),
                'gameWon' => clienttranslate('Heroes are victorious!'),
            ];
        }

        return $messages[$type] ?? '';
    }
}
