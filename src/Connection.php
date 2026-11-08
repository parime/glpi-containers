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

namespace GlpiPlugin\Containers;

use CronTask;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Containers\Compat\Base\ConnectionBase;
use GlpiPlugin\Containers\Portainer\PortainerClient;
use GlpiPlugin\Containers\Portainer\PortainerException;
use GlpiPlugin\Containers\Portainer\UrlPolicy;
use GLPIKey;
use Session;

/**
 * Connexion à un serveur Portainer : adresse, clé d'API (chiffrée avec la clé de GLPI, jamais
 * réaffichée), environnement GLPI des applications créées pour ses stacks.
 *
 * Réservé aux administrateurs de la configuration de GLPI (droit « Configuration » en
 * modification), pour la lecture comme pour l'écriture : une connexion donne accès à toute
 * l'infrastructure de conteneurs.
 */
class Connection extends ConnectionBase
{
    public const RIGHTNAME = 'config';
    public const UNDISCLOSED_FIELDS = ['api_key'];

    public const STATUS_NEVER = 0;
    public const STATUS_OK = 1;
    public const STATUS_PARTIAL = 2;
    public const STATUS_ERROR = 3;

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? __('Connexions Portainer', 'containers') : __('Connexion Portainer', 'containers');
    }

    public static function getIcon()
    {
        return 'ti ti-plug-connected';
    }

    public static function canView(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, UPDATE);
    }

    public static function canCreate(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, UPDATE);
    }

    public static function canUpdate(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, UPDATE);
    }

    public static function canDelete(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, UPDATE);
    }

    public static function canPurge(): bool
    {
        return (bool) Session::haveRight(self::RIGHTNAME, UPDATE);
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);

        return $tabs;
    }

    public function rawSearchOptions()
    {
        $options = parent::rawSearchOptions();
        $options[] = [
            'id'         => 3,
            'table'      => static::getTable(),
            'field'      => 'url',
            'name'       => __('Adresse de Portainer', 'containers'),
            'datatype'   => 'string',
        ];
        $options[] = [
            'id'         => 4,
            'table'      => static::getTable(),
            'field'      => 'is_active',
            'name'       => __('Active'),
            'datatype'   => 'bool',
        ];
        $options[] = [
            'id'         => 5,
            'table'      => static::getTable(),
            'field'      => 'last_import_date',
            'name'       => __('Dernier import', 'containers'),
            'datatype'   => 'datetime',
            'massiveaction' => false,
        ];
        $options[] = [
            'id'         => 6,
            'table'      => 'glpi_applianceenvironments',
            'field'      => 'name',
            'name'       => __('Environnement des applications créées', 'containers'),
            'datatype'   => 'dropdown',
        ];
        $options[] = [
            'id'         => 80,
            'table'      => 'glpi_entities',
            'field'      => 'completename',
            'name'       => \Entity::getTypeName(1),
            'datatype'   => 'dropdown',
            'massiveaction' => false,
        ];

        return $options;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $report = json_decode((string) ($this->fields['last_import_report'] ?? ''), true);

        $renderer = TemplateRenderer::getInstance();
        $renderer->display('@containers/connection_form.html.twig', [
            'item'        => $this,
            'params'      => $options,
            'has_api_key' => trim((string) ($this->fields['api_key'] ?? '')) !== '',
        ]);
        if (!$this->isNewItem()) {
            $renderer->display('@containers/connection_actions.html.twig', [
                'item'          => $this,
                'status_label'  => self::statusLabel((int) ($this->fields['last_import_status'] ?? 0)),
                'report'        => is_array($report) ? $report : null,
                'error_labels'  => self::errorLabels(),
                'context_class' => Portainer\PortainerContext::class,
            ]);
        }

        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (trim((string) ($input['api_key'] ?? '')) === '') {
            Session::addMessageAfterRedirect(
                __("La clé d'API Portainer est obligatoire.", 'containers'),
                false,
                ERROR
            );
            return false;
        }

        return $this->prepareInput($input, true);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input, false);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    private function prepareInput(array $input, bool $isNew): array|false
    {
        if (array_key_exists('url', $input) || $isNew) {
            $input['url'] = UrlPolicy::normalize((string) ($input['url'] ?? ''));
            $allowHttp = (bool) ($input['allow_http'] ?? ($this->fields['allow_http'] ?? false));
            $error = UrlPolicy::check($input['url'], $allowHttp);
            if ($error !== null) {
                Session::addMessageAfterRedirect(self::urlErrorLabel($error), false, ERROR);
                return false;
            }
        } elseif (array_key_exists('allow_http', $input) && !(bool) $input['allow_http']) {
            // HTTP retiré : l'adresse enregistrée doit alors être en HTTPS.
            $error = UrlPolicy::check((string) ($this->fields['url'] ?? ''), false);
            if ($error !== null) {
                Session::addMessageAfterRedirect(self::urlErrorLabel($error), false, ERROR);
                return false;
            }
        }

        // Clé laissée vide en modification : la clé enregistrée est conservée.
        $key = trim((string) ($input['api_key'] ?? ''));
        if ($key === '') {
            unset($input['api_key']);
        } else {
            $input['api_key'] = (new GLPIKey())->encrypt($key);
        }
        // Champs tenus par l'import, jamais par le formulaire.
        unset($input['last_import_date'], $input['last_import_status'], $input['last_import_report']);

        return $input;
    }

    public function getDecryptedApiKey(): string
    {
        return (string) (new GLPIKey())->decrypt((string) ($this->fields['api_key'] ?? ''));
    }

    /**
     * Client de lecture de ce serveur Portainer.
     */
    public function getClient(): PortainerClient
    {
        return new PortainerClient((string) $this->fields['url'], $this->getDecryptedApiKey());
    }

    /**
     * « Tester la connexion » : liste des environnements Portainer.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(?PortainerClient $client = null): array
    {
        try {
            $endpoints = ($client ?? $this->getClient())->endpoints();
        } catch (PortainerException $e) {
            return ['ok' => false, 'message' => self::errorLabels()[$e->getMessage()] ?? $e->getMessage()];
        }

        return [
            'ok'      => true,
            'message' => sprintf(
                __('Connexion réussie : %d environnement(s) Portainer visible(s) avec cette clé.', 'containers'),
                count($endpoints)
            ),
        ];
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_OK      => __('Réussi', 'containers'),
            self::STATUS_PARTIAL => __('Partiel', 'containers'),
            self::STATUS_ERROR   => __('Échec', 'containers'),
            default              => __('Jamais lancé', 'containers'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function errorLabels(): array
    {
        return [
            PortainerException::UNAUTHORIZED => __("Clé d'API refusée par Portainer (401).", 'containers'),
            PortainerException::FORBIDDEN => __('Accès refusé par Portainer (403) pour cette clé.', 'containers'),
            PortainerException::NOT_FOUND => __("Adresse introuvable (404) : vérifier l'adresse.", 'containers'),
            PortainerException::NOT_SWARM => __("Cet environnement n'est pas un cluster Swarm.", 'containers'),
            PortainerException::PRIVATE_NETWORK => __('Réseau privé refusé par GLPI (voir le README).', 'containers'),
            PortainerException::UNEXPECTED => __("Réponse inattendue : est-ce bien Portainer ?", 'containers'),
            PortainerException::UNREACHABLE => __('Portainer injoignable (réseau, certificat, délai).', 'containers'),
        ];
    }

    private static function urlErrorLabel(string $error): string
    {
        return match ($error) {
            UrlPolicy::ERROR_HTTPS_REQUIRED => __('HTTPS obligatoire (sauf « Autoriser HTTP »).', 'containers'),
            UrlPolicy::ERROR_CREDENTIALS => __("L'adresse ne doit pas contenir d'identifiants.", 'containers'),
            default => __('Adresse invalide (exemple : https://portainer.exemple.test:9443).', 'containers'),
        };
    }

    /**
     * @return array{description: string}
     */
    public static function cronInfo(string $name): array
    {
        return ['description' => __('Import des stacks et services Docker Swarm depuis Portainer', 'containers')];
    }

    /**
     * Tâche automatique (toutes les heures) : import de chaque connexion active.
     */
    public static function cronPortainerimport(CronTask $task): int
    {
        global $DB;

        $done = 0;
        foreach ($DB->request(['FROM' => static::getTable(), 'WHERE' => ['is_active' => 1]]) as $row) {
            $connection = new self();
            if (!$connection->getFromDB((int) $row['id'])) {
                continue;
            }
            $report = (new Importer())->import($connection);
            $task->addVolume($report['services']);
            $task->log(sprintf(
                __('%1$s : %2$s (%3$d services, %4$d retirés, %5$d nœuds)', 'containers'),
                $connection->fields['name'],
                self::statusLabel($report['status']),
                $report['services'],
                $report['retired'],
                $report['nodes']
            ));
            $done++;
        }

        return $done > 0 ? 1 : 0;
    }
}
