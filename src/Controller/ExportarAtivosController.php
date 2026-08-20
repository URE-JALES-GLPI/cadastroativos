<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\AssetManager;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ExportarAtivosController extends AbstractController
{
    #[Route('/ajax/ExportarAtivos', name: 'cadastroativos_exportar', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        $systemName     = $request->query->getString('tipo_ativo');
        $availableTypes = AssetManager::getAvailableTypes();

        if ($systemName === '' || !array_key_exists($systemName, $availableTypes)) {
            return new Response('Tipo de ativo invalido.', 400);
        }

        global $DB;

        $entityId = AssetManager::getCurrentEntityId();

        if (AssetManager::isLegacyType($systemName)) {
            $iterator = $DB->request([
                'SELECT' => [
                    'p.id', 'p.name', 'p.otherserial', 'p.serial',
                    'm.name AS modelo', 'f.name AS fabricante', 's.name AS status',
                ],
                'FROM'   => 'glpi_phones AS p',
                'LEFT JOIN' => [
                    'glpi_phonemodels AS m'   => ['FKEY' => ['m' => 'id', 'p' => 'phonemodels_id']],
                    'glpi_manufacturers AS f' => ['FKEY' => ['f' => 'id', 'p' => 'manufacturers_id']],
                    'glpi_assetstates AS s'   => ['FKEY' => ['s' => 'id', 'p' => 'states_id']],
                ],
                'WHERE'  => [
                    'p.entities_id' => $entityId,
                    'p.is_deleted'  => 0,
                    'p.is_template' => 0,
                ],
                'ORDER'  => ['CAST(p.otherserial AS UNSIGNED)'],
            ]);
        } else {
            $definition = AssetManager::getDefinition($systemName);
            if ($definition === null) {
                return new Response('Tipo de ativo invalido.', 400);
            }
            $definitionId = (int) $definition->getID();

            $iterator = $DB->request([
                'SELECT' => [
                    'a.id', 'a.name', 'a.otherserial', 'a.serial',
                    'm.name AS modelo', 'f.name AS fabricante', 's.name AS status',
                ],
                'FROM'   => 'glpi_assets_assets AS a',
                'LEFT JOIN' => [
                    'glpi_assets_assetmodels AS m' => ['FKEY' => ['m' => 'id', 'a' => 'assets_assetmodels_id']],
                    'glpi_manufacturers AS f'      => ['FKEY' => ['f' => 'id', 'a' => 'manufacturers_id']],
                    'glpi_assetstates AS s'        => ['FKEY' => ['s' => 'id', 'a' => 'states_id']],
                ],
                'WHERE'  => [
                    'a.assets_assetdefinitions_id' => $definitionId,
                    'a.entities_id'               => $entityId,
                    'a.is_deleted'                => 0,
                    'a.is_template'               => 0,
                ],
                'ORDER'  => ['CAST(a.otherserial AS UNSIGNED)'],
            ]);
        }

        $rows = [['Numero Inventario', 'Nome', 'Modelo', 'Fabricante', 'Status', 'Numero Serie']];
        foreach ($iterator as $row) {
            $rows[] = [
                $row['otherserial'] ?? '',
                $row['name'] ?? '',
                $row['modelo'] ?? '',
                $row['fabricante'] ?? '',
                $row['status'] ?? '',
                $row['serial'] ?? '',
            ];
        }

        $csv = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $value) {
                $cells[] = self::csvCell((string) $value);
            }
            $csv .= implode(';', $cells) . "\r\n";
        }

        $fileName = 'ativos_' . strtolower($systemName) . '_' . date('Ymd_His') . '.csv';

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        return $response;
    }

    private static function csvCell(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        if (strpbrk($value, ';",') !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}