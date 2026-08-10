<?php

namespace GlpiPlugin\Cadastroativos;

use CommonGLPI;
use Session;

class Menu extends CommonGLPI
{
    public static $rightname = 'plugin_cadastroativos_use';

    public static function getMenuName(int $nb = 0): string
    {
        return 'Cadastro de Ativos';
    }

    public static function getIcon(): string
    {
        return 'fas fa-clipboard-list';
    }

    public static function canView(): bool
    {
        return Session::haveRight('plugin_cadastroativos_use', READ)
            || Session::haveRight('plugin_cadastroativos_infra', READ)
            || Session::haveRight('plugin_cadastroativos_av', READ);
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
