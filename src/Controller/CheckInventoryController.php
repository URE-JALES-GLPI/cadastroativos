<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\AssetManager;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint AJAX: verifica em tempo real se o Numero de Inventario informado
 * ja esta em uso para o Tipo de Ativo e Entidade da sessao atual.
 *
 * A validacao definitiva sempre ocorre no POST do CadastroController.
 * Este endpoint serve apenas para feedback visual imediato ao usuario.
 *
 * Acesso protegido pelo direito proprio do plugin (plugin_cadastroativos_use).
 */
final class CheckInventoryController extends AbstractController
{
    #[Route(
        '/ajax/CheckInventory',
        name: 'cadastroativos_check_inventory',
        methods: ['GET', 'POST']
    )]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight('plugin_cadastroativos_use', READ);

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
