<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Cadastroativos\Menu;

include_once __DIR__ . '/hook.php';

define('PLUGIN_CADASTROATIVOS_VERSION', '1.6.0');
define('PLUGIN_CADASTROATIVOS_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_CADASTROATIVOS_MAX_GLPI_VERSION', '11.99.99');

function plugin_init_cadastroativos(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['cadastroativos'] = true;

    Plugin::registerClass('PluginCadastroativosProfile', [
        'addtabon' => ['Profile'],
    ]);

    $PLUGIN_HOOKS['change_profile']['cadastroativos'] = [
        'PluginCadastroativosProfile', 'changeProfile',
    ];

    // Sempre registra o menu; a visibilidade real e controlada em Menu::canView()/getMenuContent()
    // Evita que o menu suma quando a sessao ainda nao foi recarregada apos edicao de perfil
    // Registra em 'tools' (central) e 'helpdesk' (simplificada) — PROATI é helpdesk e precisa acessar
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['cadastroativos'] = [
        'tools'    => Menu::class,
        'helpdesk' => Menu::class,
    ];
}

function plugin_version_cadastroativos(): array
{
    return [
        'name'         => '[URE] Cadastro de Inventario',
        'version'      => PLUGIN_CADASTROATIVOS_VERSION,
        'author'       => 'Equipe de TI',
        'license'      => 'GPLv3+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CADASTROATIVOS_MIN_GLPI_VERSION,
                'max' => PLUGIN_CADASTROATIVOS_MAX_GLPI_VERSION,
            ],
            'php' => ['min' => '8.1'],
        ],
    ];
}

function plugin_cadastroativos_check_prerequisites(): bool
{
    if (!class_exists('Glpi\\Asset\\AssetDefinitionManager')) {
        echo 'Este plugin requer o modulo de Definicoes de Ativos (Custom Assets) do GLPI 11.';
        return false;
    }
    return true;
}

function plugin_cadastroativos_check_config(bool $verbose = false): bool
{
    return true;
}
