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
        $offer = HNS_LevelReward::drawOffer($this->powers, ['attack', 'dash_1', 'dash_2', 'vortex', 'strike', 'missing']);

        $this->assertSame(['dash_1', 'vortex'], $offer);
    }

    public function testLevelRewardOfferStartsMissingFamilyAtRankOneOnly(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'vortex'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];
        $offer = HNS_LevelReward::drawOfferForPlayer($this->powers, ['dash_1', 'dash_2', 'vortex', 'vortex_2'], $playerPowers);

        $this->assertSame(['dash_1'], $offer);
    }

    public function testLevelRewardOfferKeepsStandardNewCardsAndAddsAvailableUpgrades(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'vortex'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'strike'],
        ];

        $offer = HNS_LevelReward::drawOfferForPlayer($this->powers, ['dash_1', 'vortex', 'vortex_2'], $playerPowers, 3);

        $this->assertSame(['dash_1'], $offer);
    }

    public function testLevelRewardOfferNeverContainsRankTwoCards(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_1'],
            ['slot' => 2, 'power_key' => 'vortex'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];

        $offer = HNS_LevelReward::drawOfferForPlayer($this->powers, ['dash_1', 'dash_2', 'vortex', 'vortex_2'], $playerPowers);

        $this->assertSame([], $offer);
    }

    public function testLevelRewardOfferDoesNotProposeOwnedFamilyAtRankOne(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_3'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'strike'],
        ];

        $offer = HNS_LevelReward::drawOfferForPlayer($this->powers, ['dash_1', 'vortex'], $playerPowers);

        $this->assertSame(['vortex'], $offer);
    }

    public function testLevelRewardUpgradeOfferSkipsRankMaxAndOwnedTargets(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_2'],
            ['slot' => 2, 'power_key' => 'dash_3'],
            ['slot' => 3, 'power_key' => 'vortex_2'],
        ];

        $upgrades = HNS_LevelReward::drawUpgradeOfferForPlayer($this->powers, $playerPowers);

        $this->assertSame([], $upgrades);
    }

    public function testLevelRewardUpgradeOfferIsSeparateFromRankOneOffer(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'dash_1'],
            ['slot' => 2, 'power_key' => 'vortex'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];

        $upgrades = HNS_LevelReward::drawUpgradeOfferForPlayer($this->powers, $playerPowers);

        $this->assertSame([
            ['slot' => 1, 'from' => 'dash_1', 'to' => 'dash_2'],
            ['slot' => 2, 'from' => 'vortex', 'to' => 'vortex_2'],
        ], $upgrades);
    }

    public function testPlayerCanTakeOfferedPowerIntoChosenSlot(): void
    {
        $playerPowers = [
            ['slot' => 1, 'power_key' => 'strike'],
            ['slot' => 2, 'power_key' => 'attack'],
            ['slot' => 3, 'power_key' => 'attack'],
        ];

        $updatedPowers = HNS_LevelReward::takeOfferedPower($playerPowers, 2, 'vortex', ['dash_1', 'vortex']);

        $this->assertSame('vortex', $updatedPowers[1]['power_key']);
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
