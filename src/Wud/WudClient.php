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

namespace GlpiPlugin\Dockerinventory\Wud;

use GlpiPlugin\Dockerinventory\Compat\Http;

/**
 * Lecture seule de l'API de What's Up Docker : uniquement GET /api/app (version de WUD) et
 * GET /api/containers (conteneurs suivis et résultat de leur dernière vérification). Les
 * déclencheurs de WUD et ses appels d'écriture ne sont jamais utilisés.
 *
 * Authentification basique optionnelle (utilisateur et mot de passe de WUD). Le transport est
 * injectable (tests sans réseau) ; par défaut Compat\Http::get(), qui passe par le client HTTP de
 * GLPI (proxy, réseaux privés refusés en GLPI 12 sauf contexte autorisé) et ne suit aucune
 * redirection.
 */
final class WudClient
{
    /** @var callable(string, array<string, scalar>, array<string, string>): string */
    private $transport;

    /**
     * @param (callable(string, array<string, scalar>, array<string, string>): string)|null $transport
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username = '',
        #[\SensitiveParameter]
        private readonly string $password = '',
        ?callable $transport = null
    ) {
        $this->transport = $transport ?? static fn (string $url, array $query, array $headers): string
            => Http::get($url, $query, $headers, WudContext::class);
    }

    /**
     * Informations sur WUD (`version` notamment).
     *
     * @return array<string, mixed>
     */
    public function app(): array
    {
        $data = $this->get('/api/app');
        if (array_is_list($data) && $data !== []) {
            throw new WudException(WudException::UNEXPECTED);
        }

        return $data;
    }

    /**
     * Version de WUD, chaîne vide si elle n'est pas donnée.
     */
    public function version(): string
    {
        $version = $this->app()['version'] ?? '';

        return is_scalar($version) ? trim((string) $version) : '';
    }

    /**
     * Conteneurs suivis par WUD.
     *
     * @return list<array<string, mixed>>
     */
    public function containers(): array
    {
        $data = $this->get('/api/containers');
        if (!array_is_list($data)) {
            throw new WudException(WudException::UNEXPECTED);
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @return array<mixed>
     * @throws WudException
     */
    private function get(string $path): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->username !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($this->username . ':' . $this->password);
        }
        try {
            $body = ($this->transport)($this->baseUrl . $path, [], $headers);
        } catch (\Throwable $e) {
            throw WudException::fromThrowable($e);
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new WudException(WudException::UNEXPECTED);
        }

        return $data;
    }
}
