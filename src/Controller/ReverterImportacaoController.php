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

final class ReverterImportacaoController extends AbstractController
{
    #[Route('/ajax/ReverterImportacao', name: 'cadastroativos_reverter_importacao', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['success' => false, 'errors' => ['Metodo nao permitido.']], 405);
        }

        Session::checkLoginUser();

        if (!Menu::canView()) {
            return new JsonResponse(['success' => false, 'errors' => ['Acesso negado.']], 403);
        }
        if (!Session::haveRight(PLUGIN_CADASTROATIVOS_RIGHT_IMPORT, READ)) {
            return new JsonResponse(['success' => false, 'errors' => ['Acesso negado.']], 403);
        }

        $confirm  = $request->request->get('confirm');
        $confirm2 = $request->request->get('confirm2');
        // Exige dupla confirmacao
        if ($confirm !== '1' || $confirm2 !== '1') {
            return new JsonResponse(['success' => false, 'errors' => ['É necessário confirmar duas vezes antes de reverter. Confirme novamente.']], 400);
        }

        $usersId  = (int) Session::getLoginUserID();
        $entityId = AssetManager::getCurrentEntityId();

        global $DB;
        AssetManager::ensureHistoryTable();
        if (!$DB->tableExists('glpi_plugin_cadastroativos_imports')) {
            return new JsonResponse(['success' => false, 'errors' => ['Histórico de importações não encontrado.']]);
        }

        $last = AssetManager::getLastImport($usersId, $entityId);
        if ($last === null) {
            return new JsonResponse(['success' => false, 'errors' => ['Nenhuma implantação encontrada para reverter nesta entidade.']]);
        }

        $createdIds = json_decode($last['created_ids'] ?? '[]', true);
        if (!is_array($createdIds)) $createdIds = [];
        $deletedIds = json_decode($last['deleted_ids'] ?? '[]', true);
        if (!is_array($deletedIds)) $deletedIds = [];

        // Conta totais para mensagem
        $totalCreated = 0;
        foreach ($createdIds as $tipo => $ids) {
            if (is_array($ids)) $totalCreated += count($ids);
        }
        $totalDeleted = 0;
        foreach ($deletedIds as $tipo => $ids) {
            if (is_array($ids)) $totalDeleted += count($ids);
        }

        try {
            $DB->beginTransaction();

            $revertedCreated = 0;
            $revertedDeleted = 0;
            $errors = [];

            // Reverte criados: apaga (soft delete)
            foreach ($createdIds as $tipo => $ids) {
                if (!is_array($ids)) continue;
                foreach ($ids as $id) {
                    $id = (int) $id;
                    if ($id <= 0) continue;
                    $ok = AssetManager::softDeleteAsset($tipo, $id);
                    if ($ok) {
                        $revertedCreated++;
                    } else {
                        // tenta purge como fallback silencioso
                        // não conta como erro crítico
                    }
                }
            }

            // Reverte apagados: restaura
            foreach ($deletedIds as $tipo => $ids) {
                if (!is_array($ids)) continue;
                foreach ($ids as $id) {
                    $id = (int) $id;
                    if ($id <= 0) continue;
                    $ok = AssetManager::restoreAsset($tipo, $id);
                    if ($ok) {
                        $revertedDeleted++;
                    }
                }
            }

            // Marca import como revertido
            $DB->update('glpi_plugin_cadastroativos_imports', [
                'is_reverted'   => 1,
                'date_reverted' => date('Y-m-d H:i:s'),
                'reverted_by'   => $usersId,
            ], ['id' => (int) $last['id']]);

            $DB->commit();

            return new JsonResponse([
                'success' => true,
                'message' => "Reversão concluída: {$revertedCreated} ativo(s) criado(s) apagado(s) e {$revertedDeleted} ativo(s) restaurado(s).",
                'reverted_created' => $revertedCreated,
                'reverted_deleted' => $revertedDeleted,
                'import_id' => (int) $last['id'],
            ]);
        } catch (\Throwable $e) {
            if (method_exists($DB, 'inTransaction') && $DB->inTransaction()) {
                $DB->rollBack();
            }
            return new JsonResponse(['success' => false, 'errors' => ['Erro ao reverter: ' . $e->getMessage()]]);
        }
    }
}
