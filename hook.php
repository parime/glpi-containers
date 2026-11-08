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

use GlpiPlugin\Containers\Install\Installer;

function plugin_containers_install(): bool
{
    return (new Installer())->install(new Migration(PLUGIN_CONTAINERS_VERSION));
}

function plugin_containers_uninstall(): bool
{
    return (new Installer())->uninstall();
}
