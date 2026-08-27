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

final class CheckInventoryController extends AbstractController
{
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    #[Route('/ajax/CheckInventory', name: 'cadastroativos_check_inventory', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        $systemName      = $request->query->getString('tipo_ativo');
        $inventoryNumber = trim($request->query->getString('numero_inventario'));
        $availableTypes  = AssetManager::getAvailableTypes();

        if ($systemName === '' || !array_key_exists($systemName, $availableTypes) || $inventoryNumber === '') {
            return new JsonResponse(['duplicado' => false]);
        }

        $entityId  = AssetManager::getCurrentEntityId();
        $duplicado = AssetManager::inventoryNumberExists($systemName, $inventoryNumber, $entityId);

        return new JsonResponse(['duplicado' => $duplicado]);
    }
}
