<?php

use PHPUnit\Framework\TestCase;

/**
 * Verifies that every image asset referenced in hacknslash.js actually exists
 * under img/. The JS code uses this.getAssetUrl(<relativePath>) and string
 * literals such as 'tiles/levels/floor.webp' or 'cards/powers/attack-0.webp'.
 *
 * We extract every occurrence of a quoted "tiles/..." / "cards/..." asset path
 * and assert the corresponding file exists on disk.
 */
final class AssetExistenceTest extends TestCase
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

    /**
     * Extract every quoted path that points into the img/ asset folders.
     *
     * @return string[]
     */
    private static function extractAssetPaths(string $source): array
    {
        // Capture quoted strings starting with "tiles/" or "cards/" and ending with .webp/.png/.jpg/.jpeg/.svg.
        preg_match_all(
            "#['\"]((?:tiles|cards)/[^'\"\\s]+?\\.(?:webp|png|jpg|jpeg|svg))['\"]#",
            $source,
            $matches
        );

        $paths = array_unique($matches[1] ?? []);
        sort($paths);
        return $paths;
    }

    public function testEveryAssetReferencedInJsExists(): void
    {
        $root = dirname(__DIR__);
        $js = self::readFile($root . '/hacknslash.js');
        $paths = self::extractAssetPaths($js);

        $this->assertNotEmpty($paths, 'No asset paths were extracted from hacknslash.js — extraction regex likely broken.');

        $missing = [];
        foreach ($paths as $relative) {
            $absolute = $root . '/img/' . $relative;
            if (!file_exists($absolute)) {
                $missing[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Missing asset files referenced in hacknslash.js:\n  - " . implode("\n  - ", $missing)
        );
    }

    public function testLevelTileAssetsAreAllReferenced(): void
    {
        $root = dirname(__DIR__);
        $js = self::readFile($root . '/hacknslash.js');

        // The level renderer must reference the entrance/exit/pillar/hole/spikes
        // icons plus at least the four floor variants and the wall directional
        // variants.
        // Assets that are string literals in the JS.
        $literalAssets = [
            'tiles/levels/floor.webp',
            'tiles/levels/floor-1.webp',
            'tiles/levels/floor-2.webp',
            'tiles/levels/floor-3.webp',
            'tiles/levels/wall-top-left.webp',
            'tiles/levels/wall-top-right.webp',
            'tiles/levels/wall-bottom-left.webp',
            'tiles/levels/wall-bottom-right.webp',
            'tiles/levels/wall-top.webp',
            'tiles/levels/wall-bottom.webp',
            'tiles/levels/wall-left.webp',
            'tiles/levels/wall-right.webp',
            'tiles/levels/wall--left-right.webp',
        ];

        // Assets constructed dynamically via simpleTypes mapping — verify the
        // type name appears in the mapping and the file exists on disk.
        $dynamicTypes = ['entrance', 'exit', 'pillar', 'hole', 'spikes'];

        foreach ($literalAssets as $relative) {
            $this->assertStringContainsString(
                $relative,
                $js,
                "hacknslash.js does not reference required level asset $relative"
            );
            $this->assertFileExists(
                $root . '/img/' . $relative,
                "Level asset $relative is missing on disk"
            );
        }

        foreach ($dynamicTypes as $type) {
            $this->assertStringContainsString(
                "'" . $type . "'",
                $js,
                "hacknslash.js does not reference tile type $type in simpleTypes mapping"
            );
            $this->assertFileExists(
                $root . '/img/tiles/levels/' . $type . '.webp',
                "Level asset tiles/levels/$type.webp is missing on disk"
            );
        }

        $this->assertLessThan(
            strpos($js, "return 'tiles/levels/wall--left-right.webp'"),
            strpos($js, "return 'tiles/levels/wall-top-left.webp'"),
            'Corner wall variants must be tested before straight wall variants.'
        );
    }

    public function testMonsterTileAssetsAreAllReferenced(): void
    {
        $root = dirname(__DIR__);
        $js = self::readFile($root . '/hacknslash.js');

        $required = [
            'tiles/monsters/goblin-pixel.webp',
            'tiles/monsters/slime-pixel.webp',
            'tiles/monsters/evil-eye-pixel.webp',
            'tiles/monsters/goblin-kamikaze-pixel.webp',
            'tiles/monsters/goblin-wizard-pixel.webp',
            'tiles/monsters/goblin-bomber-pixel.webp',
            'tiles/monsters/orc-pixel.webp',
            'tiles/monsters/pig-rider-pixel.webp',
            'tiles/monsters/wolf-rider-pixel.webp',
            'tiles/monsters/slasher-pixel.webp',
        ];

        foreach ($required as $relative) {
            $this->assertStringContainsString(
                $relative,
                $js,
                "hacknslash.js does not reference required monster asset $relative"
            );
            $this->assertFileExists(
                $root . '/img/' . $relative,
                "Monster asset $relative is missing on disk"
            );
        }
    }

    public function testSlasherBossCardAssetsAreReferenced(): void
    {
        $root = dirname(__DIR__);
        $js = self::readFile($root . '/hacknslash.js');

        foreach (['slasher-1.webp', 'slasher-2.webp', 'slasher-3.webp'] as $file) {
            $relative = 'cards/monsters/' . $file;
            $this->assertStringContainsString($relative, $js);
            $this->assertFileExists($root . '/img/' . $relative);
        }
    }

    public function testGoblinMapSpriteUsesGoblinKey(): void
    {
        $root = dirname(__DIR__);
        $js = self::readFile($root . '/hacknslash.js');

        $this->assertStringContainsString("1: 'goblin'", $js);
        $this->assertStringContainsString("'goblin': 'tiles/monsters/goblin-pixel.webp'", $js);
        $this->assertStringContainsString("return keys[typeArg] || 'goblin';", $js);
        $this->assertStringNotContainsString("|| 'tiles/markers/monster.webp'", $js);
    }

    public function testMonsterEntitiesRenderAsSpritesInsteadOfRoundTokens(): void
    {
        $root = dirname(__DIR__);
        $css = self::readFile($root . '/hacknslash.css');

        $this->assertStringContainsString('.hns_entity_monster {', $css);
        $this->assertStringContainsString('background: transparent;', $css);
        $this->assertStringContainsString('border-color: transparent;', $css);
        $this->assertStringContainsString('.hns_entity_monster img {', $css);
        $this->assertStringContainsString('width: 46px;', $css);
        $this->assertStringContainsString('height: 46px;', $css);
    }

    public function testBossEntitiesRenderWithSlasherPixelSprite(): void
    {
        $root = dirname(__DIR__);
        $css = self::readFile($root . '/hacknslash.css');
        $js = self::readFile($root . '/hacknslash.js');

        $this->assertStringContainsString('.hns_entity_boss {', $css);
        $this->assertStringContainsString('.hns_entity_boss img {', $css);
        $this->assertStringContainsString('getBossTileImage', $js);
        $this->assertStringContainsString("'slasher': 'tiles/monsters/slasher-pixel.webp'", $js);
    }

    public function testPowerCardImagesDoNotUseImplicitBaseFallback(): void
    {
        $root = dirname(__DIR__);
        $js = self::readFile($root . '/hacknslash.js');

        $this->assertStringNotContainsString("replace(/_\\d+$/, '')", $js);
        $this->assertStringNotContainsString("+ '-1.webp'", $js);
        $this->assertStringNotContainsString('cards/powers/powers-1.webp', $js);
        $this->assertStringContainsString("files[powerKey] || 'cards/powers/attack-0.webp'", $js);
    }
}
