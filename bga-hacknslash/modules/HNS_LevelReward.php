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
