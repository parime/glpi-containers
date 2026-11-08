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

use GlpiPlugin\Dockerinventory\Wud\WudMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WudMapperTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>> Conteneurs du faux WUD, par nom.
     */
    private static function fixture(): array
    {
        $data = json_decode((string) file_get_contents(dirname(__DIR__) . '/mock-wud/fixture.json'), true);
        $byName = [];
        foreach ($data['containers'] as $container) {
            $byName[$container['name']] = $container;
        }

        return $byName;
    }

    public function testMapsFullContainer(): void
    {
        $mapped = WudMapper::mapContainer(self::fixture()['boutique_web.1.t01']);

        $this->assertNotNull($mapped);
        $this->assertSame('boutique/web', $mapped['image']);
        $this->assertSame('docker.io/boutique/web', $mapped['image_key']);
        $this->assertSame('2.4.1', $mapped['tag']);
        $this->assertTrue($mapped['semver']);
        $this->assertStringStartsWith('sha256:1f1e', $mapped['digest']);
        $this->assertSame('2.5.0', $mapped['latest_tag']);
        $this->assertTrue($mapped['update_available']);
        $this->assertSame('tag', $mapped['update_kind']);
        $this->assertSame('minor', $mapped['semver_diff']);
        $this->assertSame('boutique_web', $mapped['service_name']);
        $this->assertTrue($mapped['is_swarm']);
        $this->assertSame('', $mapped['error']);
    }

    public function testPrivateRegistryAndOutsideSwarm(): void
    {
        $fixture = self::fixture();
        $api = WudMapper::mapContainer($fixture['boutique_api.1.t04']);
        $this->assertSame('registry.exemple.test:5000/boutique/api', $api['image_key'] ?? null);

        $adminer = WudMapper::mapContainer($fixture['outils-recette-adminer-1']);
        $this->assertFalse($adminer['is_swarm'] ?? null);
        $this->assertSame('docker.io/library/adminer', $adminer['image_key'] ?? null);

        // Ni étiquettes ni nom de tâche : on ne sait pas s'il est dans Swarm.
        $db = WudMapper::mapContainer($fixture['db-boutique']);
        $this->assertNotNull($db);
        $this->assertNull($db['is_swarm']);
        $this->assertSame('', $db['service_name']);

        $backup = WudMapper::mapContainer($fixture['sauvegarde-nocturne']);
        $this->assertFalse($backup['has_result'] ?? true);
        $this->assertSame('Registry responded with 503', $backup['error'] ?? null);
    }

    public function testToleratesMissingAndUnknownFields(): void
    {
        $mapped = WudMapper::mapContainer([
            'id'       => 'c0ffee',
            'name'     => '/outil.1.abc',
            'image'    => 'pas un tableau',
            'inconnu'  => ['ignoré' => true],
            'updateKind' => ['kind' => 'TAG', 'semverDiff' => 'Patch'],
        ]);

        $this->assertNotNull($mapped);
        $this->assertSame('outil.1.abc', $mapped['name']);
        $this->assertSame('', $mapped['image']);
        $this->assertSame('', $mapped['tag']);
        $this->assertNull($mapped['semver']);
        $this->assertNull($mapped['update_available']);
        $this->assertFalse($mapped['has_result']);
        $this->assertSame('tag', $mapped['update_kind']);
        $this->assertSame('patch', $mapped['semver_diff']);
        $this->assertSame('outil', $mapped['service_name']);
        $this->assertNull(WudMapper::mapContainer(['image' => []]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function taskNameProvider(): iterable
    {
        yield 'réplique' => ['boutique_web.1.t01', 'boutique_web'];
        yield 'global' => ['supervision_agent.node-0001.t07', 'supervision_agent'];
        yield 'compose' => ['outils-recette-adminer-1', ''];
        yield 'nom libre' => ['db-boutique', ''];
    }

    #[DataProvider('taskNameProvider')]
    public function testServiceFromTaskName(string $name, string $service): void
    {
        $this->assertSame($service, WudMapper::serviceFromTaskName($name));
    }

    public function testImageKeys(): void
    {
        $this->assertSame('docker.io/library/postgres', WudMapper::referenceKey('postgres'));
        $this->assertSame('docker.io/grafana/alloy', WudMapper::referenceKey('docker.io/grafana/alloy'));
        $this->assertSame(
            'registry.exemple.test:5000/boutique/api',
            WudMapper::referenceKey('registry.exemple.test:5000/boutique/api')
        );
        $this->assertSame(
            'docker.io/library/postgres',
            WudMapper::wudImageKey('hub', 'https://registry-1.docker.io/v2', 'library/postgres')
        );
        // Registre inconnu : chemin seul, comparé sans l'hôte.
        $this->assertSame('boutique/api', WudMapper::wudImageKey('', '', 'boutique/api'));
        $this->assertTrue(WudMapper::sameImage('registry.exemple.test:5000/boutique/api', 'boutique/api'));
        $this->assertTrue(WudMapper::sameImage('docker.io/library/nginx', 'nginx'));
        $this->assertFalse(WudMapper::sameImage('registry.exemple.test:5000/boutique/api', 'docker.io/boutique/api'));
        $this->assertFalse(WudMapper::sameImage('', ''));
    }
}
