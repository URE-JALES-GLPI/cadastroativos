<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DebugController extends AbstractController
{
    #[Route('/debug', name: 'cadastroativos_debug', methods: ['GET'])]
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
                    $dbError = 'Nenhuma linha encontrada em glpi_profilerights para este profiles_id. O perfil pode ter sido criado apos o install e nunca aberto na aba Cadastro de Inventario.';
                }
            } else {
                $dbError = 'DB nao disponivel ou pid=0 ou tabela inexistente';
            }
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        $menuCanView = \GlpiPlugin\Cadastroativos\Menu::canView() ? 'YES' : 'NO';

        // Loga tambem em arquivo para servidor
        $log = [
            'time' => date('Y-m-d H:i:s'),
            'pid' => $pid,
            'profileName' => $profileName,
            'loginUser' => $loginUser,
            'activeEntity' => $activeEntity,
            'sessionRights' => $sessionRights,
            'haveRightCheck' => $haveRightCheck,
            'dbRights' => $dbRights,
            'dbError' => $dbError,
            'menuCanView' => $menuCanView,
            'sessionDump' => $_SESSION['glpiactiveprofile'] ?? null,
        ];
        try {
            $logFile = GLPI_ROOT . '/files/_log/cadastroativos_debug.log';
            @file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);
        } catch (\Throwable $e) {}

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
            'hint' => 'Se dbRights mostra rights=1 mas sessionRights=NOT SET ou 0, a sessao nao foi recarregada. Tente deslogar/logar ou trocar de perfil. Se dbRights vazio, abra Administracao > Perfis > [perfil] > aba Cadastro de Inventario e clique Salvar.',
        ]);
    }
}
