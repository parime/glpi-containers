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

use GlpiPlugin\Dockerinventory\Registry\RegistryClient;
use GlpiPlugin\Dockerinventory\Registry\RegistryException;
use GlpiPlugin\Dockerinventory\Registry\RegistryResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Clients des registres et résolution du registre d'une image, sans réseau : jamais le vrai Docker
 * Hub (adresse fictive et transport en mémoire).
 */
final class RegistryClientTest extends TestCase
{
    private const HUB = 'https://hub.exemple.test';
    private const PASSWORD = 'robot_mot_de_passe_fictif';

    public function testDockerHubReadsPagesAnonymously(): void
    {
        $calls = [];
        $client = RegistryClient::dockerHub(self::HUB, static function (
            string $url,
            array $query,
            array $headers
        ) use (&$calls): string {
            $calls[] = [$url, $query, $headers];
            $page = (int) $query['page'];

            return (string) json_encode([
                'next'    => $page < 2 ? 'https://hub.exemple.test/next' : null,
                'results' => [['name' => '1.' . $page, 'digest' => 'sha256:' . $page], ['digest' => 'sans nom']],
            ]);
        });

        $this->assertSame(
            [['name' => '1.1', 'digest' => 'sha256:1'], ['name' => '1.2', 'digest' => 'sha256:2']],
            $client->tags('library/postgres')
        );
        $this->assertCount(2, $calls);
        $this->assertSame(self::HUB . '/v2/repositories/library/postgres/tags', $calls[0][0]);
        $this->assertSame(['page' => 1, 'page_size' => 100, 'ordering' => 'last_updated'], $calls[0][1]);
        $this->assertArrayNotHasKey('Authorization', $calls[0][2]);
        $this->assertSame(2, $client->requests());
        $this->assertFalse($client->hasCredentials());
    }

    public function testDockerHubStopsAfterMaxPages(): void
    {
        $count = 0;
        $client = RegistryClient::dockerHub(self::HUB, static function () use (&$count): string {
            $count++;

            return '{"next": "https://hub.exemple.test/suite", "results": [{"name": "1.0"}]}';
        });

        $this->assertCount(RegistryClient::MAX_HUB_PAGES, $client->tags('grafana/grafana'));
        $this->assertSame(RegistryClient::MAX_HUB_PAGES, $count);
    }

    public function testHarborReadsTagsListWithBasicAuth(): void
    {
        $calls = [];
        $transport = static function (string $url, array $query, array $headers) use (&$calls): string {
            $calls[] = [$url, $headers];

            return str_ends_with($url, '/v2/') ? '{}' : '{"name": "boutique/api", "tags": ["1.9.0", 7, "1.10.0"]}';
        };
        $client = RegistryClient::harbor('https://harbor.exemple.test/', 'robot$lecture', self::PASSWORD, $transport);

        $client->ping();
        $this->assertSame(
            [['name' => '1.9.0', 'digest' => ''], ['name' => '1.10.0', 'digest' => '']],
            $client->tags('boutique/api')
        );
        $this->assertSame('https://harbor.exemple.test/v2/', $calls[0][0]);
        $this->assertSame('https://harbor.exemple.test/v2/boutique/api/tags/list', $calls[1][0]);
        $this->assertSame('Basic ' . base64_encode('robot$lecture:' . self::PASSWORD), $calls[1][1]['Authorization']);
        $this->assertTrue($client->hasCredentials());
    }

    public function testHarborRepositoryWithoutTags(): void
    {
        $client = RegistryClient::harbor('https://harbor.exemple.test', '', '', static fn (): string
            => '{"name": "vide", "tags": null}');

        $this->assertSame([], $client->tags('projet/vide'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function failureProvider(): iterable
    {
        yield '401' => ['HTTP 401 returned for "https://harbor.exemple.test/v2/"', RegistryException::UNAUTHORIZED];
        yield '403' => ['HTTP/1.1 403 Forbidden', RegistryException::FORBIDDEN];
        yield '404' => ['HTTP 404 returned', RegistryException::NOT_FOUND];
        yield '429' => ['HTTP 429 returned', RegistryException::RATE_LIMITED];
        yield '503' => ['HTTP 503 returned', RegistryException::SERVER_ERROR];
        yield 'réseau privé' => [
            'Host "10.0.0.5" is blocked for the current context',
            RegistryException::PRIVATE_NETWORK,
        ];
        yield 'réseau' => ['Could not resolve host', RegistryException::UNREACHABLE];
    }

    #[DataProvider('failureProvider')]
    public function testFailuresBecomeStableCodes(string $message, string $code): void
    {
        $transport = static function () use ($message): string {
            throw new \RuntimeException($message . ' ' . self::PASSWORD);
        };
        $client = RegistryClient::harbor('https://harbor.exemple.test', 'robot$lecture', self::PASSWORD, $transport);

        try {
            $client->tags('boutique/api');
            $this->fail('Exception attendue');
        } catch (RegistryException $e) {
            $this->assertSame($code, $e->getMessage());
            $this->assertStringNotContainsString(self::PASSWORD, $e->getMessage());
        }
    }

    public function testUnexpectedAnswers(): void
    {
        foreach (['<html>', '{"results": {"a": 1}}', '[]'] as $body) {
            $client = RegistryClient::dockerHub(self::HUB, static fn (): string => $body);
            try {
                $client->tags('library/redis');
                $this->fail('Exception attendue pour ' . $body);
            } catch (RegistryException $e) {
                $this->assertSame(RegistryException::UNEXPECTED, $e->getMessage());
            }
        }
    }

    public function testRepositoryNamesAreValidatedAndEncoded(): void
    {
        $this->assertSame('library/postgres', RegistryClient::encodePath('library/postgres'));
        $this->assertSame('boutique/api-v2/web_front', RegistryClient::encodePath('/Boutique/api-v2/web_front/'));
        $this->assertSame('', RegistryClient::encodePath('../etc/passwd'));
        $this->assertSame('', RegistryClient::encodePath('projet/dépôt'));
        $this->assertSame('', RegistryClient::encodePath(''));

        $client = RegistryClient::dockerHub(self::HUB, function (): string {
            $this->fail('Aucun appel pour un nom invalide');
        });
        $this->expectException(RegistryException::class);
        $client->tags('a/../b');
    }

    public function testDockerHubUrlDefault(): void
    {
        if (!defined('PLUGIN_DOCKERINVENTORY_DOCKER_HUB_URL')) {
            $this->assertSame('https://hub.docker.com', RegistryClient::dockerHubUrl());
        }
        $this->assertSame(RegistryClient::KIND_DOCKER_HUB, RegistryClient::dockerHub(self::HUB)->kind());
    }

    public function testResolverFindsTheRegistryOfAnImage(): void
    {
        $harbors = [
            ['id' => 7, 'host' => 'registry.exemple.test:5000', 'entities' => [3, 4]],
            ['id' => 8, 'host' => 'harbor.exemple.test', 'entities' => [0, 3, 4]],
        ];

        $this->assertSame(
            ['kind' => 'dockerhub', 'host' => 'docker.io', 'repository' => 'library/postgres', 'connection_id' => 0],
            RegistryResolver::resolve('postgres', 3, $harbors)
        );
        $grafana = RegistryResolver::resolve('docker.io/grafana/grafana', 3, $harbors);
        $this->assertSame('grafana/grafana', $grafana['repository']);
        $api = 'registry.exemple.test:5000/boutique/api';
        $this->assertSame(
            ['kind' => 'harbor', 'host' => 'registry.exemple.test:5000', 'repository' => 'boutique/api',
                'connection_id' => 7],
            RegistryResolver::resolve($api, 4, $harbors)
        );
        // Entité non couverte par la connexion Harbor : registre inconnu.
        $this->assertSame('unknown', RegistryResolver::resolve($api, 0, $harbors)['kind']);
        $annuaire = RegistryResolver::resolve('Harbor.Exemple.test/outils/annuaire', 0, $harbors);
        $this->assertSame(8, $annuaire['connection_id']);
        $this->assertSame('unknown', RegistryResolver::resolve('ghcr.io/exemple/outil', 3, $harbors)['kind']);
    }

    public function testHarborHost(): void
    {
        $this->assertSame('harbor.exemple.test', RegistryResolver::harborHost('https://Harbor.exemple.test/', ''));
        $this->assertSame(
            'harbor.exemple.test:8443',
            RegistryResolver::harborHost('https://harbor.exemple.test:8443', '')
        );
        $this->assertSame(
            'registry.exemple.test:5000',
            RegistryResolver::harborHost('http://10.0.0.5:8080', 'registry.exemple.test:5000')
        );
        $this->assertSame('', RegistryResolver::harborHost('', ''));
    }
}
