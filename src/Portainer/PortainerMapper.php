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

namespace GlpiPlugin\Dockerinventory\Portainer;

/**
 * Traduction des réponses de l'API Portainer (et de l'API Docker qu'elle relaie) en valeurs à
 * enregistrer. Fonctions pures, sans GLPI : testées sans base de données.
 *
 * Formes lues (API Docker Engine v1.41+, relayée par /api/endpoints/{id}/docker/...) :
 * - service : ID, Spec.Name, Spec.Labels, Spec.Mode.{Replicated.Replicas|Global},
 *   Spec.TaskTemplate.ContainerSpec.{Image, Secrets[].SecretName}, Spec.TaskTemplate.Networks[].Target,
 *   Endpoint.Ports[] / Spec.EndpointSpec.Ports[] ;
 * - nœud : ID, Description.{Hostname, Engine.EngineVersion}, Spec.{Role, Availability}, Status.{State, Addr} ;
 * - tâche : ServiceID, NodeID, Status.State.
 */
final class PortainerMapper
{
    /** Étiquette posée par « docker stack deploy » (et par Portainer) sur chaque service d'une stack. */
    public const STACK_LABEL = 'com.docker.stack.namespace';

    /** Environnements Portainer de type Docker (1 local, 2 agent, 4 agent Edge) ; les autres : Kubernetes, Azure. */
    public const DOCKER_ENDPOINT_TYPES = [1, 2, 4];

    /** Stack Portainer de type Swarm (2 = Compose sur un hôte seul, 3 = Kubernetes). */
    public const STACK_TYPE_SWARM = 1;

    /**
     * Découpe une référence d'image : « registre:port/projet/image:tag@sha256:… ».
     * Sans tag ni empreinte, Docker utilise « latest » : il est alors noté tel quel.
     *
     * @return array{image: string, tag: string, digest: string}
     */
    public static function parseImage(string $reference): array
    {
        $reference = trim($reference);
        $digest = '';
        $at = strpos($reference, '@');
        if ($at !== false) {
            $digest = substr($reference, $at + 1);
            $reference = substr($reference, 0, $at);
        }
        $tag = '';
        $slash = strrpos($reference, '/');
        $colon = strrpos($reference, ':');
        if ($colon !== false && ($slash === false || $colon > $slash)) {
            $tag = substr($reference, $colon + 1);
            $reference = substr($reference, 0, $colon);
        }
        if ($tag === '' && $digest === '' && $reference !== '') {
            $tag = 'latest';
        }

        return ['image' => $reference, 'tag' => $tag, 'digest' => $digest];
    }

    /**
     * @param array<string, mixed> $service Service tel que renvoyé par /docker/services.
     * @param array<string, string> $networkNames Identifiant de réseau => nom.
     * @param array<string, int> $runningByService Identifiant de service => tâches en cours.
     * @return array{service_uid: string, name: string, stack_name: string, image: string, tag: string,
     *     digest: string, mode: string, replicas: int|null, running_replicas: int, ports: string,
     *     networks: string, secrets: string}|null Null si le service n'a pas d'identifiant.
     */
    public static function mapService(array $service, array $networkNames = [], array $runningByService = []): ?array
    {
        $uid = self::str($service['ID'] ?? null);
        if ($uid === '') {
            return null;
        }
        $spec = self::arr($service['Spec'] ?? null);
        $template = self::arr($spec['TaskTemplate'] ?? null);
        $container = self::arr($template['ContainerSpec'] ?? null);
        $labels = self::arr($spec['Labels'] ?? null);
        $mode = self::arr($spec['Mode'] ?? null);
        $image = self::parseImage(self::str($container['Image'] ?? null));

        $modeName = '';
        $replicas = null;
        if (array_key_exists('Replicated', $mode)) {
            $modeName = 'replicated';
            $replicas = (int) (self::arr($mode['Replicated'])['Replicas'] ?? 0);
        } elseif (array_key_exists('Global', $mode)) {
            $modeName = 'global';
        } elseif ($mode !== []) {
            $modeName = strtolower((string) array_key_first($mode));
        }

        $endpoint = self::arr($service['Endpoint'] ?? null);
        $ports = self::arr($endpoint['Ports'] ?? null)
            ?: self::arr(self::arr($spec['EndpointSpec'] ?? null)['Ports'] ?? null);
        $portList = [];
        foreach ($ports as $port) {
            $port = self::arr($port);
            $published = (int) ($port['PublishedPort'] ?? 0);
            if ($published <= 0) {
                continue;
            }
            $entry = sprintf(
                '%d:%d/%s',
                $published,
                (int) ($port['TargetPort'] ?? 0),
                self::str($port['Protocol'] ?? null) ?: 'tcp'
            );
            if (self::str($port['PublishMode'] ?? null) === 'host') {
                $entry .= ' (host)';
            }
            $portList[] = $entry;
        }

        $networks = self::arr($template['Networks'] ?? null) ?: self::arr($spec['Networks'] ?? null);
        $networkList = [];
        foreach ($networks as $network) {
            $target = self::str(self::arr($network)['Target'] ?? null);
            if ($target !== '') {
                $networkList[] = $networkNames[$target] ?? $target;
            }
        }

        // Noms des secrets seulement : l'API Docker n'expose jamais leur contenu, et rien d'autre
        // que SecretName n'est lu ici.
        $secretList = [];
        foreach (self::arr($container['Secrets'] ?? null) as $secret) {
            $name = self::str(self::arr($secret)['SecretName'] ?? null);
            if ($name !== '') {
                $secretList[] = $name;
            }
        }

        return [
            'service_uid'      => $uid,
            'name'             => self::str($spec['Name'] ?? null) ?: $uid,
            'stack_name'       => self::str($labels[self::STACK_LABEL] ?? null),
            'image'            => $image['image'],
            'tag'              => $image['tag'],
            'digest'           => $image['digest'],
            'mode'             => $modeName,
            'replicas'         => $replicas,
            'running_replicas' => $runningByService[$uid] ?? 0,
            'ports'            => self::join($portList),
            'networks'         => self::join($networkList),
            'secrets'          => self::join($secretList),
        ];
    }

    /**
     * @param array<string, mixed> $node Nœud tel que renvoyé par /docker/nodes.
     * @return array{node_uid: string, hostname: string, role: string, availability: string, state: string,
     *     engine_version: string, address: string}|null
     */
    public static function mapNode(array $node): ?array
    {
        $uid = self::str($node['ID'] ?? null);
        if ($uid === '') {
            return null;
        }
        $description = self::arr($node['Description'] ?? null);
        $spec = self::arr($node['Spec'] ?? null);
        $status = self::arr($node['Status'] ?? null);

        return [
            'node_uid'       => $uid,
            'hostname'       => self::str($description['Hostname'] ?? null),
            'role'           => self::str($spec['Role'] ?? null),
            'availability'   => self::str($spec['Availability'] ?? null),
            'state'          => self::str($status['State'] ?? null),
            'engine_version' => self::str(self::arr($description['Engine'] ?? null)['EngineVersion'] ?? null),
            'address'        => self::str($status['Addr'] ?? null),
        ];
    }

    /**
     * Tâches en cours, par service et par nœud.
     *
     * @param list<mixed> $tasks Tâches renvoyées par /docker/tasks.
     * @return array{services: array<string, int>, placement: array<string, array<string, int>>}
     */
    public static function runningTasks(array $tasks): array
    {
        $services = [];
        $placement = [];
        foreach ($tasks as $task) {
            $task = self::arr($task);
            if (self::str(self::arr($task['Status'] ?? null)['State'] ?? null) !== 'running') {
                continue;
            }
            $service = self::str($task['ServiceID'] ?? null);
            $node = self::str($task['NodeID'] ?? null);
            if ($service === '') {
                continue;
            }
            $services[$service] = ($services[$service] ?? 0) + 1;
            if ($node !== '') {
                $placement[$service][$node] = ($placement[$service][$node] ?? 0) + 1;
            }
        }

        return ['services' => $services, 'placement' => $placement];
    }

    /**
     * @param list<mixed> $networks Réseaux renvoyés par /docker/networks.
     * @return array<string, string> Identifiant => nom.
     */
    public static function networkNames(array $networks): array
    {
        $names = [];
        foreach ($networks as $network) {
            $network = self::arr($network);
            $id = self::str($network['Id'] ?? null);
            $name = self::str($network['Name'] ?? null);
            if ($id !== '' && $name !== '') {
                $names[$id] = $name;
            }
        }

        return $names;
    }

    /**
     * Nom court d'un nom d'hôte (« swarm-node-01.exemple.test » => « swarm-node-01 »).
     */
    public static function shortHostname(string $hostname): string
    {
        $dot = strpos($hostname, '.');

        return $dot === false ? $hostname : substr($hostname, 0, $dot);
    }

    /**
     * @return array<mixed>
     */
    private static function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param list<string> $values
     */
    private static function join(array $values): string
    {
        $values = array_values(array_unique($values));
        sort($values);

        return implode(', ', $values);
    }
}
