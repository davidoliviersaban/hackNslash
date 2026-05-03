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

            $event = $this->hydrateEngineEventForNotification($event);
            $message = self::engineEventMessage($type);
            $this->notifyAllPlayers($type, $message, $event);
        }
    }

    /** @param array<string, mixed> $event */
    private function hydrateEngineEventForNotification(array $event): array
    {
        if (($event['type'] ?? '') === 'monsterAttack' && !isset($event['player_name'])) {
            $event['player_name'] = $this->playerNameForEntityId((int) ($event['target_entity_id'] ?? 0));
        }
        if (($event['type'] ?? '') === 'monsterAttack' && !isset($event['target_health'])) {
            $event['target_health'] = $this->entityHealthForEntityId((int) ($event['target_entity_id'] ?? 0));
        }
        if (in_array($event['type'] ?? '', ['cardPlayed', 'afterCardPlayed'], true) && !isset($event['player_name'])) {
            $event['player_name'] = $this->playerNameForEntityId((int) ($event['source_entity_id'] ?? 0));
        }

        return $event;
    }

    private function playerNameForEntityId(int $entityId): string
    {
        if ($entityId <= 0) {
            return '';
        }

        $name = $this->getUniqueValueFromDB("SELECT p.player_name FROM entity e JOIN player p ON p.player_id = e.entity_owner WHERE e.entity_id = $entityId LIMIT 1");

        return is_string($name) ? $name : '';
    }

    private function entityHealthForEntityId(int $entityId): int
    {
        if ($entityId <= 0) {
            return 0;
        }

        return (int) $this->getUniqueValueFromDB("SELECT entity_health FROM entity WHERE entity_id = $entityId LIMIT 1");
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
                'cardPlayed' => clienttranslate('A power is played'),
                'afterCardPlayed' => clienttranslate('A power is played'),
                'afterDash' => '',
                'afterDashAttack' => '',
                'afterKill' => '',
                'afterPushOrPull' => '',
                'entityDamaged' => '',
                'entityHealed' => '',
                'thornsDamage' => clienttranslate('Thorns retaliate'),
                'shieldBroken' => clienttranslate('Shield is broken'),
                'monsterAttack' => clienttranslate('${player_name} is hit by a monster'),
                'monsterMove' => '',
                'monsterStick' => clienttranslate('A slime sticks to a hero'),
                'monsterCharge' => clienttranslate('A monster charges'),
                'monsterFrontArcAttack' => clienttranslate('A monster cleaves'),
                'monsterExplode' => clienttranslate('A kamikaze explodes'),
                'trapDamage' => clienttranslate('A trap triggers'),
                'bossPhaseDefeated' => clienttranslate('A boss phase is defeated'),
                'bossPhaseStarted' => clienttranslate('The boss attacks'),
                'bossSpawnMinion' => clienttranslate('The boss summons reinforcements'),
                'levelCleared' => clienttranslate('Level cleared!'),
                'gameWon' => clienttranslate('Heroes are victorious!'),
            ];
        }

        return $messages[$type] ?? '';
    }
}
