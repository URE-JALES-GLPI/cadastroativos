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
            $onDuplicate = $request->request->get('on_duplicate', 'skip'); // skip | abort
            $doUpdate    = $request->request->get('update_existing') === '1';
            // Sempre permite cadastro em branco: sincroniza por diferenca
            $allowEmpty = true;

            $entityId = AssetManager::getCurrentEntityId();
            $usersId  = (int) Session::getLoginUserID();
            $filename = $file->getClientOriginalName();
            $entityNameRaw = \Dropdown::getDropdownName('glpi_entities', $entityId);
            $entityName = \Html::cleanInputText($entityNameRaw);

            // === Validacao CIE vs entidade atual (bloqueia se planilha pertence a outra entidade) ===
            $sheetCies = [];
            foreach ($parsed['rows'] as $r) {
                $cieVal = trim((string)($r['ciee'] ?? ''));
                if ($cieVal === '') continue;
                $cieNorm = XlsxService::normalizeCie($cieVal);
                if ($cieNorm !== '') $sheetCies[$cieNorm] = true;
            }
            $distinctCies = array_keys($sheetCies);
            if (!empty($distinctCies)) {
                if (count($distinctCies) > 1) {
                    $lista = implode(', ', $distinctCies);
                    return new JsonResponse(['success'=>false,'errors'=>["A planilha contém múltiplas CIEs diferentes: $lista. Cada importação deve conter apenas uma CIE (uma entidade)."]], 400);
                }
                $sheetCie = (string)$distinctCies[0];
                $sheetEntity = XlsxService::getEntityNameByCie($sheetCie) ?? "CIE $sheetCie";
                $expectedCie = XlsxService::getExpectedCieForEntity($entityName);
                if ($expectedCie !== null) {
                    if ($sheetCie !== (string)$expectedCie) {
                        $expectedEntity = XlsxService::getEntityNameByCie($expectedCie) ?? $entityName;
                        $msg = "Divergência de entidade: você está em \"$entityName\" (CIE $expectedCie), mas a planilha é da entidade \"$sheetEntity\" (CIE $sheetCie). "
                             . "Acesse a entidade correta no GLPI (selecione \"$sheetEntity\") e tente novamente. "
                             . "Dica: no topo do GLPI, use o seletor de entidades para mudar para \"$sheetEntity\".";
                        return new JsonResponse(['success'=>false,'errors'=>[$msg],'expected_cie'=>$expectedCie,'expected_entity'=>$expectedEntity,'sheet_cie'=>$sheetCie,'sheet_entity'=>$sheetEntity,'current_entity'=>$entityName,'current_cie'=>$expectedCie], 400);
                    }
                } else {
                    // Entidade atual sem CIE mapeado: valida por nome direto apenas se CIE da planilha for conhecido
                    $sheetEntityRaw = XlsxService::getEntityNameByCie($sheetCie);
                    if ($sheetEntityRaw !== null) {
                        $normCurrent = XlsxService::normalize($entityName);
                        $normSheet = XlsxService::normalize($sheetEntityRaw);
                        $isMatch = false;
                        if ($normSheet !== '' && $normCurrent !== '') {
                            if ($normSheet === $normCurrent || str_contains($normSheet, $normCurrent) || str_contains($normCurrent, $normSheet)) {
                                $isMatch = true;
                            }
                        }
                        if (!$isMatch) {
                            $msg = "Divergência de entidade: você está em \"$entityName\" (sem CIE mapeado), mas a planilha é da entidade \"$sheetEntity\" (CIE $sheetCie). "
                                 . "Acesse a entidade correta no GLPI (selecione \"$sheetEntity\") e tente novamente. ";
                            return new JsonResponse(['success'=>false,'errors'=>[$msg],'sheet_cie'=>$sheetCie,'sheet_entity'=>$sheetEntity,'current_entity'=>$entityName], 400);
                        }
                    }
                }
            }

            if (!$isPreview) {
                $DB->beginTransaction();
            }

            // === FASE 1: varredura e validacao sem gravar, separando brancos vs nao-brancos ===
            $vistosFirst = [];
            $errosFirst  = [];
            $puladosFirst = 0;

            /** @var array<string, array> $validBlanksPerType */
            $validBlanksPerType = [];
            /** @var array<int, array> $validNonBlanks */
            $validNonBlanks = [];

            foreach ($parsed['rows'] as $idx => $row) {
                $line = $idx + 2;
                $data = XlsxService::mapRow($row, $parsed['headerMap']);
                if (XlsxService::isEmptyRow($data)) {
                    continue;
                }
                $tipoCheck = trim((string) ($data['tipo_ativo'] ?? ''));
                if ($tipoCheck === '') {
                    continue;
                }

                $rawNum = trim((string) ($data['numero_inventario'] ?? $data['serial'] ?? ''));
                $rawTipoNorm = XlsxService::typeSystemName($tipoCheck) ?? $tipoCheck;
                $dupKeyRaw = $rawTipoNorm . '|' . $rawNum . '|' . $entityId;
                if ($rawNum !== '' && isset($vistosFirst[$dupKeyRaw])) {
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

            // === FASE 2: sincronizacao de em branco por categoria (sempre ativa) ===
            $dbBlankPerType = [];
            $allowedPerType = [];
            $toDeletePerType = [];
            $sheetBlankPerType = [];
            $blanksSkippedDiff  = 0;
            $blanksToImport = [];
            $totalToDelete = 0;

            // Tipos presentes na planilha (com ou sem branco) para sincronizar também casos onde planilha tem 0 brancos mas tem o tipo
            $sheetTypesPresent = array_keys($validBlanksPerType);
            foreach ($validNonBlanks as $e) {
                $t = $e['built']['tipo_ativo'] ?? null;
                if ($t && !in_array($t, $sheetTypesPresent, true)) {
                    $sheetTypesPresent[] = $t;
                }
            }

            if (empty($sheetTypesPresent)) {
                // nenhum tipo valido na planilha: nada para sincronizar
            } else {
                foreach ($sheetTypesPresent as $tipo) {
                    $list = $validBlanksPerType[$tipo] ?? [];
                    $sheetCount = count($list);
                    $sheetBlankPerType[$tipo] = $sheetCount;
                    $dbCount = AssetManager::countBlankInventory($tipo, $entityId);
                    $dbBlankPerType[$tipo] = $dbCount;

                    if ($sheetCount > $dbCount) {
                        $allowed = $sheetCount - $dbCount;
                        $toDelete = 0;
                        $toImport = array_slice($list, 0, $allowed);
                        $skipped = $sheetCount - $allowed;
                        $blanksToImport = array_merge($blanksToImport, $toImport);
                    } elseif ($sheetCount < $dbCount) {
                        $allowed = 0;
                        $toDelete = $dbCount - $sheetCount;
                        $skipped = $sheetCount;
                    } else {
                        $allowed = 0;
                        $toDelete = 0;
                        $skipped = $sheetCount;
                    }
                    $allowedPerType[$tipo] = $allowed;
                    $toDeletePerType[$tipo] = $toDelete;
                    $blanksSkippedDiff += $skipped;
                    $totalToDelete += $toDelete;
                }
                $puladosFirst += $blanksSkippedDiff;
            }

            $candidateCount = count($validNonBlanks) + count($blanksToImport);

            // === PREVIEW: apenas retorna contagem + o que seria apagado ===
            if ($isPreview) {
                $importadosPreview = $candidateCount;

                $blanksInfo = [];
                foreach ($sheetBlankPerType as $tipo => $sheetCnt) {
                    $blanksInfo[$tipo] = [
                        'sheet'    => $sheetCnt,
                        'db'       => $dbBlankPerType[$tipo] ?? 0,
                        'allowed'  => $allowedPerType[$tipo] ?? 0,
                        'to_delete'=> $toDeletePerType[$tipo] ?? 0,
                        'skipped'  => $sheetCnt - ($allowedPerType[$tipo] ?? 0),
                        'need_delete' => $toDeletePerType[$tipo] ?? 0,
                    ];
                }

                return new JsonResponse([
                    'success'    => empty($errosFirst),
                    'preview'    => true,
                    'total'      => count($parsed['rows']),
                    'importados' => $importadosPreview,
                    'deletados'  => $totalToDelete,
                    'pulados'    => $puladosFirst,
                    'erros'      => $errosFirst,
                    'errors'     => array_map(fn($e) => 'Linha ' . $e['linha'] . ': ' . $e['motivo'], $errosFirst),
                    'allow_empty' => true,
                    'blanks_info' => $blanksInfo,
                    'sheet_blank_per_type' => $sheetBlankPerType,
                    'db_blank_per_type'    => $dbBlankPerType,
                    'to_delete_per_type'   => $toDeletePerType,
                ]);
            }

            // === FASE 3: importacao real (gravacao) ===
            $importados = 0;
            $erros = $errosFirst;
            $pulados = $puladosFirst;
            $createdIdsPerType = [];
            $deletedIdsPerType = [];
            $deletadosCount = 0;

            // 3a: apagar excedentes (sincroniza para bater diferenca)
            foreach ($toDeletePerType as $tipo => $needDelete) {
                if ($needDelete <= 0) continue;
                $ids = AssetManager::getBlankAssetIds($tipo, $entityId, $needDelete);
                $deletedForTipo = [];
                foreach ($ids as $id) {
                    $ok = AssetManager::softDeleteAsset($tipo, $id);
                    if ($ok) {
                        $deletedForTipo[] = $id;
                        $deletadosCount++;
                    }
                }
                if (!empty($deletedForTipo)) {
                    $deletedIdsPerType[$tipo] = $deletedForTipo;
                }
                // Se não conseguiu deletar todos por algum motivo, contabiliza diferenca mas segue
            }

            // 3b: importa nao-brancos
            foreach ($validNonBlanks as $entry) {
                $line  = $entry['linha'];
                $built = $entry['built'];

                if ($doUpdate && ($built['input']['otherserial'] ?? '') !== '') {
                    $existingId = AssetManager::findAssetIdByInventory($built['tipo_ativo'], $built['input']['otherserial'], $built['input']['entities_id']);
                    if ($existingId > 0) {
                        try {
                            AssetManager::updateAsset($built['tipo_ativo'], $existingId, $built['input']);
                            $importados++;
                            // update não gera created_id novo; não rastreia para revert
                            continue;
                        } catch (\RuntimeException $e) {
                            $erros[] = ['linha' => $line, 'motivo' => $e->getMessage()];
                            continue;
                        }
                    }
                }

                try {
                    $newId = AssetManager::createAsset($built['tipo_ativo'], $built['input']);
                    $importados++;
                    $tipo = $built['tipo_ativo'];
                    if (!isset($createdIdsPerType[$tipo])) $createdIdsPerType[$tipo] = [];
                    $createdIdsPerType[$tipo][] = $newId;
                } catch (\RuntimeException $e) {
                    $erros[] = ['linha' => $line, 'motivo' => $e->getMessage()];
                }
            }

            // 3c: importa brancos permitidos (diferenca)
            foreach ($blanksToImport as $entry) {
                $line  = $entry['linha'];
                $built = $entry['built'];
                try {
                    $newId = AssetManager::createAsset($built['tipo_ativo'], $built['input']);
                    $importados++;
                    $tipo = $built['tipo_ativo'];
                    if (!isset($createdIdsPerType[$tipo])) $createdIdsPerType[$tipo] = [];
                    $createdIdsPerType[$tipo][] = $newId;
                } catch (\RuntimeException $e) {
                    $erros[] = ['linha' => $line, 'motivo' => $e->getMessage()];
                }
            }

            // Atomico: se houver erro e modo abort, rollback tudo
            if (!empty($erros) && $onDuplicate === 'abort') {
                $DB->rollBack();
                $flat = array_map(fn($e) => 'Linha ' . $e['linha'] . ': ' . $e['motivo'], $erros);
                return new JsonResponse([
                    'success'    => false,
                    'total'      => count($parsed['rows']),
                    'importados' => 0,
                    'deletados'  => 0,
                    'pulados'    => $pulados,
                    'erros'      => $erros,
                    'errors'     => $flat,
                    'allow_empty' => true,
                    'blanks_info' => array_map(fn($t) => [
                        'sheet' => $sheetBlankPerType[$t] ?? 0,
                        'db'    => $dbBlankPerType[$t] ?? 0,
                        'allowed' => $allowedPerType[$t] ?? 0,
                        'to_delete' => $toDeletePerType[$t] ?? 0,
                    ], array_keys($sheetBlankPerType)),
                ]);
            }
            if (!empty($erros) && $onDuplicate === 'skip') {
                $DB->commit();
                // mesmo com erros, grava historico parcial
                $blanksInfoResp = [];
                foreach ($sheetBlankPerType as $tipo => $sheetCnt) {
                    $blanksInfoResp[$tipo] = [
                        'sheet'   => $sheetCnt,
                        'db'      => $dbBlankPerType[$tipo] ?? 0,
                        'allowed' => $allowedPerType[$tipo] ?? 0,
                        'to_delete' => $toDeletePerType[$tipo] ?? 0,
                        'skipped' => $sheetCnt - ($allowedPerType[$tipo] ?? 0),
                    ];
                }
                // Historico parcial
                try {
                    AssetManager::insertImportHistory([
                        'users_id'      => $usersId,
                        'entities_id'   => $entityId,
                        'date_creation' => date('Y-m-d H:i:s'),
                        'filename'      => $filename,
                        'total_rows'    => count($parsed['rows']),
                        'importados'    => $importados,
                        'deletados'     => $deletadosCount,
                        'pulados'       => $pulados,
                        'is_reverted'   => 0,
                        'created_ids'   => json_encode($createdIdsPerType, JSON_UNESCAPED_UNICODE),
                        'deleted_ids'   => json_encode($deletedIdsPerType, JSON_UNESCAPED_UNICODE),
                        'blanks_info'   => json_encode($blanksInfoResp, JSON_UNESCAPED_UNICODE),
                        'errors'        => json_encode($erros, JSON_UNESCAPED_UNICODE),
                    ]);
                } catch (\Throwable $e) {}
                $flat = array_map(fn($e) => 'Linha ' . $e['linha'] . ': ' . $e['motivo'], $erros);
                return new JsonResponse([
                    'success'    => false,
                    'total'      => count($parsed['rows']),
                    'importados' => $importados,
                    'deletados'  => $deletadosCount,
                    'pulados'    => $pulados,
                    'erros'      => $erros,
                    'errors'     => $flat,
                    'allow_empty' => true,
                    'blanks_info' => $blanksInfoResp,
                    'created_ids' => $createdIdsPerType,
                    'deleted_ids' => $deletedIdsPerType,
                ]);
            }

            $DB->commit();

            $blanksInfoResp = [];
            foreach ($sheetBlankPerType as $tipo => $sheetCnt) {
                $blanksInfoResp[$tipo] = [
                    'sheet'   => $sheetCnt,
                    'db'      => $dbBlankPerType[$tipo] ?? 0,
                    'allowed' => $allowedPerType[$tipo] ?? 0,
                    'to_delete' => $toDeletePerType[$tipo] ?? 0,
                    'skipped' => $sheetCnt - ($allowedPerType[$tipo] ?? 0),
                ];
            }

            $importId = 0;
            try {
                $importId = AssetManager::insertImportHistory([
                    'users_id'      => $usersId,
                    'entities_id'   => $entityId,
                    'date_creation' => date('Y-m-d H:i:s'),
                    'filename'      => $filename,
                    'total_rows'    => count($parsed['rows']),
                    'importados'    => $importados,
                    'deletados'     => $deletadosCount,
                    'pulados'       => $pulados,
                    'is_reverted'   => 0,
                    'created_ids'   => json_encode($createdIdsPerType, JSON_UNESCAPED_UNICODE),
                    'deleted_ids'   => json_encode($deletedIdsPerType, JSON_UNESCAPED_UNICODE),
                    'blanks_info'   => json_encode($blanksInfoResp, JSON_UNESCAPED_UNICODE),
                    'errors'        => json_encode($erros, JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable $e) {}

            return new JsonResponse([
                'success'    => true,
                'total'      => count($parsed['rows']),
                'importados' => $importados,
                'deletados'  => $deletadosCount,
                'pulados'    => $pulados,
                'erros'      => $erros,
                'allow_empty' => true,
                'blanks_info' => $blanksInfoResp,
                'created_ids' => $createdIdsPerType,
                'deleted_ids' => $deletedIdsPerType,
                'import_id'  => $importId,
            ]);
        } catch (\Throwable $e) {
            if (method_exists($DB, 'inTransaction') && $DB->inTransaction()) {
                $DB->rollBack();
            }
            return new JsonResponse(['success' => false, 'errors' => ['Erro ao processar o arquivo: ' . $e->getMessage()]]);
        }
    }
}
