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

use GlpiPlugin\Dockerinventory\Portainer\UrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, string|null}>
     */
    public static function urlProvider(): iterable
    {
        yield 'https' => ['https://portainer.exemple.test:9443', false, null];
        yield 'https avec chemin' => ['https://outils.exemple.test/portainer', false, null];
        yield 'http refusé' => ['http://portainer.exemple.test:9000', false, UrlPolicy::ERROR_HTTPS_REQUIRED];
        yield 'http autorisé' => ['http://portainer.exemple.test:9000', true, null];
        yield 'identifiants' => ['https://admin:secret@portainer.exemple.test', false, UrlPolicy::ERROR_CREDENTIALS];
        yield 'requête' => ['https://portainer.exemple.test/?x=1', false, UrlPolicy::ERROR_INVALID];
        yield 'fragment' => ['https://portainer.exemple.test/#a', false, UrlPolicy::ERROR_INVALID];
        yield 'autre protocole' => ['ftp://portainer.exemple.test', true, UrlPolicy::ERROR_INVALID];
        yield 'sans protocole' => ['portainer.exemple.test', false, UrlPolicy::ERROR_INVALID];
        yield 'espace' => ['https://portainer.exemple .test', false, UrlPolicy::ERROR_INVALID];
        yield 'vide' => ['', false, UrlPolicy::ERROR_INVALID];
    }

    #[DataProvider('urlProvider')]
    public function testCheck(string $url, bool $allowHttp, ?string $expected): void
    {
        $this->assertSame($expected, UrlPolicy::check($url, $allowHttp));
    }

    public function testNormalizeRemovesSpacesAndTrailingSlash(): void
    {
        $this->assertSame('https://portainer.exemple.test', UrlPolicy::normalize('  https://portainer.exemple.test/ '));
    }
}
