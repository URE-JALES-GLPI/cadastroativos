<?php

/**
 * -------------------------------------------------------------------------
 * Plugin Cadastro de Ativos para GLPI 11
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Cadastroativos\Menu;

include_once __DIR__ . '/hook.php';

define('PLUGIN_CADASTROATIVOS_VERSION', '1.3.0');
define('PLUGIN_CADASTROATIVOS_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_CADASTROATIVOS_MAX_GLPI_VERSION', '11.99.99');

function plugin_init_cadastroativos(): void
{
    global $PLUGIN_HOOKS;

    // CSRF obrigatorio
    $PLUGIN_HOOKS['csrf_compliant']['cadastroativos'] = true;

    // Registra a classe de perfil para aparecer a aba em Administracao > Perfis
    Plugin::registerClass('PluginCadastroativosProfile', [
        'addtabon' => ['Profile'],
    ]);

    // Atualiza sessao ao trocar de perfil
    $PLUGIN_HOOKS['change_profile']['cadastroativos'] = [
        'PluginCadastroativosProfile',
        'changeProfile',
    ];

    // Menu Ferramentas — o GLPI filtra automaticamente pelo $rightname da classe Menu
    // Nao verificamos Session::haveRight aqui pois a sessao pode nao ter
    // os direitos do plugin carregados ainda neste momento do boot
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['cadastroativos'] = [
        'tools' => Menu::class,
    ];

    // CSS e JS
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['cadastroativos']        = 'css/cadastroativos.css';
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['cadastroativos'] = 'js/cadastroativos.js';
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
