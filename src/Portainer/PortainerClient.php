<?php

/**
 * -------------------------------------------------------------------------
 * Containers plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-containers
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

namespace GlpiPlugin\Containers\Portainer;

use GlpiPlugin\Containers\Compat\Http;

/**
 * Lecture seule de l'API Portainer : uniquement des requêtes GET, authentifiées par l'en-tête
 * X-API-Key (jeton d'accès d'un utilisateur Portainer dédié, aux droits minimaux).
 *
 * Le transport est injectable (tests sans réseau) ; par défaut Compat\Http::get(), qui passe par
 * le client HTTP de GLPI (proxy, réseaux privés refusés en GLPI 12 sauf contexte autorisé) et ne
 * suit aucune redirection.
 */
final class PortainerClient
{
    /** @var callable(string, array<string, scalar>, array<string, string>): string */
    private $transport;

    /**
     * @param (callable(string, array<string, scalar>, array<string, string>): string)|null $transport
     */
    public function __construct(
        private readonly string $baseUrl,
        #[\SensitiveParameter]
        private readonly string $apiKey,
        ?callable $transport = null
    ) {
        $this->transport = $transport ?? static fn (string $url, array $query, array $headers): string
            => Http::get($url, $query, $headers, PortainerContext::class);
    }

    /**
     * Environnements Portainer.
     *
     * @return list<array<string, mixed>>
     */
    public function endpoints(): array
    {
        return $this->getList('/api/endpoints');
    }

    /**
     * Stacks de tous les environnements.
     *
     * @return list<array<string, mixed>>
     */
    public function stacks(): array
    {
        return $this->getList('/api/stacks');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function services(int $endpointId): array
    {
        return $this->getList(sprintf('/api/endpoints/%d/docker/services', $endpointId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nodes(int $endpointId): array
    {
        return $this->getList(sprintf('/api/endpoints/%d/docker/nodes', $endpointId));
    }

    /**
     * Tâches dont l'état voulu est « running » (réplicas en cours et leur nœud).
     *
     * @return list<array<string, mixed>>
     */
    public function tasks(int $endpointId): array
    {
        return $this->getList(
            sprintf('/api/endpoints/%d/docker/tasks', $endpointId),
            ['filters' => (string) json_encode(['desired-state' => ['running']])]
        );
    }

    /**
     * Réseaux, pour afficher leur nom plutôt que leur identifiant.
     *
     * @return list<array<string, mixed>>
     */
    public function networks(int $endpointId): array
    {
        return $this->getList(sprintf('/api/endpoints/%d/docker/networks', $endpointId));
    }

    /**
     * @param array<string, scalar> $query
     * @return list<array<string, mixed>>
     * @throws PortainerException
     */
    private function getList(string $path, array $query = []): array
    {
        try {
            $body = ($this->transport)($this->baseUrl . $path, $query, [
                'Accept'    => 'application/json',
                'X-API-Key' => $this->apiKey,
            ]);
        } catch (\Throwable $e) {
            throw PortainerException::fromThrowable($e);
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !array_is_list($data)) {
            throw new PortainerException(PortainerException::UNEXPECTED);
        }

        return array_values(array_filter($data, 'is_array'));
    }
}
