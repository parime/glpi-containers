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

namespace GlpiPlugin\Containers\Install;

use CronTask;
use DBConnection;
use DisplayPreference;
use GlpiPlugin\Containers\Connection;
use GlpiPlugin\Containers\Service;
use Migration;
use ProfileRight;

/**
 * Installation, mise à jour et désinstallation. Tables créées une fois (CREATE TABLE seulement
 * ici), évolutions futures par Migration. La désinstallation retire les données du plugin et tout
 * ce qu'il a écrit dans les tables du cœur (droit, tâche automatique, préférences d'affichage),
 * mais jamais les applications (Appliance) créées pour les stacks : ce sont des objets GLPI que
 * les utilisateurs ont pu compléter et lier.
 */
final class Installer
{
    public const TABLES = [
        'glpi_plugin_containers_connections',
        'glpi_plugin_containers_stacks',
        'glpi_plugin_containers_services',
        'glpi_plugin_containers_nodes',
        'glpi_plugin_containers_services_nodes',
    ];

    public const CRON_NAME = 'portainerimport';

    /** Colonnes affichées par défaut dans la liste des services (options de recherche de Service). */
    private const DEFAULT_COLUMNS = [8, 3, 4, 6, 7, 10, 11];

    public function install(Migration $migration): bool
    {
        $this->createTables();

        $firstInstall = countElementsInTable('glpi_profilerights', ['name' => Service::RIGHTNAME]) === 0;
        ProfileRight::addProfileRights([Service::RIGHTNAME]);
        if ($firstInstall) {
            $this->grantReadToAdministrators();
            $this->installDisplayPreferences();
        }

        CronTask::register(Connection::class, self::CRON_NAME, HOUR_TIMESTAMP, [
            'state'         => CronTask::STATE_WAITING,
            'logs_lifetime' => 30,
        ]);

        $migration->executeMigration();

        return true;
    }

    public function uninstall(): bool
    {
        global $DB;

        CronTask::unregister('containers');
        ProfileRight::deleteProfileRights([Service::RIGHTNAME]);
        $this->deleteCoreRowsOfPluginItemtypes();

        foreach (self::TABLES as $table) {
            $DB->dropTable($table, true);
        }

        return true;
    }

    private function createTables(): void
    {
        global $DB;

        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $sign = DBConnection::getDefaultPrimaryKeySignOption();
        $options = "ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC";

        $definitions = [
            'glpi_plugin_containers_connections' => "
                `id` int {$sign} NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT '',
                `entities_id` int {$sign} NOT NULL DEFAULT '0',
                `is_recursive` tinyint NOT NULL DEFAULT '0',
                `is_active` tinyint NOT NULL DEFAULT '1',
                `url` varchar(255) NOT NULL DEFAULT '',
                `api_key` text,
                `allow_http` tinyint NOT NULL DEFAULT '0',
                `applianceenvironments_id` int {$sign} NOT NULL DEFAULT '0',
                `last_import_date` timestamp NULL DEFAULT NULL,
                `last_import_status` tinyint NOT NULL DEFAULT '0',
                `last_import_report` text,
                `comment` text,
                `date_mod` timestamp NULL DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `entities_id` (`entities_id`),
                KEY `is_recursive` (`is_recursive`),
                KEY `is_active` (`is_active`),
                KEY `applianceenvironments_id` (`applianceenvironments_id`)",
            'glpi_plugin_containers_stacks' => "
                `id` int {$sign} NOT NULL AUTO_INCREMENT,
                `plugin_containers_connections_id` int {$sign} NOT NULL DEFAULT '0',
                `endpoint_uid` int NOT NULL DEFAULT '0',
                `stack_uid` int NOT NULL DEFAULT '0',
                `name` varchar(255) NOT NULL DEFAULT '',
                `appliances_id` int {$sign} NOT NULL DEFAULT '0',
                `is_retired` tinyint NOT NULL DEFAULT '0',
                `last_seen` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`plugin_containers_connections_id`, `stack_uid`),
                KEY `appliances_id` (`appliances_id`)",
            'glpi_plugin_containers_services' => "
                `id` int {$sign} NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT '',
                `entities_id` int {$sign} NOT NULL DEFAULT '0',
                `is_recursive` tinyint NOT NULL DEFAULT '0',
                `plugin_containers_connections_id` int {$sign} NOT NULL DEFAULT '0',
                `endpoint_uid` int NOT NULL DEFAULT '0',
                `endpoint_name` varchar(255) NOT NULL DEFAULT '',
                `service_uid` varchar(64) NOT NULL DEFAULT '',
                `stack_name` varchar(255) NOT NULL DEFAULT '',
                `plugin_containers_stacks_id` int {$sign} NOT NULL DEFAULT '0',
                `appliances_id` int {$sign} NOT NULL DEFAULT '0',
                `image` varchar(255) NOT NULL DEFAULT '',
                `tag` varchar(255) NOT NULL DEFAULT '',
                `digest` varchar(255) NOT NULL DEFAULT '',
                `mode` varchar(32) NOT NULL DEFAULT '',
                `replicas` int DEFAULT NULL,
                `running_replicas` int NOT NULL DEFAULT '0',
                `ports` text,
                `networks` text,
                `secrets` text,
                `is_retired` tinyint NOT NULL DEFAULT '0',
                `date_retired` timestamp NULL DEFAULT NULL,
                `last_seen` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`plugin_containers_connections_id`, `service_uid`),
                KEY `name` (`name`),
                KEY `entities_id` (`entities_id`),
                KEY `is_recursive` (`is_recursive`),
                KEY `plugin_containers_stacks_id` (`plugin_containers_stacks_id`),
                KEY `appliances_id` (`appliances_id`),
                KEY `is_retired` (`is_retired`)",
            'glpi_plugin_containers_nodes' => "
                `id` int {$sign} NOT NULL AUTO_INCREMENT,
                `plugin_containers_connections_id` int {$sign} NOT NULL DEFAULT '0',
                `endpoint_uid` int NOT NULL DEFAULT '0',
                `node_uid` varchar(64) NOT NULL DEFAULT '',
                `hostname` varchar(255) NOT NULL DEFAULT '',
                `role` varchar(32) NOT NULL DEFAULT '',
                `availability` varchar(32) NOT NULL DEFAULT '',
                `state` varchar(32) NOT NULL DEFAULT '',
                `engine_version` varchar(64) NOT NULL DEFAULT '',
                `address` varchar(255) NOT NULL DEFAULT '',
                `computers_id` int {$sign} NOT NULL DEFAULT '0',
                `is_retired` tinyint NOT NULL DEFAULT '0',
                `last_seen` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`plugin_containers_connections_id`, `node_uid`),
                KEY `computers_id` (`computers_id`)",
            'glpi_plugin_containers_services_nodes' => "
                `id` int {$sign} NOT NULL AUTO_INCREMENT,
                `plugin_containers_services_id` int {$sign} NOT NULL DEFAULT '0',
                `plugin_containers_nodes_id` int {$sign} NOT NULL DEFAULT '0',
                `running_tasks` int NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`plugin_containers_services_id`, `plugin_containers_nodes_id`),
                KEY `plugin_containers_nodes_id` (`plugin_containers_nodes_id`)",
        ];

        foreach ($definitions as $table => $columns) {
            if (!$DB->tableExists($table)) {
                $DB->doQuery("CREATE TABLE `{$table}` ({$columns}) {$options}");
            }
        }
    }

    /**
     * Premier accès : lecture des services pour les profils qui administrent déjà la configuration
     * de GLPI (super-administrateur). Les autres profils se règlent dans l'onglet du plugin.
     */
    private function grantReadToAdministrators(): void
    {
        global $DB;

        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => 'config']]) as $row) {
            if (((int) $row['rights'] & UPDATE) === UPDATE) {
                ProfileRight::updateProfileRights((int) $row['profiles_id'], [Service::RIGHTNAME => READ]);
            }
        }
    }

    private function installDisplayPreferences(): void
    {
        global $DB;

        $hasInterface = $DB->fieldExists(DisplayPreference::getTable(), 'interface');
        foreach (self::DEFAULT_COLUMNS as $rank => $num) {
            $DB->insert(DisplayPreference::getTable(), [
                'itemtype' => Service::class,
                'num'      => $num,
                'rank'     => $rank + 1,
                'users_id' => 0,
            ] + ($hasInterface ? ['interface' => 'central'] : []));
        }
    }

    /**
     * Lignes des tables du cœur qui désignent un type d'objet du plugin (préférences d'affichage,
     * recherches enregistrées, historique...) : toutes les colonnes itemtype/itemtype_link.
     */
    private function deleteCoreRowsOfPluginItemtypes(): void
    {
        global $DB;

        $like = addcslashes('GlpiPlugin\\Containers\\', '\\') . '%';
        $columns = $DB->request([
            'SELECT' => ['TABLE_NAME', 'COLUMN_NAME'],
            'FROM'   => 'information_schema.COLUMNS',
            'WHERE'  => [
                'TABLE_SCHEMA' => $DB->dbdefault,
                'COLUMN_NAME'  => ['itemtype', 'itemtype_link'],
                ['NOT' => ['TABLE_NAME' => ['LIKE', 'glpi\_plugin\_%']]],
            ],
        ]);
        foreach ($columns as $column) {
            $DB->delete($column['TABLE_NAME'], [$column['COLUMN_NAME'] => ['LIKE', $like]]);
        }
    }
}
