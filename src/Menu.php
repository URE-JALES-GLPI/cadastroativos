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
        $has = Session::haveRight('plugin_cadastroativos_use', READ)
            || Session::haveRight('plugin_cadastroativos_infra', READ)
            || Session::haveRight('plugin_cadastroativos_av', READ)
            || Session::haveRight('plugin_cadastroativos_import', READ);
        if ($has) {
            return true;
        }

        // Fallback 1: tenta recarregar da sessao (cobre caso perfil editado sem relogar)
        if (class_exists('PluginCadastroativosProfile') && isset($_SESSION['glpiactiveprofile']['id'])) {
            \PluginCadastroativosProfile::changeProfile();
            $has = Session::haveRight('plugin_cadastroativos_use', READ)
                || Session::haveRight('plugin_cadastroativos_infra', READ)
                || Session::haveRight('plugin_cadastroativos_av', READ)
                || Session::haveRight('plugin_cadastroativos_import', READ);
            if ($has) {
                return true;
            }
        }

        // Fallback 2: consulta direta no banco (ignora cache de sessao corrompido)
        // Garante acesso mesmo se $_SESSION ainda nao foi atualizado ou se houve falha no change_profile
        try {
            global $DB;
            $pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
            if ($pid > 0 && $DB && $DB->tableExists('glpi_profilerights')) {
                // Garante que linhas existam (perfis criados apos install)
                if (class_exists('PluginCadastroativosProfile')) {
                    \PluginCadastroativosProfile::addDefaultProfileInfos($pid);
                }
                $iter = $DB->request([
                    'SELECT' => ['name', 'rights'],
                    'FROM'   => 'glpi_profilerights',
                    'WHERE'  => [
                        'profiles_id' => $pid,
                        'name'        => ['IN', ['plugin_cadastroativos_use', 'plugin_cadastroativos_infra', 'plugin_cadastroativos_av', 'plugin_cadastroativos_import']],
                    ],
                ]);
                foreach ($iter as $r) {
                    if (((int) $r['rights'] & READ) === READ) {
                        // Corrige sessao para proximas requisicoes
                        if (class_exists('PluginCadastroativosProfile')) {
                            \PluginCadastroativosProfile::changeProfile();
                        }
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignora e retorna false
        }

        return false;
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
