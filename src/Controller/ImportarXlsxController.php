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
            // Checkbox: permitir cadastro com numero de inventario/serie em branco
            $allowEmpty = $request->request->get('allow_empty_serial') === '1'
                || $request->request->get('allow_empty') === '1'
                || $request->request->get('permitir_branco') === '1'
                || $request->request->get('permitir_sem_serie') === '1';

            $entityId = AssetManager::getCurrentEntityId();

            if (!$isPreview) {
                $DB->beginTransaction();
            }

            // === FASE 1: varredura e validacao sem gravar, separando brancos vs nao-brancos ===
            $vistosFirst = []; // para detectar duplicado dentro do arquivo (raw e built)
            $errosFirst  = [];
            $puladosFirst = 0;

            /** @var array<string, array> $validBlanksPerType */
            $validBlanksPerType = [];
            /** @var array<int, array> $validNonBlanks */
            $validNonBlanks = [];

            foreach ($parsed['rows'] as $idx => $row) {
                $line = $idx + 2; // linha 1 = cabecalho
                $data = XlsxService::mapRow($row, $parsed['headerMap']);
                if (XlsxService::isEmptyRow($data)) {
                    continue;
                }
                $tipoCheck = trim((string) ($data['tipo_ativo'] ?? ''));
                if ($tipoCheck === '') {
                    continue;
                }

                // Checa duplicado dentro do arquivo ANTES do buildRow (raw)
                $rawNum = trim((string) ($data['numero_inventario'] ?? $data['serial'] ?? ''));
                $rawTipoNorm = XlsxService::typeSystemName($tipoCheck) ?? $tipoCheck;
                $dupKeyRaw = $rawTipoNorm . '|' . $rawNum . '|' . $entityId;
                if ($rawNum !== '' && isset($vistosFirst[$dupKeyRaw])) {
                    // duplicado dentro do arquivo, pula silenciosamente (nao conta como erro)
                    continue;
                }

                $built = XlsxService::buildRow($data, $availableTypes);
                if (!$built['ok']) {
                    $msg = implode(' ', $built['errors']);
                    $isDup = str_contains($msg, 'ja cadastrado');
                    if ($isDup && $onDuplicate === 'skip') {
                        $puladosFirst++;
                        continue;
                    }
                    $errosFirst[] = ['linha' => $line, 'motivo' => $msg];
                    continue;
                }

                $dupKey = $built['tipo_ativo'] . '|' . ($built['input']['otherserial'] ?? '') . '|' . ($built['input']['entities_id'] ?? 0);
                if (($built['input']['otherserial'] ?? '') !== '' && isset($vistosFirst[$dupKey])) {
                    $puladosFirst++;
                    continue;
                }
                // Registra vistos para ambas as chaves
                if ($rawNum !== '') {
                    $vistosFirst[$dupKeyRaw] = $line;
                }
                $vistosFirst[$dupKey] = $line;

                $isBlank = trim((string) ($built['input']['otherserial'] ?? '')) === '';
                if ($isBlank) {
                    $tipo = $built['tipo_ativo'];
                    if (!isset($validBlanksPerType[$tipo])) {
                        $validBlanksPerType[$tipo] = [];
                    }
                    $validBlanksPerType[$tipo][] = ['linha' => $line, 'built' => $built, 'data' => $data];
                } else {
                    $validNonBlanks[] = ['linha' => $line, 'built' => $built, 'data' => $data];
                }
            }

            // === FASE 2: logica de em branco por categoria ===
            $dbBlankPerType = [];
            $allowedPerType = [];
            $sheetBlankPerType = [];
            $blanksSkippedNoPerm = 0;
            $blanksSkippedDiff  = 0;
            $blanksToImport = [];

            if (empty($validBlanksPerType)) {
                // nada em branco, sem acao extra
            } elseif (!$allowEmpty) {
                // Nao permitido: todos os brancos sao pulados
                foreach ($validBlanksPerType as $tipo => $list) {
                    $sheetBlankPerType[$tipo] = count($list);
                    $allowedPerType[$tipo] = 0;
                    $blanksSkippedNoPerm += count($list);
                }
                $puladosFirst += $blanksSkippedNoPerm;
                $validBlanksPerType = [];
            } else {
                // Permitido: calcula diferenca por categoria (sheet - db)
                foreach ($validBlanksPerType as $tipo => $list) {
                    $sheetCount = count($list);
                    $sheetBlankPerType[$tipo] = $sheetCount;
                    $dbCount = AssetManager::countBlankInventory($tipo, $entityId);
                    $dbBlankPerType[$tipo] = $dbCount;
                    $allowed = $sheetCount - $dbCount;
                    if ($allowed < 0) {
                        $allowed = 0;
                    }
                    $allowedPerType[$tipo] = $allowed;
                    // Mantem apenas os primeiros $allowed em ordem de aparecimento
                    $toImport = array_slice($list, 0, $allowed);
                    $skipped  = array_slice($list, $allowed);
                    $blanksToImport = array_merge($blanksToImport, $toImport);
                    $blanksSkippedDiff += count($skipped);
                }
                $puladosFirst += $blanksSkippedDiff;
                // Para pulados por diferenca, validBlanksPerType agora so contem importaveis; mas vamos usar $blanksToImport flatten
            }

            // Se nao permitido, blanksToImport fica vazio; se permitido, ja preenchido
            if ($allowEmpty && !empty($validBlanksPerType) && empty($blanksToImport)) {
                // caso acima ja preencheu; mas garante que se validBlanks ainda tem dados, mas allowed==0, nada a importar
            } elseif ($allowEmpty) {
                // ja calculado
            } else {
                $blanksToImport = [];
            }

            // Total de candidatos a importar (nao-brancos + brancos permitidos)
            $candidateCount = count($validNonBlanks) + count($blanksToImport);

            // === PREVIEW: apenas retorna contagem ===
            if ($isPreview) {
                $importadosPreview = $candidateCount;

                // Monta mensagem auxiliar de brancos
                $blanksInfo = [];
                foreach ($sheetBlankPerType as $tipo => $sheetCnt) {
                    $blanksInfo[$tipo] = [
                        'sheet'   => $sheetCnt,
                        'db'      => $dbBlankPerType[$tipo] ?? 0,
                        'allowed' => $allowedPerType[$tipo] ?? 0,
                        'skipped' => $sheetCnt - ($allowedPerType[$tipo] ?? 0),
                    ];
                }

                return new JsonResponse([
                    'success'    => empty($errosFirst),
                    'preview'    => true,
                    'total'      => count($parsed['rows']),
                    'importados' => $importadosPreview,
                    'pulados'    => $puladosFirst,
                    'erros'      => $errosFirst,
                    'errors'     => array_map(fn($e) => 'Linha ' . $e['linha'] . ': ' . $e['motivo'], $errosFirst),
                    'allow_empty' => $allowEmpty,
                    'blanks_info' => $blanksInfo,
                    'sheet_blank_per_type' => $sheetBlankPerType,
                    'db_blank_per_type'    => $dbBlankPerType,
                ]);
            }

            // === FASE 3: importacao real (gravacao) ===
            $importados = 0;
            $erros = $errosFirst;
            $pulados = $puladosFirst;

            // Importa nao-brancos
            foreach ($validNonBlanks as $entry) {
                $line  = $entry['linha'];
                $built = $entry['built'];

                // Upsert
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

            // Importa brancos permitidos (diferenca)
            foreach ($blanksToImport as $entry) {
                $line  = $entry['linha'];
                $built = $entry['built'];
                try {
                    AssetManager::createAsset($built['tipo_ativo'], $built['input']);
                    $importados++;
                } catch (\RuntimeException $e) {
                    $erros[] = ['linha' => $line, 'motivo' => $e->getMessage()];
                }
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
                    'allow_empty' => $allowEmpty,
                    'blanks_info' => array_map(fn($t) => [
                        'sheet' => $sheetBlankPerType[$t] ?? 0,
                        'db'    => $dbBlankPerType[$t] ?? 0,
                        'allowed' => $allowedPerType[$t] ?? 0,
                    ], array_keys($sheetBlankPerType)),
                ]);
            }
            // Modo skip: mesmo com erros, commit parcial
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
                    'allow_empty' => $allowEmpty,
                    'blanks_info' => $sheetBlankPerType, // simplificado
                ]);
            }

            $DB->commit();

            // Monta blanks_info para resposta de sucesso
            $blanksInfoResp = [];
            foreach ($sheetBlankPerType as $tipo => $sheetCnt) {
                $blanksInfoResp[$tipo] = [
                    'sheet'   => $sheetCnt,
                    'db'      => $dbBlankPerType[$tipo] ?? 0,
                    'allowed' => $allowedPerType[$tipo] ?? 0,
                ];
            }

            return new JsonResponse([
                'success'    => true,
                'total'      => count($parsed['rows']),
                'importados' => $importados,
                'pulados'    => $pulados,
                'erros'      => $erros,
                'allow_empty' => $allowEmpty,
                'blanks_info' => $blanksInfoResp,
            ]);
        } catch (\Throwable $e) {
            if (method_exists($DB, 'inTransaction') && $DB->inTransaction()) {
                $DB->rollBack();
            }
            return new JsonResponse(['success' => false, 'errors' => ['Erro ao processar o arquivo: ' . $e->getMessage()]]);
        }
    }
}
