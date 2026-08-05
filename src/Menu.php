<?php

namespace GlpiPlugin\Cadastroativos;

use CommonGLPI;
use Session;

/**
 * Registra a entrada "Cadastro de Ativos" no menu Ferramentas do GLPI 11.
 */
class Menu extends CommonGLPI
{
    /**
     * Direito proprio do plugin que controla visibilidade do menu.
     * O GLPI chama canView() que verifica Session::haveRight($rightname, READ).
     */
    public static $rightname = 'plugin_cadastroativos_use';

    public static function getMenuName(int $nb = 0): string
    {
        return 'Cadastro de Ativos';
    }

    public static function getIcon(): string
    {
        return 'fas fa-clipboard-list';
    }

    /**
     * canView e chamado pelo GLPI para decidir se exibe o item no menu.
     * Retorna true se o usuario tiver READ no direito do plugin.
     */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function getMenuContent(): array
    {
        if (!self::canView()) {
            return [];
        }

        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/cadastroativos/Cadastro',
            'icon'  => self::getIcon(),
        ];
    }
}
