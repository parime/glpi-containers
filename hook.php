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

use GlpiPlugin\Dockerinventory\Install\Installer;

function plugin_dockerinventory_install(): bool
{
    return (new Installer())->install(new Migration(PLUGIN_DOCKERINVENTORY_VERSION));
}

function plugin_dockerinventory_uninstall(): bool
{
    return (new Installer())->uninstall();
}
