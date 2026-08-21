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
            $isPreview   = $request->request->get('preview') === '1' || $request->request->get('dry_run') === '1';
            $onDuplicate = $request->request->get('on_duplicate', 'abort'); // abort | skip
            $doUpdate    = $request->request->get('update_existing') === '1';

            if (!$isPreview) {
                $DB->beginTransaction();
            }

            $importados = 0;
            $pulados    = 0;
            $erros      = [];
            $vistos     = []; // para detectar duplicado dentro do proprio arquivo
            foreach ($parsed['rows'] as $idx => $row) {
                $line = $idx + 2; // linha 1 = cabecalho
                $data = XlsxService::mapRow($row, $parsed['headerMap']);
                if (XlsxService::isEmptyRow($data)) {
                    continue;
                }
                // Ignora linhas em branco da Sueli (219+): se CATEGORIA vazia, considera linha vazia
                $tipoCheck = trim((string) ($data['tipo_ativo'] ?? ''));
                if ($tipoCheck === '') {
                    continue;
                }

                // Se duplicado dentro do arquivo, cadastra apenas o primeiro (pula sem erro) — checa ANTES do buildRow pra nao gerar "ja cadastrado"
                $rawNum = trim((string) ($data['numero_inventario'] ?? $data['serial'] ?? ''));
                $rawTipoNorm = XlsxService::typeSystemName($tipoCheck) ?? $tipoCheck;
                $dupKeyRaw = $rawTipoNorm . '|' . $rawNum . '|' . AssetManager::getCurrentEntityId();
                if ($rawNum !== '' && isset($vistos[$dupKeyRaw])) {
                    continue;
                }

                $built = XlsxService::buildRow($data, $availableTypes);
                if (!$built['ok']) {
                    $msg = implode(' ', $built['errors']);
                    // Se for duplicado e modo skip, apenas pula sem erro
                    $isDup = str_contains($msg, 'ja cadastrado');
                    if ($isDup && $onDuplicate === 'skip') {
                        $pulados++;
                        continue;
                    }
                    $erros[] = ['linha' => $line, 'motivo' => $msg];
                    continue;
                }

                // Se duplicado dentro do arquivo, cadastra apenas o primeiro (pula sem erro)
                $dupKey = $built['tipo_ativo'] . '|' . ($built['input']['otherserial'] ?? '') . '|' . ($built['input']['entities_id'] ?? 0);
                if (($built['input']['otherserial'] ?? '') !== '' && isset($vistos[$dupKey])) {
                    $pulados++;
                    continue;
                }
                $vistos[$dupKey] = $line;

                // Preview: so valida, nao grava
                if ($isPreview) {
                    $importados++;
                    continue;
                }

                // Upsert: se ja existe e flag update_existing, atualiza em vez de criar
                if ($doUpdate && ($built['input']['otherserial'] ?? '') !== '') {
                    $existingId = AssetManager::findAssetIdByInventory($built['tipo_ativo'], $built['input']['otherserial'], $built['input']['entities_id']);
                    if ($existingId > 0) {
                        try {
                            AssetManager::updateAsset($built['tipo_ativo'], $existingId, $built['input']);
                            $importados++;
                            continue;
                        } catch (\RuntimeException $e) {
                            $erros[] = ['linha' => $line, 'motivo' => $e->getMessage()];
                            continue;
                        }
                    }
                }

                try {
                    AssetManager::createAsset($built['tipo_ativo'], $built['input']);
                    $importados++;
                } catch (\RuntimeException $e) {
                    $erros[] = ['linha' => $line, 'motivo' => $e->getMessage()];
                }
            }

            // Preview: nao precisa commit, so retorna validacao
            if ($isPreview) {
                return new JsonResponse([
                    'success'    => empty($erros),
                    'preview'    => true,
                    'total'      => count($parsed['rows']),
                    'importados' => $importados, // quantos importariam
                    'pulados'    => $pulados,
                    'erros'      => $erros,
                    'errors'     => array_map(fn($e) => 'Linha ' . $e['linha'] . ': ' . $e['motivo'], $erros),
                ]);
            }

            // Atomico: se houver 1 erro e modo abort, nao sobe nenhum (rollback)
            if (!empty($erros) && $onDuplicate === 'abort') {
                $DB->rollBack();
                $flat = array_map(fn($e) => 'Linha ' . $e['linha'] . ': ' . $e['motivo'], $erros);
                return new JsonResponse([
                    'success'    => false,
                    'total'      => count($parsed['rows']),
                    'importados' => 0,
                    'pulados'    => $pulados,
                    'erros'      => $erros,
                    'errors'     => $flat,
                ]);
            }
            // Modo skip: mesmo com erros, commit parcial (pulados ja contados)
            if (!empty($erros) && $onDuplicate === 'skip') {
                $DB->commit();
                $flat = array_map(fn($e) => 'Linha ' . $e['linha'] . ': ' . $e['motivo'], $erros);
                return new JsonResponse([
                    'success'    => false,
                    'total'      => count($parsed['rows']),
                    'importados' => $importados,
                    'pulados'    => $pulados,
                    'erros'      => $erros,
                    'errors'     => $flat,
                ]);
            }

            $DB->commit();

            return new JsonResponse([
                'success'    => true,
                'total'      => count($parsed['rows']),
                'importados' => $importados,
                'pulados'    => $pulados,
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