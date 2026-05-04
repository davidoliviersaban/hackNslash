<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_LevelReward.php';

final class LevelRewardTest extends TestCase
{
    private array $powers;

    protected function setUp(): void
    {
        include dirname(__DIR__) . '/modules/material/bonus_cards.inc.php';
        $this->powers = $bonus_cards;
    }

    public function testLevelRewardOfferDrawsThreeRankOnePowers(): void
    {
        $offer = HNS_LevelReward::drawOffer($this->powers, ['attack', 'dash_1', 'dash_2', 'vortex_1', 'strike', 'missing']);

        $this->assertSame(['dash_1', 'vortex_1'], $offer);
    }

    public function testLevelRewardOfferStartsMissingFamilyAtRankOneOnly(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'vortex_1'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];
        $offer = HNS_LevelReward::drawOfferForPlayer($this->powers, ['dash_1', 'dash_2', 'vortex_1', 'vortex_2'], $playerPowers);

        $this->assertSame(['dash_1'], $offer);
    }

    public function testLevelRewardOfferKeepsStandardNewCardsAndAddsAvailableUpgrades(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'vortex_1'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'strike'],
        ];

        $offer = HNS_LevelReward::drawOfferForPlayer($this->powers, ['dash_1', 'vortex_1', 'vortex_2'], $playerPowers, 3);

        $this->assertSame(['dash_1'], $offer);
    }

    public function testLevelRewardOfferNeverContainsRankTwoCards(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_1'],
            ['slot' => 2, 'power_key' => 'vortex_1'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];

        $offer = HNS_LevelReward::drawOfferForPlayer($this->powers, ['dash_1', 'dash_2', 'vortex_1', 'vortex_2'], $playerPowers);

        $this->assertSame([], $offer);
    }

    public function testLevelRewardOfferDoesNotProposeOwnedFamilyAtRankOne(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_3'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'strike'],
        ];

        $offer = HNS_LevelReward::drawOfferForPlayer($this->powers, ['dash_1', 'vortex_1'], $playerPowers);

        $this->assertSame(['vortex_1'], $offer);
    }

    public function testLevelRewardUpgradeOfferSkipsRankMaxAndOffersVortexRankThree(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_2'],
            ['slot' => 2, 'power_key' => 'dash_3'],
            ['slot' => 3, 'power_key' => 'vortex_2'],
        ];

        $upgrades = HNS_LevelReward::drawUpgradeOfferForPlayer($this->powers, ['vortex_3', 'dash_2'], $playerPowers);

        $this->assertSame([
            ['slot' => 3, 'from' => 'vortex_2', 'to' => 'vortex_3'],
        ], $upgrades);
    }

    public function testLevelRewardUpgradeOfferIsSeparateFromRankOneOffer(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_1'],
            ['slot' => 2, 'power_key' => 'vortex_1'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];

        $upgrades = HNS_LevelReward::drawUpgradeOfferForPlayer($this->powers, ['vortex_2', 'dash_2'], $playerPowers);

        $this->assertSame([
            ['slot' => 2, 'from' => 'vortex_1', 'to' => 'vortex_2'],
            ['slot' => 1, 'from' => 'dash_1', 'to' => 'dash_2'],
        ], $upgrades);
    }

    public function testLevelRewardUpgradeOfferOnlyUsesRemainingDeckCards(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_1'],
            ['slot' => 2, 'power_key' => 'vortex_1'],
            ['slot' => 3, 'power_key' => 'quick_strike_1'],
        ];

        $upgrades = HNS_LevelReward::drawUpgradeOfferForPlayer($this->powers, ['quick_strike_2', 'missing', 'dash_3'], $playerPowers);

        $this->assertSame([
            ['slot' => 3, 'from' => 'quick_strike_1', 'to' => 'quick_strike_2'],
        ], $upgrades);
    }

    public function testPlayerCanTakeOfferedPowerIntoChosenSlot(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'strike'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];

        $updatedPowers = HNS_LevelReward::takeOfferedPower($playerPowers, 2, 'vortex_1', ['dash_1', 'vortex_1']);

        $this->assertSame('vortex_1', $updatedPowers[1]['power_key']);
    }

    public function testPlayerCanUpgradeExistingLevelOnePower(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_1'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];

        $updatedPowers = HNS_LevelReward::upgradeExistingPower($playerPowers, 1, $this->powers);

        $this->assertSame('dash_2', $updatedPowers[0]['power_key']);
    }

    public function testBasePowerCannotBeUpgradedDirectly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Power attack cannot be upgraded.');

        HNS_LevelReward::upgradeExistingPower([['slot' => 1, 'power_key' => 'attack']], 1, $this->powers);
    }
}
