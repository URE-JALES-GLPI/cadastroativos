<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Cadastroativos\Menu;

include_once __DIR__ . '/hook.php';

define('PLUGIN_CADASTROATIVOS_VERSION', '1.4.4');
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

    // Menu aparece se usuario tiver qualquer um dos direitos
    if (
        Session::haveRight(PLUGIN_CADASTROATIVOS_RIGHT, READ) ||
        Session::haveRight(PLUGIN_CADASTROATIVOS_RIGHT_INFRA, READ) ||
        Session::haveRight(PLUGIN_CADASTROATIVOS_RIGHT_AV, READ)
    ) {
        $PLUGIN_HOOKS[Hooks::MENU_TOADD]['cadastroativos'] = [
            'tools' => Menu::class,
        ];
    }
}

function plugin_version_cadastroativos(): array
{
    return [
        'name'         => 'Cadastro de Ativos',
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
