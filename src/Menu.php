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

        // Log para debug - sempre registra
        try {
            $pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
            $logData = [
                'time' => date('Y-m-d H:i:s'),
                'pid' => $pid,
                'profile' => $_SESSION['glpiactiveprofile']['name'] ?? 'N/A',
                'haveRight_use' => Session::haveRight('plugin_cadastroativos_use', READ) ? 1 : 0,
                'haveRight_infra' => Session::haveRight('plugin_cadastroativos_infra', READ) ? 1 : 0,
                'haveRight_av' => Session::haveRight('plugin_cadastroativos_av', READ) ? 1 : 0,
                'haveRight_import' => Session::haveRight('plugin_cadastroativos_import', READ) ? 1 : 0,
                'session_raw' => $_SESSION['glpiactiveprofile'] ?? [],
                'result_initial' => $has ? 1 : 0,
            ];
            @file_put_contents(GLPI_ROOT . '/files/_log/cadastroativos_canview.log', json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {}

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
        try {
            global $DB;
            $pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
            if ($pid > 0 && $DB && $DB->tableExists('glpi_profilerights')) {
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
                        if (class_exists('PluginCadastroativosProfile')) {
                            \PluginCadastroativosProfile::changeProfile();
                        }
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {}

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
