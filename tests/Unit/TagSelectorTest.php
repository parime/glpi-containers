<?php

/**
 * -------------------------------------------------------------------------
 * Docker Inventory plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-docker-inventory
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\Dockerinventory\Tests\Unit;

use GlpiPlugin\Dockerinventory\Version\TagSelector;
use GlpiPlugin\Dockerinventory\Version\VersionComparator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagSelectorTest extends TestCase
{
    private const POSTGRES = ['17.2-alpine', '18.0-beta1', '17.2', 'latest', '17.0', '16.6', '16.5', '16.4', '16'];

    /**
     * @return iterable<string, array{string, list<string>, string|null}>
     */
    public static function latestProvider(): iterable
    {
        yield 'majeure' => ['16.4', self::POSTGRES, '17.2'];
        yield 'préversion ignorée' => ['17.2', self::POSTGRES, '17.2'];
        yield 'même nombre de composantes' => ['16', self::POSTGRES, '16'];
        yield 'variante suivie seule' => ['17.0-alpine', self::POSTGRES, '17.2-alpine'];
        yield 'tag mobile' => ['latest', self::POSTGRES, null];
        yield 'préversion déployée : version finale' => [
            '2.0.0-rc1',
            ['2.0.0-rc1', '2.0.0-rc2', '2.0.0', '1.9.0'],
            '2.0.0',
        ];
        yield 'préversion déployée : même canal' => ['2.0.0-rc1', ['2.0.0-rc2', '2.0.0-beta3', '1.9.0'], '2.0.0-rc2'];
        yield 'autre canal ignoré' => ['2.0.0-rc1', ['2.0.0-beta3'], '2.0.0-rc1'];
        yield 'préfixe v' => ['v1.4.0', ['v1.4.0', '1.5.0', 'v1.5.0', 'v1.4.1'], 'v1.5.0'];
        yield 'révision numérique' => ['1.2.3', ['1.2.3', '1.2.3-1'], '1.2.3-1'];
        yield 'variante numérotée' => [
            '3.19-alpine3.18',
            ['3.19-alpine3.19', '3.20-alpine3.20', '3.20'],
            '3.20-alpine3.20',
        ];
        yield 'aucun tag plus récent' => ['5.0.0', ['4.9.9', 'latest'], '5.0.0'];
        yield 'tags vides et invalides ignorés' => ['1.0.0', ['', 'main', 'sha-1f1e1d', '1.0.1'], '1.0.1'];
    }

    /**
     * @param list<string> $tags
     */
    #[DataProvider('latestProvider')]
    public function testLatest(string $deployed, array $tags, ?string $expected): void
    {
        $this->assertSame($expected, TagSelector::latest($deployed, $tags));
    }

    public function testFamilyAndPrerelease(): void
    {
        $this->assertSame('alpine', TagSelector::family('alpine3.19'));
        $this->assertSame('rc', TagSelector::family('rc.1'));
        $this->assertSame('', TagSelector::family('1'));
        $this->assertTrue(TagSelector::isPrerelease('beta2'));
        $this->assertTrue(TagSelector::isPrerelease('RC1'));
        $this->assertFalse(TagSelector::isPrerelease('alpine'));
        $this->assertFalse(TagSelector::isPrerelease(''));
        $this->assertGreaterThan(0, TagSelector::compare('2.0.0', '2.0.0-rc9'));
        $this->assertLessThan(0, TagSelector::compare('1.9.9', '1.10.0'));
        $this->assertSame(0, TagSelector::compare('latest', '1.0'));
    }

    public function testEvaluateGapFromRegistryTags(): void
    {
        $tags = [
            ['name' => '7.4.2', 'digest' => 'sha256:d742'],
            ['name' => '7.4.1', 'digest' => 'sha256:d741'],
            ['name' => '8.0-rc1', 'digest' => 'sha256:d80'],
        ];
        $this->assertSame([
            'latest_tag'       => '7.4.2',
            'latest_digest'    => 'sha256:d742',
            'gap'              => VersionComparator::GAP_PATCH,
            'update_available' => 1,
        ], TagSelector::evaluate('7.4.1', '', $tags));

        $upToDate = TagSelector::evaluate('7.4.2', 'sha256:d742', $tags);
        $this->assertSame(VersionComparator::GAP_NONE, $upToDate['gap']);
        $this->assertSame(0, $upToDate['update_available']);

        // Même tag, nouvelle empreinte (image reconstruite).
        $rebuilt = TagSelector::evaluate('7.4.2', 'sha256:ancienne', $tags);
        $this->assertSame(VersionComparator::GAP_DIGEST, $rebuilt['gap']);
        $this->assertSame(1, $rebuilt['update_available']);
    }

    public function testEvaluateUntrackableAndEmpty(): void
    {
        $tags = [['name' => 'latest', 'digest' => 'sha256:neuve'], ['name' => '1.5.0', 'digest' => '']];
        $moving = TagSelector::evaluate('latest', 'sha256:ancienne', $tags);
        $this->assertSame(VersionComparator::GAP_UNTRACKABLE, $moving['gap']);
        $this->assertSame(1, $moving['update_available']);
        $this->assertSame('sha256:neuve', $moving['latest_digest']);

        $same = TagSelector::evaluate('latest', 'sha256:neuve', $tags);
        $this->assertSame(VersionComparator::GAP_UNTRACKABLE, $same['gap']);
        $this->assertSame(0, $same['update_available']);

        $this->assertSame(VersionComparator::GAP_UNKNOWN, TagSelector::evaluate('1.0.0', '', [])['gap']);
    }
}
