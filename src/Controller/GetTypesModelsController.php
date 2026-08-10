<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\AssetManager;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetTypesModelsController extends AbstractController
{
    #[Route('/ajax/GetTypesModels', name: 'cadastroativos_get_types_models', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        $systemName     = $request->query->getString('tipo_ativo');
        $availableTypes = AssetManager::getAvailableTypes();

        if ($systemName === '' || !array_key_exists($systemName, $availableTypes)) {
            return new JsonResponse(['types' => [], 'models' => []]);
        }

        $definition = AssetManager::getDefinition($systemName);
        if ($definition === null) {
            return new JsonResponse(['types' => [], 'models' => []]);
        }

        $definitionId = (int) $definition->getID();

        global $DB;

        $types = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_assets_assettypes',
            'WHERE'  => ['assets_assetdefinitions_id' => $definitionId],
            'ORDER'  => 'name ASC',
        ]) as $row) {
            $types[] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }

        $models = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_assets_assetmodels',
            'WHERE'  => ['assets_assetdefinitions_id' => $definitionId],
            'ORDER'  => 'name ASC',
        ]) as $row) {
            $models[] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }

        return new JsonResponse(['types' => $types, 'models' => $models]);
    }
}
