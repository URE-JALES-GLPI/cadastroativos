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

/**
 * Endpoint AJAX para salvar o ativo sem recarregar a pagina.
 * Retorna JSON com {success, errors, id, nome, tipoAtivo}
 */
final class SalvarAtivoController extends AbstractController
{
    #[Route('/ajax/SalvarAtivo', name: 'cadastroativos_salvar', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight('plugin_cadastroativos_use', READ);

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
        $memoriaRam       = trim($request->request->getString('custom_memoria_ram'));
        $armazenamento    = trim($request->request->getString('custom_armazenamento'));
        $tipoStorage      = trim($request->request->getString('custom_tipo_storage'));
        $imei             = trim($request->request->getString('custom_imei'));

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
        if ($modelsId <= 0)        { $errors[] = 'Selecione o Modelo.'; }
        if ($manufacturersId <= 0) { $errors[] = 'Selecione o Fabricante.'; }
        if ($typesId <= 0)         { $errors[] = 'Selecione o Tipo.'; }
        if ($temSerial && $serial === '') {
            $errors[] = 'Preencha o Numero de Serie (voce indicou que o ativo possui).';
        }

        // Unicidade
        if (empty($errors)) {
            if (AssetManager::inventoryNumberExists($tipoAtivo, $numeroInventario, $currentEntityId)) {
                $errors[] = sprintf(
                    'O Numero de Inventario "%s" ja esta em uso nesta entidade para este tipo de ativo.',
                    $numeroInventario
                );
            }
        }

        if (!empty($errors)) {
            return new JsonResponse(['success' => false, 'errors' => $errors]);
        }

        // Monta nome: Modelo + #inventario
        $modelName = Dropdown::getDropdownName('glpi_assets_assetmodels', $modelsId);
        $nomeFinal = $modelName . ' ' . AssetManager::buildAssetName($numeroInventario);

        // Custom fields
        $customFields = [];
        if (in_array($tipoAtivo, ['Celular', 'Desktop'])) {
            if ($memoriaRam)    { $customFields['memoria_ram']   = $memoriaRam; }
            if ($armazenamento) { $customFields['armazenamento'] = $armazenamento; }
        }
        if ($tipoAtivo === 'Desktop' && $tipoStorage) {
            $customFields['tipo_storage'] = $tipoStorage;
        }
        if ($tipoAtivo === 'Celular' && $imei) {
            $customFields['imei'] = $imei;
        }

        $input = [
            'name'                  => $nomeFinal,
            'otherserial'           => $numeroInventario,
            'states_id'             => $statesId,
            'assets_assetmodels_id' => $modelsId,
            'manufacturers_id'      => $manufacturersId,
            'assets_assettypes_id'  => $typesId,
            'serial'                => $serial,
            'entities_id'           => $currentEntityId,
            'is_recursive'          => 0,
        ];
        if (!empty($customFields)) {
            $input['custom_fields'] = json_encode($customFields);
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
