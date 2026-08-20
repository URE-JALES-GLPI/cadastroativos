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
    return true;
}

function plugin_cadastroativos_uninstall(): bool
{
    PluginCadastroativosProfile::uninstall();
    return true;
}
