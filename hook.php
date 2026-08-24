<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

define('PLUGIN_CADASTROATIVOS_RIGHT',       'plugin_cadastroativos_use');
define('PLUGIN_CADASTROATIVOS_RIGHT_INFRA', 'plugin_cadastroativos_infra');
define('PLUGIN_CADASTROATIVOS_RIGHT_AV',    'plugin_cadastroativos_av');
define('PLUGIN_CADASTROATIVOS_RIGHT_IMPORT', 'plugin_cadastroativos_import');

function plugin_cadastroativos_install(): bool
{
    PluginCadastroativosProfile::install();
    plugin_cadastroativos_ensureImportTables();
    return true;
}

function plugin_cadastroativos_uninstall(): bool
{
    PluginCadastroativosProfile::uninstall();
    return true;
}

/**
 * Cria tabela de historico de importacoes para controle de revert.
 */
function plugin_cadastroativos_ensureImportTables(): void
{
    global $DB;
    if (!$DB->tableExists('glpi_plugin_cadastroativos_imports')) {
        $query = "CREATE TABLE `glpi_plugin_cadastroativos_imports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `users_id` int(11) NOT NULL,
            `entities_id` int(11) NOT NULL,
            `date_creation` datetime NOT NULL,
            `filename` varchar(255) NOT NULL DEFAULT '',
            `total_rows` int(11) NOT NULL DEFAULT 0,
            `importados` int(11) NOT NULL DEFAULT 0,
            `deletados` int(11) NOT NULL DEFAULT 0,
            `pulados` int(11) NOT NULL DEFAULT 0,
            `is_reverted` tinyint(1) NOT NULL DEFAULT 0,
            `date_reverted` datetime DEFAULT NULL,
            `reverted_by` int(11) DEFAULT NULL,
            `created_ids` text,
            `deleted_ids` text,
            `blanks_info` text,
            `errors` text,
            PRIMARY KEY (`id`),
            KEY `users_id` (`users_id`),
            KEY `entities_id` (`entities_id`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->doQuery($query);
    } else {
        // Migracao leve: garante colunas caso venha de versao anterior
        $cols = [
            'deletados' => "ALTER TABLE `glpi_plugin_cadastroativos_imports` ADD COLUMN `deletados` int(11) NOT NULL DEFAULT 0 AFTER `importados`",
            'is_reverted' => "ALTER TABLE `glpi_plugin_cadastroativos_imports` ADD COLUMN `is_reverted` tinyint(1) NOT NULL DEFAULT 0 AFTER `pulados`",
            'date_reverted' => "ALTER TABLE `glpi_plugin_cadastroativos_imports` ADD COLUMN `date_reverted` datetime DEFAULT NULL AFTER `is_reverted`",
            'reverted_by' => "ALTER TABLE `glpi_plugin_cadastroativos_imports` ADD COLUMN `reverted_by` int(11) DEFAULT NULL AFTER `date_reverted`",
            'created_ids' => "ALTER TABLE `glpi_plugin_cadastroativos_imports` ADD COLUMN `created_ids` text AFTER `reverted_by`",
            'deleted_ids' => "ALTER TABLE `glpi_plugin_cadastroativos_imports` ADD COLUMN `deleted_ids` text AFTER `created_ids`",
            'blanks_info' => "ALTER TABLE `glpi_plugin_cadastroativos_imports` ADD COLUMN `blanks_info` text AFTER `deleted_ids`",
            'errors' => "ALTER TABLE `glpi_plugin_cadastroativos_imports` ADD COLUMN `errors` text AFTER `blanks_info`",
        ];
        foreach ($cols as $col => $sql) {
            if (!$DB->fieldExists('glpi_plugin_cadastroativos_imports', $col)) {
                $DB->doQuery($sql);
            }
        }
    }
}
