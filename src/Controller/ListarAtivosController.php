<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Cadastroativos\AssetManager;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListarAtivosController extends AbstractController
{
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    #[Route('/ajax/ListarAtivos', name: 'cadastroativos_listar', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        $systemName     = $request->query->getString('tipo_ativo');
        $availableTypes = AssetManager::getAvailableTypes();

        if ($systemName === '' || !array_key_exists($systemName, $availableTypes)) {
            return new JsonResponse(['ativos' => []]);
        }

        global $DB;

        $entityId = AssetManager::getCurrentEntityId();

        if (AssetManager::isLegacyType($systemName)) {
            $iterator = $DB->request([
                'SELECT' => ['id', 'name', 'otherserial', 'phonemodels_id'],
                'FROM'   => 'glpi_phones',
                'WHERE'  => [
                    'entities_id' => $entityId,
                    'is_deleted'  => 0,
                    'is_template' => 0,
                ],
                'ORDER'  => ['id DESC'],
            ]);

            $ativos = [];
            foreach ($iterator as $row) {
                $modelName = '';
                if ((int) $row['phonemodels_id'] > 0) {
                    $modelRow = $DB->request([
                        'SELECT' => ['name'],
                        'FROM'   => 'glpi_phonemodels',
                        'WHERE'  => ['id' => (int) $row['phonemodels_id']],
                    ])->current();
                    $modelName = $modelRow['name'] ?? '';
                }
                $ativos[] = [
                    'id'          => (int) $row['id'],
                    'name'        => $row['name'] ?? '',
                    'otherserial' => $row['otherserial'] ?? '',
                    'modelo'      => $modelName,
                ];
            }

            return new JsonResponse(['ativos' => $ativos]);
        }

        $definition = AssetManager::getDefinition($systemName);
        if ($definition === null) {
            return new JsonResponse(['ativos' => []]);
        }

        $definitionId = (int) $definition->getID();

        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'otherserial', 'assets_assetmodels_id'],
            'FROM'   => 'glpi_assets_assets',
            'WHERE'  => [
                'assets_assetdefinitions_id' => $definitionId,
                'entities_id'               => $entityId,
                'is_deleted'                => 0,
                'is_template'               => 0,
            ],
            'ORDER'  => ['id DESC'],
        ]);

        $ativos = [];
        foreach ($iterator as $row) {
            $modelName = '';
            if ((int) $row['assets_assetmodels_id'] > 0) {
                $modelRow = $DB->request([
                    'SELECT' => ['name'],
                    'FROM'   => 'glpi_assets_assetmodels',
                    'WHERE'  => ['id' => (int) $row['assets_assetmodels_id']],
                ])->current();
                $modelName = $modelRow['name'] ?? '';
            }
            $ativos[] = [
                'id'          => (int) $row['id'],
                'name'        => $row['name'] ?? '',
                'otherserial' => $row['otherserial'] ?? '',
                'modelo'      => $modelName,
            ];
        }

        return new JsonResponse(['ativos' => $ativos]);
    }
}
