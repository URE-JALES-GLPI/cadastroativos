<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\AssetManager;
use GlpiPlugin\Cadastroativos\Menu;
use GlpiPlugin\Cadastroativos\XlsxService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ImportarXlsxController extends AbstractController
{
    #[Route('/ajax/ImportarXlsx', name: 'cadastroativos_importar_xlsx', methods: ['GET', 'POST'])]
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

        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return new JsonResponse(['success' => false, 'errors' => ['PhpSpreadsheet nao esta disponivel neste GLPI.']]);
        }

        $file = $request->files->get('arquivo');
        if ($file === null || !$file->isValid()) {
            return new JsonResponse(['success' => false, 'errors' => ['Envie um arquivo XLSX valido.']]);
        }
        if ($file->getSize() > XlsxService::MAX_FILE_SIZE) {
            return new JsonResponse(['success' => false, 'errors' => ['Arquivo muito grande (maximo 10 MB).']]);
        }
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            return new JsonResponse(['success' => false, 'errors' => ['Somente arquivos .xlsx sao aceitos.']]);
        }

        global $DB;

        try {
            $parsed = XlsxService::parseRows($file->getPathname());

            if (empty($parsed['headerMap'])) {
                return new JsonResponse(['success' => false, 'errors' => ['Arquivo sem cabecalho reconhecido. Baixe o modelo e preencha as colunas conforme o exemplo.']]);
            }

            $availableTypes = AssetManager::getAvailableTypes();

            $DB->beginTransaction();

            $importados = 0;
            $erros      = [];
            foreach ($parsed['rows'] as $idx => $row) {
                $line = $idx + 2; // linha 1 = cabecalho
                $data = XlsxService::mapRow($row, $parsed['headerMap']);
                if (XlsxService::isEmptyRow($data)) {
                    continue;
                }

                $built = XlsxService::buildRow($data, $availableTypes);
                if (!$built['ok']) {
                    $erros[] = ['linha' => $line, 'motivo' => implode(' ', $built['errors'])];
                    continue;
                }

                try {
                    AssetManager::createAsset($built['tipo_ativo'], $built['input']);
                    $importados++;
                } catch (\RuntimeException $e) {
                    $erros[] = ['linha' => $line, 'motivo' => $e->getMessage()];
                }
            }

            $DB->commit();

            return new JsonResponse([
                'success'    => true,
                'total'      => count($parsed['rows']),
                'importados' => $importados,
                'erros'      => $erros,
            ]);
        } catch (\Throwable $e) {
            if (method_exists($DB, 'inTransaction') && $DB->inTransaction()) {
                $DB->rollBack();
            }
            return new JsonResponse(['success' => false, 'errors' => ['Erro ao processar o arquivo: ' . $e->getMessage()]]);
        }
    }
}