<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\AssetManager;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AddDropdownController extends AbstractController
{
    #[Route('/ajax/AddDropdown', name: 'cadastroativos_add_dropdown', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        $systemName = trim($request->request->getString('tipo_ativo'));
        $campo      = $request->request->getString('campo');
        $nome       = trim($request->request->getString('nome'));

        $availableTypes = AssetManager::getAvailableTypes();
        if ($systemName === '' || !array_key_exists($systemName, $availableTypes)) {
            return new JsonResponse(['success' => false, 'errors' => ['Selecione um Tipo de Ativo.']]);
        }
        if (!in_array($campo, ['assets_assettypes_id', 'assets_assetmodels_id'], true)) {
            return new JsonResponse(['success' => false, 'errors' => ['Campo invalido.']]);
        }
        if ($nome === '') {
            return new JsonResponse(['success' => false, 'errors' => ['Informe o nome.']]);
        }
        if (mb_strlen($nome) > 255) {
            return new JsonResponse(['success' => false, 'errors' => ['Nome muito longo (max. 255 caracteres).']]);
        }

        $definition   = AssetManager::getDefinition($systemName);
        $definitionId = (int) $definition->getID();

        global $DB;

        $table = $campo === 'assets_assettypes_id' ? 'glpi_assets_assettypes' : 'glpi_assets_assetmodels';
        $iterator = $DB->request([
            'COUNT' => 'id',
            'FROM'  => $table,
            'WHERE' => [
                'assets_assetdefinitions_id' => $definitionId,
                'name'                       => $nome,
            ],
        ]);
        $row = $iterator->current();
        if ((int) ($row['COUNT(id)'] ?? 0) > 0) {
            return new JsonResponse(['success' => false, 'errors' => ['Ja existe "' . $nome . '" para este tipo de ativo.']]);
        }

        $input = [
            'assets_assetdefinitions_id' => $definitionId,
            'name'                       => $nome,
        ];

        $fqcn = $campo === 'assets_assettypes_id' ? 'Glpi\Asset\AssetType' : 'Glpi\Asset\AssetModel';
        if (class_exists($fqcn)) {
            $item  = new $fqcn();
            $newId = $item->add($input);
        } else {
            $now = date('Y-m-d H:i:s');
            $input['date_creation'] = $now;
            $input['date_mod']      = $now;
            $DB->insert($table, $input);
            $newId = $DB->insertId();
        }

        if (!$newId) {
            return new JsonResponse(['success' => false, 'errors' => ['Nao foi possivel cadastrar. Verifique o nome informado.']]);
        }

        return new JsonResponse(['success' => true, 'id' => (int) $newId, 'name' => $nome]);
    }
}