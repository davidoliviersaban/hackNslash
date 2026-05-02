<?php

final class HNS_FreeActionEngine
{
    public const EVENT_AFTER_MOVE = 'afterMove';
    public const EVENT_AFTER_DASH = 'afterDash';
    public const EVENT_AFTER_PUSH_OR_PULL = 'afterPushOrPull';
    public const EVENT_AFTER_KILL = 'afterKill';
    public const EVENT_AFTER_SHIELD_BREAK = 'afterShieldBreak';
    public const EVENT_AFTER_CARD_PLAYED = 'afterCardPlayed';
    public const EVENT_AFTER_TRAP = 'afterTrap';

    /** @var array<int, array<string, mixed>> */
    private array $activeEventChain;

    /** @var array<int, string> */
    private array $usedActionKeys;

    /**
     * @param array<int, array<string, mixed>> $activeEventChain
     * @param array<int, string> $usedActionKeys
     */
    public function __construct(array $activeEventChain = [], array $usedActionKeys = [])
    {
        $this->activeEventChain = array_values($activeEventChain);
        $this->usedActionKeys = array_values($usedActionKeys);
    }

    /**
     * @param array<int, string> $triggerEvents
     */
    public function canUseFreeAction(string $actionKey, array $triggerEvents, int $cooldown): bool
    {
        if ($cooldown > 0) {
            return false;
        }

        if (in_array($actionKey, $this->usedActionKeys, true)) {
            return false;
        }

        return $this->findMatchingEvent($triggerEvents) !== null;
    }

    /**
     * @param array<int, string> $triggerEvents
     * @param array<int, array<string, mixed>> $producedEvents
     * @return array{consumed_event: array<string, mixed>, cooldown: int, used_action_keys: array<int, string>, active_event_chain: array<int, array<string, mixed>>}
     */
    public function useFreeAction(
        string $actionKey,
        array $triggerEvents,
        int $currentCooldown,
        int $nextCooldown,
        array $producedEvents
    ): array {
        if (!$this->canUseFreeAction($actionKey, $triggerEvents, $currentCooldown)) {
            throw new InvalidArgumentException('Free action is not available.');
        }

        $consumedEvent = $this->findMatchingEvent($triggerEvents);
        if ($consumedEvent === null) {
            throw new LogicException('Free action availability changed during resolution.');
        }

        $this->usedActionKeys[] = $actionKey;
        $this->activeEventChain = array_values($producedEvents);

        return [
            'consumed_event' => $consumedEvent,
            'cooldown' => $nextCooldown,
            'used_action_keys' => $this->usedActionKeys,
            'active_event_chain' => $this->activeEventChain,
        ];
    }

    /**
     * @param array<int, string> $triggerEvents
     * @return array<string, mixed>|null
     */
    private function findMatchingEvent(array $triggerEvents): ?array
    {
        foreach ($this->activeEventChain as $event) {
            $eventType = $event['type'] ?? null;
            if (is_string($eventType) && in_array($eventType, $triggerEvents, true)) {
                return $event;
            }
        }

        return null;
    }
}
