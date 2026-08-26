<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DebugAjaxController extends AbstractController
{
    // Rota AJAX — mais confiável que /debug em algumas instalações (cache de rotas)
    // Acesso: /glpi/plugins/cadastroativos/ajax/Debug  ou /glpi/plugins/cadastroativos/ajax/Debug?json=1
    #[Route('/ajax/Debug', name: 'cadastroativos_debug_ajax', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        $pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        $profileName = $_SESSION['glpiactiveprofile']['name'] ?? 'N/A';
        $activeEntity = (int) Session::getActiveEntity();
        $loginUser = (int) Session::getLoginUserID();

        $sessionRights = [
            'plugin_cadastroativos_use'    => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_use'] ?? 'NOT SET',
            'plugin_cadastroativos_infra'  => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_infra'] ?? 'NOT SET',
            'plugin_cadastroativos_av'     => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_av'] ?? 'NOT SET',
            'plugin_cadastroativos_import' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_import'] ?? 'NOT SET',
        ];

        $haveRightCheck = [
            'use'    => Session::haveRight('plugin_cadastroativos_use', READ) ? 'YES' : 'NO',
            'infra'  => Session::haveRight('plugin_cadastroativos_infra', READ) ? 'YES' : 'NO',
            'av'     => Session::haveRight('plugin_cadastroativos_av', READ) ? 'YES' : 'NO',
            'import' => Session::haveRight('plugin_cadastroativos_import', READ) ? 'YES' : 'NO',
        ];

        $dbRights = [];
        $dbError = null;
        try {
            if ($DB && $DB->tableExists('glpi_profilerights') && $pid > 0) {
                $iter = $DB->request([
                    'SELECT' => ['name', 'rights'],
                    'FROM'   => 'glpi_profilerights',
                    'WHERE'  => [
                        'profiles_id' => $pid,
                        'name'        => ['IN', ['plugin_cadastroativos_use', 'plugin_cadastroativos_infra', 'plugin_cadastroativos_av', 'plugin_cadastroativos_import']],
                    ],
                ]);
                foreach ($iter as $row) {
                    $dbRights[$row['name']] = (int) $row['rights'];
                }
                if (empty($dbRights)) {
                    $dbError = 'Nenhuma linha em glpi_profilerights para este pid. Use /plugins/cadastroativos/front/debug.php?fix=self';
                }
            }
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        $menuCanView = 'UNKNOWN';
        try { $menuCanView = \GlpiPlugin\Cadastroativos\Menu::canView() ? 'YES' : 'NO'; } catch (\Throwable $e) { $menuCanView = 'ERR'; }

        return new JsonResponse([
            'pid' => $pid,
            'profileName' => $profileName,
            'loginUser' => $loginUser,
            'activeEntity' => $activeEntity,
            'sessionRights' => $sessionRights,
            'haveRightCheck' => $haveRightCheck,
            'dbRights' => $dbRights,
            'dbError' => $dbError,
            'menuCanView' => $menuCanView,
            'hint' => 'Acesse /plugins/cadastroativos/front/debug.php para diagnóstico visual e botão de correção. Se for PROATI e dbRights vazio, use ?fix=self',
        ]);
    }
}
