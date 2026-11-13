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

use GlpiPlugin\Dockerinventory\Ticket\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TicketPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function thresholdProvider(): iterable
    {
        yield 'majeure, seuil majeure' => ['major', 'major', true];
        yield 'mineure, seuil majeure' => ['minor', 'major', false];
        yield 'mineure, seuil mineure' => ['minor', 'minor', true];
        yield 'majeure, seuil correctif' => ['major', 'patch', true];
        yield 'correctif, seuil mineure' => ['patch', 'minor', false];
        yield 'correctif, seuil correctif' => ['patch', 'patch', true];
        yield 'préversion jamais' => ['prerelease', 'patch', false];
        yield 'empreinte jamais' => ['digest', 'patch', false];
        yield 'non suivable jamais' => ['untrackable', 'patch', false];
        yield 'non vérifié jamais' => ['unknown', 'patch', false];
        yield 'seuil inconnu' => ['major', 'tout', false];
    }

    #[DataProvider('thresholdProvider')]
    public function testReaches(string $gap, string $threshold, bool $expected): void
    {
        $this->assertSame($expected, TicketPolicy::reaches($gap, $threshold));
    }

    /**
     * @return array{gap: string, deployed_tag: string, latest_tag: string}
     */
    private static function s(string $gap, string $deployed, string $latest): array
    {
        return ['gap' => $gap, 'deployed_tag' => $deployed, 'latest_tag' => $latest];
    }

    public function testDecide(): void
    {
        $major = self::s('major', '1.2', '2.0');
        $triggers = TicketPolicy::triggers($major, 'major');
        $this->assertSame([TicketPolicy::TRIGGER_VERSION], $triggers);

        // Premier passage : ticket.
        $this->assertSame(TicketPolicy::ACTION_CREATE, TicketPolicy::decide($major, null, false, $triggers));
        // Sous le seuil : rien.
        $minor = self::s('minor', '1.2', '1.4');
        $this->assertSame(
            TicketPolicy::ACTION_NONE,
            TicketPolicy::decide($minor, null, false, TicketPolicy::triggers($minor, 'major'))
        );
        // Ticket ouvert, même situation : pas de doublon, pas de suivi.
        $this->assertSame(TicketPolicy::ACTION_NONE, TicketPolicy::decide($major, $major, true, $triggers));
        // Ticket ouvert, nouvelle version : suivi.
        $newer = self::s('major', '1.2', '2.1');
        $this->assertSame(TicketPolicy::ACTION_FOLLOWUP, TicketPolicy::decide($newer, $major, true, $triggers));
        // Ticket ouvert, écart résolu : suivi.
        $fixed = self::s('none', '2.1', '2.1');
        $this->assertSame(TicketPolicy::ACTION_FOLLOWUP, TicketPolicy::decide($fixed, $major, true, []));
        $this->assertSame('resolved', TicketPolicy::followupKind($fixed, []));
        $this->assertSame('below_threshold', TicketPolicy::followupKind($minor, []));
        $this->assertSame('still_outdated', TicketPolicy::followupKind($newer, $triggers));
        // Ticket ouvert, version non vérifiée (registre indisponible) : rien.
        $this->assertSame(
            TicketPolicy::ACTION_NONE,
            TicketPolicy::decide(self::s('unknown', '1.2', ''), $major, true, [])
        );
        // Ticket fermé pour la même version : rien ; pour une nouvelle version : nouveau ticket.
        $this->assertSame(TicketPolicy::ACTION_NONE, TicketPolicy::decide($major, $major, false, $triggers));
        $this->assertSame(TicketPolicy::ACTION_CREATE, TicketPolicy::decide($newer, $major, false, $triggers));
    }

    public function testVulnerabilityTriggerIsReservedForBatch3(): void
    {
        $upToDate = self::s('none', '2.0', '2.0');
        $this->assertSame([], TicketPolicy::triggers($upToDate, 'major'));
        $this->assertSame(
            [TicketPolicy::TRIGGER_VULNERABILITY],
            TicketPolicy::triggers($upToDate, 'major', [TicketPolicy::TRIGGER_VULNERABILITY => true])
        );
    }
}
