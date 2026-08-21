<?php

namespace GlpiPlugin\Cadastroativos;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class XlsxService
{
    public const MAX_FILE_SIZE = 10485760; // 10 MB

    public const COLUMNS = [
        ['key' => 'tipo_ativo',        'label' => 'Tipo de Ativo',         'required' => true,  'help' => 'Celular, Telefones, Notebook, Tablet, Desktop, Switch, Firewall, Rack de Rede, Nobreak, Televisão, Plataforma de Recarga'],
        ['key' => 'numero_inventario', 'label' => 'Numero de Inventario',   'required' => true,  'help' => 'Somente numeros. Ex: 1, 2, 3, 4...'],
        ['key' => 'status',            'label' => 'Status',                'required' => true,  'help' => 'Nome do status exatamente como cadastrado no GLPI (ex: Em uso, Disponivel).'],
        ['key' => 'fabricante',        'label' => 'Fabricante',            'required' => true,  'help' => 'Nome do fabricante exatamente como cadastrado no GLPI (ex: Dell, Samsung).'],
        ['key' => 'tipo',              'label' => 'Tipo',                  'required' => true,  'help' => 'Nome do tipo do ativo cadastrado no GLPI (ex: Notebook, Smartphone).'],
        ['key' => 'modelo',            'label' => 'Modelo',                'required' => false, 'help' => 'Nome do modelo. Deixe vazio somente para Plataforma de Recarga.'],
        ['key' => 'serial',            'label' => 'Numero de Serie',       'required' => false, 'help' => 'Numero de serie do equipamento (opcional).'],
        ['key' => 'ambiente',          'label' => 'Ambiente',              'required' => false, 'help' => 'Pedagogico ou Administrativo.'],
        ['key' => 'memoria_ram',       'label' => 'Memoria RAM',           'required' => false, 'help' => 'Ex: 4 GB, 8 GB, 16 GB.'],
        ['key' => 'armazenamento',     'label' => 'Armazenamento',         'required' => false, 'help' => 'Ex: 128 GB, 500 GB.'],
        ['key' => 'tipo_storage',      'label' => 'Tipo de Armazenamento', 'required' => false, 'help' => 'HD, SSD ou HD + SSD (utilizado no Desktop).'],
        ['key' => 'imei',              'label' => 'IMEI',                  'required' => false, 'help' => 'IMEI do celular (opcional). Prefira celula em formato de texto.'],
        ['key' => 'avaliacao_tecnica', 'label' => 'Avaliacao Tecnica',     'required' => false, 'help' => 'Bom, Desgaste natural, Mau uso, Dano fisico, Obsoleto, Sem avaliacao.'],
        ['key' => 'observacoes',       'label' => 'Observacoes',           'required' => false, 'help' => 'Informacoes adicionais sobre o ativo.'],
    ];

    private static ?array $HEADER_MAP = null;

    public static function normalize(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = iconv('UTF-8', 'ASCII//TRANSLIT', $v);
        if ($v === false) {
            $v = mb_strtolower(trim($value));
        }
        return preg_replace('/[^a-z0-9]/', '', $v);
    }

    public static function cellString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            if (floor($value) == $value && abs($value) < PHP_INT_MAX) {
                return number_format($value, 0, '', '');
            }
            return (string) $value;
        }
        return (string) $value;
    }

    public static function typeSystemName(string $value): ?string
    {
        $needle = self::normalize($value);
        if ($needle === '') {
            return null;
        }
        foreach (AssetManager::SUPPORTED_TYPES as $systemName => $label) {
            if (self::normalize($systemName) === $needle || self::normalize($label) === $needle) {
                return $systemName;
            }
        }
        return null;
    }

    /**
     * Mapa de cabeçalho: rótulo normalizado (ou coluna) -> chave do campo.
     */
    public static function headerMap(): array
    {
        if (self::$HEADER_MAP === null) {
            $map = [];
            foreach (self::COLUMNS as $col) {
                $map[self::normalize($col['label'])] = $col['key'];
                $map[self::normalize($col['key'])]   = $col['key'];
            }
            $aliases = [
                'numero de serie'   => 'serial',
                'serial'            => 'serial',
                'memoria'           => 'memoria_ram',
                'ram'               => 'memoria_ram',
                'avaliacao'         => 'avaliacao_tecnica',
                'obs'               => 'observacoes',
                'observacao'        => 'observacoes',
                'tipo de armazenamento' => 'tipo_storage',
                'tipo do ativo'     => 'tipo_ativo',
                'numero de patrimonio' => 'numero_inventario',
                'ambiente'          => 'ambiente',
                'fabricante'        => 'fabricante',
                'modelo'            => 'modelo',
                'numerodserie'    => 'numero_inventario',
                'statusdoequipamento' => 'status',
                'categoriadoequipamento' => 'categoria_equipamento',
            ];
            foreach ($aliases as $label => $key) {
                $map[self::normalize($label)] = $key;
            }
            self::$HEADER_MAP = $map;
        }
        return self::$HEADER_MAP;
    }

    /**
     * Lê a planilha ativa e retorna cabeçalhos, mapa de colunas e linhas.
     */
    public static function parseRows(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();

        $highestRow    = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $colCount      = Coordinate::columnIndexFromString($highestColumn);

        $headers   = [];
        $headerMap = [];
        for ($c = 1; $c <= $colCount; $c++) {
            $label = trim(self::cellString($sheet->getCell([$c, 1])->getValue()));
            $headers[$c] = $label;
            if ($label === '') {
                continue;
            }
            $norm = self::normalize($label);
            $key  = self::headerMap()[$norm] ?? null;
            if ($key !== null) {
                $headerMap[$c] = $key;
            }
        }

        $rows = [];
        for ($r = 2; $r <= $highestRow; $r++) {
            $line = [];
            foreach ($headers as $c => $label) {
                $line[$c] = self::cellString($sheet->getCell([$c, $r])->getValue());
            }
            $rows[] = $line;
        }

        return [
            'headers'   => $headers,
            'headerMap' => $headerMap,
            'rows'      => $rows,
        ];
    }

    public static function mapRow(array $row, array $headerMap): array
    {
        $out = [];
        foreach ($row as $c => $value) {
            $key = $headerMap[$c] ?? null;
            if ($key === null) {
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    public static function isEmptyRow(array $data): bool
    {
        foreach ($data as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }
        return true;
    }

    public static function findIdByTable(string $table, string $name, array $extraWhere = []): int
    {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $table,
            'WHERE'  => ['name' => $name] + $extraWhere,
            'LIMIT'  => 1,
        ]);
        $row = $iterator->current();

        return $row ? (int) $row['id'] : 0;
    }

    /**
     * Valida e monta o input de criação de um ativo a partir da linha do XLSX.
     *
     * @return array{ok: bool, errors?: array, input?: array, nome?: string, tipo_ativo?: string}
     */
    public static function buildRow(array $data, array $availableTypes): array
    {
        $errors = [];

        $tipoAtivo = self::typeSystemName($data['tipo_ativo'] ?? '');
        if ($tipoAtivo === null || !array_key_exists($tipoAtivo, $availableTypes)) {
            $errors[] = "Tipo de ativo invalido ou sem permissao: '" . trim((string) ($data['tipo_ativo'] ?? '')) . "'.";
        }

        $numeroInventario = trim((string) ($data['numero_inventario'] ?? ''));
        if ($numeroInventario === '') {
            $errors[] = 'Numero de Inventario obrigatorio.';
        } elseif (!ctype_digit($numeroInventario)) {
            $errors[] = "Numero de Inventario deve conter apenas numeros: '$numeroInventario'.";
        }

        $status     = trim((string) ($data['status'] ?? ''));
        $fabricante = trim((string) ($data['fabricante'] ?? ''));
        $tipo       = trim((string) ($data['tipo'] ?? ''));
        $modelo     = trim((string) ($data['modelo'] ?? ''));

        $statesId = self::findIdByTable('glpi_states', $status, ['is_active' => 1]);
        if ($status === '') {
            $errors[] = 'Status obrigatorio.';
        } elseif ($statesId <= 0) {
            $errors[] = "Status nao encontrado no GLPI: '$status'.";
        }

        $manufacturersId = self::findIdByTable('glpi_manufacturers', $fabricante);
        if ($fabricante === '') {
            $errors[] = 'Fabricante obrigatorio.';
        } elseif ($manufacturersId <= 0) {
            $errors[] = "Fabricante nao encontrado no GLPI: '$fabricante'.";
        }

        $typesId  = 0;
        $modelsId = 0;
        if ($tipoAtivo !== null) {
            $isLegacy   = AssetManager::isLegacyType($tipoAtivo);
            $typesTable = $isLegacy ? 'glpi_phonetypes' : 'glpi_assets_assettypes';
            $modelTable = $isLegacy ? 'glpi_phonemodels' : 'glpi_assets_assetmodels';

            $extraTypes = [];
            $extraModels = [];
            if (!$isLegacy) {
                $definitionId = (int) AssetManager::getDefinition($tipoAtivo)?->getID();
                $extraTypes   = ['assets_assetdefinitions_id' => $definitionId];
                $extraModels  = $extraTypes;
            }

            $typesId = self::findIdByTable($typesTable, $tipo, $extraTypes);
            if ($tipo === '') {
                $errors[] = 'Tipo obrigatorio.';
            } elseif ($typesId <= 0) {
                $errors[] = "Tipo nao encontrado no GLPI para $tipoAtivo: '$tipo'.";
            }

            if ($tipoAtivo === 'PlataformadeRecarga') {
                $modelsId = 0;
            } else {
                $modelsId = self::findIdByTable($modelTable, $modelo, $extraModels);
                if ($modelo === '') {
                    $errors[] = "Modelo obrigatorio para $tipoAtivo.";
                } elseif ($modelsId <= 0) {
                    $errors[] = "Modelo nao encontrado no GLPI: '$modelo'.";
                }
            }
        }

        if (empty($errors)) {
            $entityId = AssetManager::getCurrentEntityId();
            if (AssetManager::inventoryNumberExists($tipoAtivo, $numeroInventario, $entityId)) {
                $errors[] = "Numero de Inventario '$numeroInventario' ja cadastrado nesta entidade para $tipoAtivo.";
            }
        }

        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }

        $entityId = AssetManager::getCurrentEntityId();
        $serial   = trim((string) ($data['serial'] ?? ''));

        if ($tipoAtivo === 'PlataformadeRecarga') {
            $nome = 'Plataforma de Recarga ' . AssetManager::buildAssetName($numeroInventario);
        } else {
            $nome = $modelo . ' ' . AssetManager::buildAssetName($numeroInventario);
        }

        if (AssetManager::isLegacyType($tipoAtivo)) {
            $input = [
                'name'             => $nome,
                'otherserial'      => $numeroInventario,
                'states_id'        => $statesId,
                'phonemodels_id'   => $modelsId,
                'manufacturers_id' => $manufacturersId,
                'phonetypes_id'    => $typesId,
                'serial'           => $serial,
                'entities_id'      => $entityId,
                'is_recursive'     => 0,
            ];
        } else {
            $input = [
                'name'                  => $nome,
                'otherserial'           => $numeroInventario,
                'states_id'             => $statesId,
                'assets_assetmodels_id' => $modelsId,
                'manufacturers_id'      => $manufacturersId,
                'assets_assettypes_id'  => $typesId,
                'serial'                => $serial,
                'entities_id'           => $entityId,
                'is_recursive'          => 0,
            ];

            $custom = [
                'ambiente'           => $data['ambiente'] ?? '',
                'memoria_ram'        => $data['memoria_ram'] ?? '',
                'armazenamento'      => $data['armazenamento'] ?? '',
                'tipo_storage'       => $data['tipo_storage'] ?? '',
                'imei'               => $data['imei'] ?? '',
                'avaliacao_tecnica'  => $data['avaliacao_tecnica'] ?? '',
                'observacao'         => $data['observacoes'] ?? '',
                'categoria_equipamento' => $data['categoria_equipamento'] ?? '',
            ];
            foreach ($custom as $key => $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $input['custom_' . $key] = $value;
                }
            }
        }

        return [
            'ok'       => true,
            'input'    => $input,
            'nome'     => $nome,
            'tipo_ativo' => $tipoAtivo,
        ];
    }

    /**
     * Gera o arquivo modelo (.xlsx) com as colunas, exemplos e instruções.
     */
    public static function buildTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Modelo');

        $colCount = count(self::COLUMNS);
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);

        foreach (self::COLUMNS as $i => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($colLetter . '1', $col['label']);
            $sheet->getColumnDimension($colLetter)->setWidth(24);
        }
        $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F59E0B'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $exemplos = [
            ['Notebook', '1', 'Em uso', 'Dell', 'Notebook', 'Latitude 3420', 'ABC123456', 'Pedagogico', '8 GB', '256 GB', 'SSD', '', 'Bom', 'Nota fiscal 12345'],
            ['Celular', '2', 'Disponivel', 'Samsung', 'Smartphone', 'Galaxy A14', '358971234567890', 'Administrativo', '4 GB', '128 GB', '', '358971234567890', 'Bom', ''],
            ['Plataforma de Recarga', '3', 'Disponivel', 'Xiaomi', 'Carregador', '', '', 'Pedagogico', '', '', '', '', 'Sem avaliacao', ''],
        ];

        foreach ($exemplos as $rIdx => $exRow) {
            $row = $rIdx + 2;
            foreach ($exRow as $cIdx => $value) {
                $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValueExplicit($colLetter . $row, $value, DataType::TYPE_STRING);
            }
        }

        $sheet->getStyle('A2:' . $lastCol . (count($exemplos) + 1))->applyFromArray([
            'font'    => ['italic' => true, 'color' => ['rgb' => '64748B']],
            'fill'    => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);
        $sheet->getStyle('A1:' . $lastCol . '1')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B45309');

        $sheet->setAutoFilter('A1:' . $lastCol . (count($exemplos) + 1));
        $sheet->freezePane('A2');
        $sheet->setSelectedCell('A2');

        $ins = $spreadsheet->createSheet();
        $ins->setTitle('Instrucoes');
        $ins->setCellValue('A1', 'Coluna');
        $ins->setCellValue('B1', 'Obrigatorio');
        $ins->setCellValue('C1', 'O que informar');
        $ins->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'],
            ],
        ]);

        foreach (self::COLUMNS as $i => $col) {
            $r = $i + 2;
            $ins->setCellValue('A' . $r, $col['label']);
            $ins->setCellValue('B' . $r, $col['required'] ? 'Sim' : 'Nao');
            $ins->setCellValue('C' . $r, $col['help']);
            $ins->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $ins->getColumnDimension('A')->setWidth(26);
        $ins->getColumnDimension('B')->setWidth(13);
        $ins->getColumnDimension('C')->setWidth(90);
        $ins->getStyle('A1:C' . (count(self::COLUMNS) + 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}