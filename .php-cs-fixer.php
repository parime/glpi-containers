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

// Style PSR-12 (lancé par la CI officielle GLPI : php-cs-fixer check).
return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                      => true,
        'array_syntax'                => ['syntax' => 'short'],
        'no_unused_imports'           => true,
        'ordered_imports'             => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'single_import_per_statement' => true,
        'no_trailing_whitespace'      => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude(['vendor', 'node_modules', '.github'])
            ->ignoreDotFiles(false)
            ->notPath('.phpstan-bootstrap.php')
            ->ignoreVCS(true)
    )
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
