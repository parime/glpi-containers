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

use GlpiPlugin\Dockerinventory\Wud\WudClient;
use GlpiPlugin\Dockerinventory\Wud\WudException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WudClientTest extends TestCase
{
    private const PASSWORD = 'wud_mot_de_passe_fictif';

    public function testReadsAppAndContainersWithBasicAuth(): void
    {
        $calls = [];
        $client = new WudClient(
            'https://wud.exemple.test:3000',
            'lecteur',
            self::PASSWORD,
            static function (string $url, array $query, array $headers) use (&$calls): string {
                $calls[] = [$url, $headers];
                return str_ends_with($url, '/api/app')
                    ? '{"name": "wud", "version": "8.1.1"}'
                    : '[{"id": "c1", "name": "web.1.t1"}, "ignoré"]';
            }
        );

        $this->assertSame('8.1.1', $client->version());
        $this->assertSame([['id' => 'c1', 'name' => 'web.1.t1']], $client->containers());

        $this->assertSame('https://wud.exemple.test:3000/api/app', $calls[0][0]);
        $this->assertSame('https://wud.exemple.test:3000/api/containers', $calls[1][0]);
        $this->assertSame('Basic ' . base64_encode('lecteur:' . self::PASSWORD), $calls[1][1]['Authorization']);
    }

    public function testNoAuthorizationHeaderWithoutUser(): void
    {
        $headers = [];
        $client = new WudClient('https://wud.exemple.test', '', '', static function (
            string $url,
            array $query,
            array $sent
        ) use (&$headers): string {
            $headers = $sent;
            return '[]';
        });

        $this->assertSame([], $client->containers());
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function errorProvider(): iterable
    {
        yield '401' => ['HTTP 401 returned for "https://wud.exemple.test/api/app".', WudException::UNAUTHORIZED];
        yield '403' => ['HTTP 403 returned for "x".', WudException::FORBIDDEN];
        yield '404' => ['HTTP 404 returned for "x".', WudException::NOT_FOUND];
        yield '500' => ['HTTP 500 returned for "x".', WudException::SERVER_ERROR];
        yield 'réseau privé' => ['Host "10.0.0.5" is blocked for "https://wud.exemple.test/".',
            WudException::PRIVATE_NETWORK];
        yield 'délai' => ['Idle timeout reached for "https://wud.exemple.test/api/app".', WudException::UNREACHABLE];
    }

    #[DataProvider('errorProvider')]
    public function testErrorsBecomeStableCodesWithoutPassword(string $message, string $code): void
    {
        $transport = static function () use ($message): string {
            throw new \RuntimeException($message . ' ' . self::PASSWORD);
        };
        $client = new WudClient('https://wud.exemple.test', 'lecteur', self::PASSWORD, $transport);

        try {
            $client->containers();
            $this->fail('Exception attendue');
        } catch (WudException $e) {
            $this->assertSame($code, $e->getMessage());
            $this->assertStringNotContainsString(self::PASSWORD, $e->getMessage());
        }
    }

    public function testUnexpectedAnswers(): void
    {
        $html = new WudClient('https://wud.exemple.test', '', '', static fn (): string => '<html>');
        try {
            $html->containers();
            $this->fail('Exception attendue');
        } catch (WudException $e) {
            $this->assertSame(WudException::UNEXPECTED, $e->getMessage());
        }

        // Un objet là où WUD renvoie une liste de conteneurs.
        $object = new WudClient('https://wud.exemple.test', '', '', static fn (): string => '{"id": "c1"}');
        $this->expectException(WudException::class);
        $this->expectExceptionMessage(WudException::UNEXPECTED);
        $object->containers();
    }
}
