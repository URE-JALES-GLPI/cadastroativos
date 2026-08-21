<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\Menu;
use GlpiPlugin\Cadastroativos\XlsxService;
use Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DownloadModeloController extends AbstractController
{
    #[Route('/ModeloXlsx', name: 'cadastroativos_modelo_xlsx', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        if (!Menu::canView()) {
            return new Response('Acesso negado.', 403);
        }

        if (!Session::haveRight(PLUGIN_CADASTROATIVOS_RIGHT_IMPORT, READ)) {
            return new Response('Acesso negado.', 403);
        }

        // Se existir modelo customizado em /modelo/modelo.xlsx, serve ele (ex: modelo Sueli)
        $customPaths = [
            dirname(__DIR__, 2) . '/modelo/modelo.xlsx',
            dirname(__DIR__, 2) . '/modelo/modelo_sueli.xlsx',
        ];
        foreach ($customPaths as $customPath) {
            if (is_file($customPath) && is_readable($customPath)) {
                $response = new BinaryFileResponse($customPath, 200, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'no-store',
                ]);
                $response->setContentDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    basename($customPath)
                );
                return $response;
            }
        }

        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return new Response('PhpSpreadsheet nao esta disponivel neste GLPI.', 500);
        }

        $spreadsheet = XlsxService::buildTemplate();
        $writer      = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $tmpPath = tempnam(sys_get_temp_dir(), 'ca_modelo_') . '.xlsx';
        $writer->save($tmpPath);

        $response = new BinaryFileResponse($tmpPath, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
        $response->setContentDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'modelo_cadastro_ativos.xlsx'
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }
}