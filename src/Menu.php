<?php

namespace GlpiPlugin\Cadastroativos;

use CommonGLPI;
use Session;

class Menu extends CommonGLPI
{
    public static $rightname = 'plugin_cadastroativos_use';

    public static function getMenuName(int $nb = 0): string
    {
        return 'Cadastro de Inventario';
    }

    public static function getIcon(): string
    {
        return 'fas fa-clipboard-list';
    }

    public static function canView(): bool
    {
        // Tenta recarregar direitos da sessao se ainda nao estiverem presentes
        // (cobre caso em que o perfil foi editado e o usuario ainda nao relogou)
        $has = Session::haveRight('plugin_cadastroativos_use', READ)
            || Session::haveRight('plugin_cadastroativos_infra', READ)
            || Session::haveRight('plugin_cadastroativos_av', READ)
            || Session::haveRight('plugin_cadastroativos_import', READ);
        if (!$has && class_exists('PluginCadastroativosProfile') && isset($_SESSION['glpiactiveprofile']['id'])) {
            \PluginCadastroativosProfile::changeProfile();
            $has = Session::haveRight('plugin_cadastroativos_use', READ)
                || Session::haveRight('plugin_cadastroativos_infra', READ)
                || Session::haveRight('plugin_cadastroativos_av', READ)
                || Session::haveRight('plugin_cadastroativos_import', READ);
        }
        return $has;
    }

    public static function getMenuContent(): array
    {
        if (!self::canView()) return [];
        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/cadastroativos/Cadastro',
            'icon'  => self::getIcon(),
        ];
    }
}
