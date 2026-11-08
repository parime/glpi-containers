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

use GlpiPlugin\Dockerinventory\Version\VersionComparator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Comparaison des versions sur un jeu de tags fictifs, cas limites compris.
 */
final class VersionComparatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function trackableProvider(): iterable
    {
        yield 'majeure.mineure' => ['1.2', true];
        yield 'avec variante' => ['1.2.3-alpine', true];
        yield 'calendaire' => ['2024.10.1', true];
        yield 'préfixe v' => ['v1.2.3', true];
        yield 'majeure seule' => ['16', true];
        yield 'préversion' => ['2.0.0-rc.1', true];
        yield 'latest' => ['latest', false];
        yield 'stable' => ['stable', false];
        yield 'edge' => ['edge', false];
        yield 'vide' => ['', false];
        yield 'empreinte courte' => ['1a2b3c4d5e6f', false];
        yield 'branche' => ['main-20241010', false];
    }

    #[DataProvider('trackableProvider')]
    public function testTrackableTags(string $tag, bool $expected): void
    {
        $this->assertSame($expected, VersionComparator::isTrackable($tag));
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function diffProvider(): iterable
    {
        yield 'correctif' => ['1.2.3', '1.2.4', 'patch'];
        yield 'mineure' => ['1.2.3', '1.3.0', 'minor'];
        yield 'majeure' => ['1.2.3', '2.0.0', 'major'];
        yield 'version partielle' => ['1.2', '1.2.1', 'patch'];
        yield 'nombre absent vaut zéro' => ['1.2', '1.2.0', 'none'];
        yield 'préfixe v' => ['v1.2.3', '1.2.3', 'none'];
        yield 'variante conservée' => ['1.2.3-alpine', '1.2.5-alpine', 'patch'];
        yield 'suffixe seul' => ['2.0.0-rc.1', '2.0.0', 'prerelease'];
        yield 'calendaire mois' => ['2024.10.1', '2024.11.0', 'minor'];
        yield 'calendaire année' => ['2024.10.1', '2025.1.0', 'major'];
        yield 'latest' => ['latest', '1.2.3', null];
        yield 'stable' => ['1.2.3', 'stable', null];
    }

    #[DataProvider('diffProvider')]
    public function testDiff(string $from, string $to, ?string $expected): void
    {
        $this->assertSame($expected, VersionComparator::diff($from, $to));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{tag: string, digest: string, latest_tag: string, latest_digest: string, has_result: bool,
     *     update_available: bool|null, update_kind: string, semver_diff: string}
     */
    private static function container(array $overrides): array
    {
        /** @var array{tag: string, digest: string, latest_tag: string, latest_digest: string, has_result: bool,
         *     update_available: bool|null, update_kind: string, semver_diff: string} $container */
        $container = $overrides + [
            'tag'              => '1.2.3',
            'digest'           => 'sha256:aaaa',
            'latest_tag'       => '1.2.3',
            'latest_digest'    => 'sha256:aaaa',
            'has_result'       => true,
            'update_available' => false,
            'update_kind'      => '',
            'semver_diff'      => '',
        ];

        return $container;
    }

    public function testWudResultIsTakenAsIs(): void
    {
        // WUD dit « mineure » même si le plugin aurait compté autrement : son calcul fait foi.
        $this->assertSame('minor', VersionComparator::classify(self::container([
            'latest_tag' => '2.0.0', 'update_available' => true, 'update_kind' => 'tag', 'semver_diff' => 'minor',
        ])));
        $this->assertSame('major', VersionComparator::classify(self::container([
            'tag' => '16.4', 'latest_tag' => '17.2', 'update_available' => true, 'update_kind' => 'tag',
            'semver_diff' => 'major',
        ])));
        $this->assertSame('none', VersionComparator::classify(self::container([])));
    }

    public function testDigestOnlyAndUntrackable(): void
    {
        $this->assertSame('digest', VersionComparator::classify(self::container([
            'latest_digest' => 'sha256:bbbb', 'update_available' => true, 'update_kind' => 'digest',
        ])));
        // « latest » reste non suivable, même avec une nouvelle empreinte.
        $this->assertSame('untrackable', VersionComparator::classify(self::container([
            'tag' => 'latest', 'latest_tag' => 'latest', 'latest_digest' => 'sha256:bbbb',
            'update_available' => true, 'update_kind' => 'digest',
        ])));
        $this->assertSame('untrackable', VersionComparator::classify(self::container(['tag' => 'stable'])));
    }

    public function testMissingFieldsAreDeducedOrUnknown(): void
    {
        // Pas de résultat : non vérifié.
        $this->assertSame('unknown', VersionComparator::classify(self::container([
            'has_result' => false, 'latest_tag' => '', 'latest_digest' => '',
        ])));
        // Ni updateAvailable ni updateKind : déduits du résultat, écart calculé par le plugin.
        $this->assertSame('patch', VersionComparator::classify(self::container([
            'latest_tag' => '1.2.4', 'update_available' => null,
        ])));
        $this->assertSame('digest', VersionComparator::classify(self::container([
            'latest_digest' => 'sha256:bbbb', 'update_available' => null,
        ])));
    }

    public function testSeverityOrder(): void
    {
        $this->assertLessThan(VersionComparator::severity('minor'), VersionComparator::severity('major'));
        $this->assertLessThan(VersionComparator::severity('none'), VersionComparator::severity('untrackable'));
        $this->assertSame(count(VersionComparator::GAPS), VersionComparator::severity('inconnu'));
    }
}
