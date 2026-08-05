<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

define('PLUGIN_CADASTROATIVOS_RIGHT', 'plugin_cadastroativos_use');

function plugin_cadastroativos_install(): bool
{
    // O GLPI carrega automaticamente inc/profile.class.php pelo autoload legado
    PluginCadastroativosProfile::install();
    return true;
}

function plugin_cadastroativos_uninstall(): bool
{
    PluginCadastroativosProfile::uninstall();
    return true;
}
