<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\AssetManager;
use Dropdown;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SalvarAtivoController extends AbstractController
{
    #[Route('/ajax/SalvarAtivo', name: 'cadastroativos_salvar', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        $currentEntityId  = AssetManager::getCurrentEntityId();
        $availableTypes   = AssetManager::getAvailableTypes();
        $errors           = [];

        $tipoAtivo        = trim($request->request->getString('tipo_ativo'));
        $numeroInventario = trim($request->request->getString('numero_inventario'));
        $statesId         = $request->request->getInt('states_id');
        $modelsId         = $request->request->getInt('assets_assetmodels_id');
        $manufacturersId  = $request->request->getInt('manufacturers_id');
        $typesId          = $request->request->getInt('assets_assettypes_id');
        $temSerial        = $request->request->getString('tem_serial') === '1';
        $serial           = $temSerial ? trim($request->request->getString('serial')) : '';
        $ambiente         = trim($request->request->getString('custom_ambiente'));
        $memoriaRam       = trim($request->request->getString('custom_memoria_ram'));
        $armazenamento    = trim($request->request->getString('custom_armazenamento'));
        $tipoStorage      = trim($request->request->getString('custom_tipo_storage'));
        $imei             = trim($request->request->getString('custom_imei'));
        $avaliacaoTecnica = trim($request->request->getString('custom_avaliacao_tecnica'));
        $observacao       = trim($request->request->getString('custom_observacao'));

        // Validacoes
        if ($tipoAtivo === '' || !array_key_exists($tipoAtivo, $availableTypes)) {
            $errors[] = 'Selecione um Tipo de Ativo.';
        }
        if ($numeroInventario === '') {
            $errors[] = 'Informe o Numero de Inventario.';
        } elseif (!ctype_digit($numeroInventario)) {
            $errors[] = 'O Numero de Inventario deve conter apenas numeros.';
        }
        if ($statesId <= 0)        { $errors[] = 'Selecione o Status.'; }
        if ($manufacturersId <= 0) { $errors[] = 'Selecione o Fabricante.'; }
        if ($typesId <= 0)         { $errors[] = 'Selecione o Tipo.'; }

        // Modelo nao e obrigatorio para Plataforma de Recarga
        if ($modelsId <= 0 && $tipoAtivo !== 'PlataformadeRecarga') {
            $errors[] = 'Selecione o Modelo.';
        }

        if ($temSerial && $serial === '') {
            $errors[] = 'Preencha o Numero de Serie.';
        }

        // Unicidade
        if (empty($errors)) {
            if (AssetManager::inventoryNumberExists($tipoAtivo, $numeroInventario, $currentEntityId)) {
                $errors[] = sprintf(
                    'O Numero de Inventario "%s" ja esta em uso nesta entidade para este tipo.',
                    $numeroInventario
                );
            }
        }

        if (!empty($errors)) {
            return new JsonResponse(['success' => false, 'errors' => $errors]);
        }

        // Nome: Modelo + #inventario (sem modelo para Plataforma de Recarga)
        if ($tipoAtivo === 'PlataformadeRecarga') {
            $nomeFinal = 'Plataforma de Recarga ' . AssetManager::buildAssetName($numeroInventario);
        } elseif (AssetManager::isLegacyType($tipoAtivo)) {
            global $DB;
            $modelRow  = $DB->request([
                'SELECT' => ['name'],
                'FROM'   => 'glpi_phonemodels',
                'WHERE'  => ['id' => $modelsId],
            ])->current();
            $nomeFinal = ($modelRow['name'] ?? '') . ' ' . AssetManager::buildAssetName($numeroInventario);
        } else {
            $modelName = Dropdown::getDropdownName('glpi_assets_assetmodels', $modelsId);
            $nomeFinal = $modelName . ' ' . AssetManager::buildAssetName($numeroInventario);
        }

        if (AssetManager::isLegacyType($tipoAtivo)) {
            $input = [
                'name'             => $nomeFinal,
                'otherserial'      => $numeroInventario,
                'states_id'        => $statesId,
                'phonemodels_id'   => $modelsId,
                'manufacturers_id' => $manufacturersId,
                'phonetypes_id'    => $typesId,
                'serial'           => $serial,
                'entities_id'      => $currentEntityId,
                'is_recursive'     => 0,
            ];
        } else {
            // Custom fields: o GLPI 11 mapeia campos custom_<system_name> para os IDs
            // das definicoes e monta o JSON custom_fields sozinho.
            $input = [
                'name'                  => $nomeFinal,
                'otherserial'           => $numeroInventario,
                'states_id'             => $statesId,
                'assets_assetmodels_id' => $modelsId, // 0 para PlataformadeRecarga
                'manufacturers_id'      => $manufacturersId,
                'assets_assettypes_id'  => $typesId,
                'serial'                => $serial,
                'entities_id'           => $currentEntityId,
                'is_recursive'          => 0,
            ];
            if ($ambiente !== '')        { $input['custom_ambiente']          = $ambiente; }
            if ($memoriaRam !== '')      { $input['custom_memoria_ram']       = $memoriaRam; }
            if ($armazenamento !== '')   { $input['custom_armazenamento']     = $armazenamento; }
            if ($tipoStorage !== '')     { $input['custom_tipo_storage']      = $tipoStorage; }
            if ($imei !== '')            { $input['custom_imei']              = $imei; }
            if ($avaliacaoTecnica !== ''){ $input['custom_avaliacao_tecnica'] = $avaliacaoTecnica; }
            if ($observacao !== '')      { $input['custom_observacao']        = $observacao; }
        }

        try {
            $newId = AssetManager::createAsset($tipoAtivo, $input);
            return new JsonResponse([
                'success'   => true,
                'id'        => $newId,
                'nome'      => $nomeFinal,
                'tipoAtivo' => $tipoAtivo,
            ]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'errors' => [$e->getMessage()]]);
        }
    }
}
