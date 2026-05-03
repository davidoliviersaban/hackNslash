<?php

final class HNS_LevelReward
{
    /**
     * @param array<string, array<string, mixed>> $powers
     * @param array<int, string> $deckPowerKeys
     * @return array<int, string>
     */
    public static function drawOffer(array $powers, array $deckPowerKeys, int $count = 3): array
    {
        $offer = [];
        foreach ($deckPowerKeys as $powerKey) {
            if (($powers[$powerKey]['rank'] ?? null) !== 1) {
                continue;
            }

            $offer[] = $powerKey;
            if (count($offer) === $count) {
                break;
            }
        }

        return $offer;
    }

    /**
     * @param array<string, array<string, mixed>> $powers
     * @param array<int, string> $deckPowerKeys
     * @param array<int, array{slot:int, power_key:string}> $playerPowers
     * @return array<int, string>
     */
    public static function drawOfferForPlayer(array $powers, array $deckPowerKeys, array $playerPowers, int $count = 3): array
    {
        $ownedPowerKeys = array_map(static fn (array $power): string => (string) $power['power_key'], $playerPowers);
        $ownedFamilies = array_map(static fn (string $powerKey): string => self::powerFamilyKey($powerKey, $powers), $ownedPowerKeys);
        $offer = [];

        foreach ($deckPowerKeys as $powerKey) {
            if (!isset($powers[$powerKey]) || (int) ($powers[$powerKey]['rank'] ?? 0) !== 1) {
                continue;
            }
            if (in_array(self::powerFamilyKey($powerKey, $powers), $ownedFamilies, true) || in_array($powerKey, $offer, true)) {
                continue;
            }

            $offer[] = $powerKey;
            if (count($offer) === $count) {
                break;
            }
        }

        return $offer;
    }

    /**
     * @param array<string, array<string, mixed>> $powers
     * @param array<int, string> $deckPowerKeys
     * @param array<int, array{slot:int, power_key:string}> $playerPowers
     * @return array<int, array{slot:int, from:string, to:string}>
     */
    public static function drawUpgradeOfferForPlayer(array $powers, array $deckPowerKeys, array $playerPowers, int $count = 3): array
    {
        $ownedPowerKeys = array_map(static fn (array $power): string => (string) $power['power_key'], $playerPowers);
        $upgrades = [];

        foreach ($deckPowerKeys as $deckPowerKey) {
            if (in_array($deckPowerKey, $ownedPowerKeys, true)) {
                continue;
            }

            foreach ($playerPowers as $playerPower) {
                $currentPowerKey = (string) $playerPower['power_key'];
                if (($powers[$currentPowerKey]['upgrades_to'] ?? null) !== $deckPowerKey) {
                    continue;
                }

                $upgrades[] = [
                    'slot' => (int) $playerPower['slot'],
                    'from' => $currentPowerKey,
                    'to' => $deckPowerKey,
                ];

                if (count($upgrades) === $count) {
                    return $upgrades;
                }

                break;
            }
        }

        return $upgrades;
    }

    /** @param array<string, array<string, mixed>> $powers */
    private static function powerFamilyKey(string $powerKey, array $powers): string
    {
        $rankOneKey = self::rankOnePowerKeyFor($powerKey, $powers);
        return $rankOneKey ?? $powerKey;
    }

    /** @param array<string, array<string, mixed>> $powers */
    private static function rankOnePowerKeyFor(string $powerKey, array $powers): ?string
    {
        if (!isset($powers[$powerKey])) {
            return null;
        }
        if ((int) ($powers[$powerKey]['rank'] ?? 0) === 1) {
            return $powerKey;
        }

        foreach ($powers as $candidateKey => $power) {
            if (($power['upgrades_to'] ?? null) === $powerKey) {
                return self::rankOnePowerKeyFor((string) $candidateKey, $powers);
            }
        }

        return null;
    }

    /**
     * @param array<int, array{slot:int, power_key:string}> $playerPowers
     * @param array<int, string> $offer
     * @return array<int, array{slot:int, power_key:string}>
     */
    public static function takeOfferedPower(array $playerPowers, int $slot, string $offeredPowerKey, array $offer): array
    {
        if (!in_array($offeredPowerKey, $offer, true)) {
            throw new InvalidArgumentException('Power is not in the level reward offer.');
        }

        foreach ($playerPowers as &$playerPower) {
            if ((int) $playerPower['slot'] === $slot) {
                $playerPower['power_key'] = $offeredPowerKey;
                return $playerPowers;
            }
        }

        throw new InvalidArgumentException("Unknown power slot $slot.");
    }

    /**
     * @param array<int, array{slot:int, power_key:string}> $playerPowers
     * @param array<string, array<string, mixed>> $powers
     * @return array<int, array{slot:int, power_key:string}>
     */
    public static function upgradeExistingPower(array $playerPowers, int $slot, array $powers): array
    {
        foreach ($playerPowers as &$playerPower) {
            if ((int) $playerPower['slot'] !== $slot) {
                continue;
            }

            $currentPowerKey = $playerPower['power_key'];
            $upgradePowerKey = $powers[$currentPowerKey]['upgrades_to'] ?? null;
            if ($upgradePowerKey === null) {
                throw new InvalidArgumentException("Power $currentPowerKey cannot be upgraded.");
            }

            $playerPower['power_key'] = $upgradePowerKey;
            return $playerPowers;
        }

        throw new InvalidArgumentException("Unknown power slot $slot.");
    }
}
