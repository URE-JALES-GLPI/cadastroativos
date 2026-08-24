<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\AssetManager;
use GlpiPlugin\Cadastroativos\Menu;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UltimaImportacaoController extends AbstractController
{
    #[Route('/ajax/UltimaImportacao', name: 'cadastroativos_ultima_importacao', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        if (!Menu::canView()) {
            return new JsonResponse(['success' => false, 'errors' => ['Acesso negado.']], 403);
        }
        if (!Session::haveRight(PLUGIN_CADASTROATIVOS_RIGHT_IMPORT, READ)) {
            return new JsonResponse(['success' => false, 'errors' => ['Acesso negado.']], 403);
        }

        $usersId  = (int) Session::getLoginUserID();
        $entityId = AssetManager::getCurrentEntityId();

        global $DB;
        AssetManager::ensureHistoryTable();
        if (!$DB->tableExists('glpi_plugin_cadastroativos_imports')) {
            return new JsonResponse(['success' => false, 'has_import' => false, 'message' => 'Nenhuma implantação encontrada.']);
        }

        $last = AssetManager::getLastImport($usersId, $entityId);
        if ($last === null) {
            return new JsonResponse(['success' => true, 'has_import' => false, 'message' => 'Nenhuma implantação para reverter.']);
        }

        $createdIds = json_decode($last['created_ids'] ?? '[]', true);
        $deletedIds = json_decode($last['deleted_ids'] ?? '[]', true);
        $blanksInfo = json_decode($last['blanks_info'] ?? '[]', true);
        if (!is_array($createdIds)) $createdIds = [];
        if (!is_array($deletedIds)) $deletedIds = [];
        if (!is_array($blanksInfo)) $blanksInfo = [];

        $totalCreated = 0;
        foreach ($createdIds as $ids) if (is_array($ids)) $totalCreated += count($ids);
        $totalDeleted = 0;
        foreach ($deletedIds as $ids) if (is_array($ids)) $totalDeleted += count($ids);

        return new JsonResponse([
            'success' => true,
            'has_import' => true,
            'import' => [
                'id'            => (int) $last['id'],
                'date_creation' => $last['date_creation'],
                'filename'      => $last['filename'],
                'total_rows'    => (int) $last['total_rows'],
                'importados'    => (int) $last['importados'],
                'deletados'     => (int) $last['deletados'],
                'pulados'       => (int) $last['pulados'],
                'created_count' => $totalCreated,
                'deleted_count' => $totalDeleted,
                'blanks_info'   => $blanksInfo,
                'created_ids'   => $createdIds,
                'deleted_ids'   => $deletedIds,
            ],
        ]);
    }
}
