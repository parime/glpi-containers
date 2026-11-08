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

use GlpiPlugin\Dockerinventory\Portainer\PortainerClient;
use GlpiPlugin\Dockerinventory\Portainer\PortainerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PortainerClientTest extends TestCase
{
    private const KEY = 'ptr_fictive_cle_0123456789abcdef';

    public function testSendsApiKeyHeaderAndReadsList(): void
    {
        $calls = [];
        $client = new PortainerClient(
            'https://portainer.exemple.test',
            self::KEY,
            static function (string $url, array $query, array $headers) use (&$calls): string {
                $calls[] = [$url, $query, $headers];
                return '[{"Id": 1, "Name": "swarm-production"}, "ignoré"]';
            }
        );

        $this->assertSame([['Id' => 1, 'Name' => 'swarm-production']], $client->endpoints());
        $client->tasks(7);

        $this->assertSame('https://portainer.exemple.test/api/endpoints', $calls[0][0]);
        $this->assertSame(self::KEY, $calls[0][2]['X-API-Key']);
        $this->assertSame('https://portainer.exemple.test/api/endpoints/7/docker/tasks', $calls[1][0]);
        $this->assertSame('{"desired-state":["running"]}', $calls[1][1]['filters']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function errorProvider(): iterable
    {
        yield '401' => ['HTTP 401 returned for "https://portainer.exemple.test/api/endpoints".', 'unauthorized'];
        yield '403' => ['HTTP 403 returned for "x".', 'forbidden'];
        yield '404' => ['HTTP 404 returned for "x".', 'not_found'];
        yield '503' => ['HTTP 503 returned for "x".', 'not_swarm'];
        yield 'réseau privé' => ['Host "10.0.0.5" is blocked for "https://portainer.exemple.test/".',
            'private_network'];
        yield 'autre' => ['Could not resolve host', 'unreachable'];
    }

    #[DataProvider('errorProvider')]
    public function testErrorsBecomeStableCodesWithoutKey(string $message, string $code): void
    {
        $transport = static function () use ($message): string {
            throw new \RuntimeException($message . ' ' . self::KEY);
        };
        $client = new PortainerClient('https://portainer.exemple.test', self::KEY, $transport);

        try {
            $client->stacks();
            $this->fail('Exception attendue');
        } catch (PortainerException $e) {
            $this->assertSame($code, $e->getMessage());
            $this->assertStringNotContainsString(self::KEY, $e->getMessage());
        }
    }

    public function testNonListAnswerIsUnexpected(): void
    {
        $client = new PortainerClient('https://portainer.exemple.test', self::KEY, static fn (): string => '<html>');

        $this->expectException(PortainerException::class);
        $this->expectExceptionMessage(PortainerException::UNEXPECTED);
        $client->endpoints();
    }
}
