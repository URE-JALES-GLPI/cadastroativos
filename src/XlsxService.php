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
        ['key' => 'ciee',              'label' => 'CIEE',                  'required' => false, 'help' => 'Código CIE da escola (ex: 28435). Deve corresponder à entidade selecionada no GLPI — a importação é bloqueada se a CIE da planilha não bater com a entidade atual.'],
        ['key' => 'tipo_ativo',        'label' => 'Tipo de Ativo',         'required' => true,  'help' => 'Celular, Telefones, Notebook, Tablet, Desktop, Switch, Firewall, Rack de Rede, Nobreak, Televisão, Plataforma de Recarga'],
        ['key' => 'numero_inventario', 'label' => 'Numero de Inventario',   'required' => false, 'help' => 'Opcional. Se vazio, sera deixado em branco. Ex: 1, PE06YTF3, 5A5302L6Z'],
        ['key' => 'status',            'label' => 'Status',                'required' => true,  'help' => 'Nome do status exatamente como cadastrado no GLPI (ex: Em uso, Disponivel).'],
        ['key' => 'fabricante',        'label' => 'Fabricante',            'required' => false, 'help' => 'Opcional. Se vazio, sera deixado em branco. Nome do fabricante exatamente como cadastrado no GLPI (ex: Dell, Samsung).'],
        ['key' => 'tipo',              'label' => 'Tipo',                  'required' => true,  'help' => 'Nome do tipo do ativo cadastrado no GLPI (ex: Notebook, Smartphone). Se vazio, usa o Tipo de Ativo.'],
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

    // Mapa CIE -> Entidade (fonte: tabela enviada). Usado para validar que a planilha pertence à entidade atual.
    public const CIE_MAP = [
        '28435'  => 'EE “Coripheu de Azevedo Marques”',
        '27259'  => 'EE “José dos Santos”',
        '30636'  => 'EE Profª Maria Pereira de B. Benetoli',
        '30624'  => 'EE “João Rodrigues Fernandes”',
        '27170'  => 'EE de Osvaldo Ramos',
        '27108'  => 'EE “Baptista Dolci”',
        '30752'  => 'EE “Profª Vanir Ferrero Moraes”',
        '27224'  => 'EE “Dom Artur Horsthuis”',
        '985715' => 'Cel de Jales – EE “Dom Artur Horsthuis”',
        '27145'  => 'EE “Dr. Euphly Jalles”',
        '27261'  => 'EE “Prof. Carlos de Arnaldo Silva”',
        '49700'  => 'EE “Profª Sueli da Silveira Marin Batista”',
        '27285'  => 'EE “Juvenal Giraldelli”',
        '906104' => 'EE “Profª Onélia Faggioni Moreira”',
        '28332'  => 'EE “Antonio Marin Cruz”',
        '27054'  => 'EE “Adelino Bertani”',
        '28320'  => 'EE “Profª Maria Pilar Ortega Garcia”',
        '28344'  => 'EE “Orestes Ferreira de Toledo”',
        '27194'  => 'EE “Prefeito José Ribeiro”',
        '27182'  => 'EE “Profª Zélia de Lourdes Zaccarelli Lopes”',
        '28381'  => 'EE “Rubens de Oliveira Camargo”',
        '27112'  => 'EE “Carlos Celso Lenarduzzi”',
        '28393'  => 'EE “Prefeito Antonio Bezerra de Araújo”',
        '28400'  => 'EE “Professor Itael de Mattos”',
        '985806' => 'CEL de Santa Fé do Sul',
        '28289'  => 'EE “Profª Maria das Dores Ferreira Rocha”',
        '27133'  => 'EE “Francisco Molina Molina”',
        '28290'  => 'EE “Domingos Donato Rivelli”',
        '27066'  => 'EE “Oscar Antônio da Costa”',
        '30582'  => 'EE “Coronel Ernesto Schmidt”',
        '28319'  => 'EE “Prof. José Joaquim dos Santos”',
        '27248'  => 'EE “Professor Akio Satoru”',
        '909993' => 'EE “Profª Elide Apparecida Carlos”',
        '27212'  => 'EE “José Teixeira do Amaral”',
        '27169'  => 'EE “José Nogueira de Souza”',
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
        // Fallback Sueli: categorias como "Notebook Avançado", "Notebook Sala de Aula" devem mapear para "Notebook"
        foreach (AssetManager::SUPPORTED_TYPES as $systemName => $label) {
            $normLabel = self::normalize($label);
            $normSystem = self::normalize($systemName);
            if ($normLabel !== '' && str_contains($needle, $normLabel)) {
                return $systemName;
            }
            if ($normSystem !== '' && str_contains($needle, $normSystem)) {
                return $systemName;
            }
        }
        // Caso especial: contem 'notebook' -> Notebook (cobre variações)
        if (str_contains($needle, 'notebook')) {
            return 'Notebook';
        }
        if (str_contains($needle, 'tablet')) {
            return 'Tablet';
        }
        if (str_contains($needle, 'desktop')) {
            return 'Desktop';
        }
        if (str_contains($needle, 'celular')) {
            return 'Celular';
        }
        if (str_contains($needle, 'smartphone') || str_contains($needle, 'sphone') || str_contains($needle, 'iphone')) {
            return 'Celular';
        }
        // Abas Sueli: Educatron e Televisão -> Televisão
        if (str_contains($needle, 'educatron') || str_contains($needle, 'televisao') || str_contains($needle, 'tv')) {
            return 'Televisao';
        }
        if (str_contains($needle, 'switch')) {
            return 'Switch';
        }
        if (str_contains($needle, 'firewall')) {
            return 'Firewall';
        }
        if (str_contains($needle, 'rack')) {
            return 'RackdeRede';
        }
        if (str_contains($needle, 'nobreak')) {
            return 'Nobreak';
        }
        if (str_contains($needle, 'plataforma')) {
            return 'PlataformadeRecarga';
        }
        return null;
    }

    public static function normalizeCie(string $value): string
    {
        $v = trim($value);
        if ($v === '') return '';
        // Extrai sequência numérica (cobre "28435 - EE ..." ou "CIE 28435")
        if (preg_match('/\d+/', $v, $m)) {
            return $m[0];
        }
        return $v;
    }

    public static function getEntityNameByCie(string $cie): ?string
    {
        $cie = self::normalizeCie($cie);
        if ($cie === '') return null;
        return self::CIE_MAP[$cie] ?? null;
    }

    public static function getExpectedCieForEntity(string $entityName): ?string
    {
        $normNeedle = self::normalize($entityName);
        if ($normNeedle === '') return null;
        // 1) match exato normalizado
        foreach (self::CIE_MAP as $cie => $name) {
            if (self::normalize($name) === $normNeedle) {
                return (string) $cie;
            }
        }
        // 2) fallback contém (para variações de pontuação/aspas/CEL)
        $bestCie = null;
        $bestLen = 0;
        foreach (self::CIE_MAP as $cie => $name) {
            $normMap = self::normalize($name);
            if ($normMap === '') continue;
            if (str_contains($normMap, $normNeedle) || str_contains($normNeedle, $normMap)) {
                $len = strlen($normMap);
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $bestCie = (string) $cie;
                }
            }
        }
        return $bestCie;
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
                'serial'            => 'serial',
                'numero de serie'   => 'serial',
                'numerodserie'      => 'serial',
                'numero de patrimonio' => 'numero_inventario',
                'numero de inventario' => 'numero_inventario',
                'memoria'           => 'memoria_ram',
                'ram'               => 'memoria_ram',
                'avaliacao'         => 'avaliacao_tecnica',
                'avaliacao tecnica' => 'avaliacao_tecnica',
                'obs'               => 'observacoes',
                'observacao'        => 'observacoes',
                'observacoes'       => 'observacoes',
                'tipo de armazenamento' => 'tipo_storage',
                'tipo do ativo'     => 'tipo_ativo',
                'categoria do equipamento' => 'tipo_ativo',
                'tipo de equipamento' => 'tipo_ativo',
                'status do equipamento' => 'status',
                'statusdoequipamento' => 'status',
                'ambiente'          => 'ambiente',
                'fabricante'        => 'fabricante',
                'marca'             => 'fabricante',
                'marca fabricante'  => 'fabricante',
                'tipo modelo'       => 'modelo',
                'modelo'            => 'modelo',
                'cie'               => 'ciee',
                'ciee'              => 'ciee',
                'codigo cie'        => 'ciee',
                'codigo ciee'       => 'ciee',
                'cod cie'           => 'ciee',
                'cod ciee'          => 'ciee',
                'cie codigo'        => 'ciee',
                'ciee codigo'       => 'ciee',
                'entidade cie'      => 'ciee',
                'cie entidade'      => 'ciee',
                'escola cie'        => 'ciee',
                'cie escola'        => 'ciee',
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
     * Suporta multiplas abas com layouts ligeiramente diferentes (ex: planilha Sueli).
     */
    public static function parseRows(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

        $globalKeys = [];
        $rows       = [];
        $colIndexMap = []; // compatibilidade: indice_coluna -> chave (primeira ocorrencia)

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $highestRow    = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $colCount      = Coordinate::columnIndexFromString($highestColumn);

            // Collect headers from this sheet
            $sheetHeaders = [];
            for ($c = 1; $c <= $colCount; $c++) {
                $label = trim(self::cellString($sheet->getCell([$c, 1])->getValue()));
                if ($label === '') {
                    continue;
                }
                // Strip ' > ...' annotation pattern before normalizing
                if (preg_match('/^[^>]*>/', $label, $match)) {
                    $label = trim(substr($label, 0, strpos($label, '>')));
                }
                $norm = self::normalize($label);
                $key  = self::headerMap()[$norm] ?? null;
                // Fallback: cabeçalho que começa com 'cie' é tratado como CIEE (cobre "CIE", "CIEE", "CIEE Escola", etc). Evita falso positivo em "ESPECIE"
                if ($key === null && str_starts_with($norm, 'cie')) {
                    $key = 'ciee';
                }
                if ($key !== null) {
                    $sheetHeaders[$c] = $key;
                    $globalKeys[$key] = true;
                    if (!isset($colIndexMap[$c])) {
                        $colIndexMap[$c] = $key;
                    }
                }
            }

            if (empty($sheetHeaders)) {
                continue;
            }

            // Fallback para abas sem CATEGORIA (ex: Plataforma de Recarga)
            $sheetTitleNorm = self::normalize((string) $sheet->getTitle());
            $hasTipoAtivo = in_array('tipo_ativo', $sheetHeaders, true);
            $defaultTipoAtivo = null;
            if (!$hasTipoAtivo && str_contains($sheetTitleNorm, 'plataforma')) {
                $defaultTipoAtivo = 'Plataforma de Recarga';
                $globalKeys['tipo_ativo'] = true;
            } elseif (!$hasTipoAtivo && (str_contains($sheetTitleNorm, 'tvs') || str_contains($sheetTitleNorm, 'educatron'))) {
                // TV Educatron sem categoria explicita? fallback para Televisão, o valor da linha vai sobrescrever se tiver
                $defaultTipoAtivo = null;
            }

            // Read rows from this sheet - ja mapeia por chave para evitar conflito entre abas
            for ($r = 2; $r <= $highestRow; $r++) {
                $line = [];
                $hasData = false;
                foreach ($sheetHeaders as $c => $key) {
                    $val = self::cellString($sheet->getCell([$c, $r])->getValue());
                    if (trim($val) !== '') {
                        $hasData = true;
                    }
                    $line[$key] = $val;
                    // Mantem tambem indice numerico para compat legada com mapRow
                    $line[$c] = $val;
                }
                // Injeta tipo_ativo padrao para abas sem coluna CATEGORIA (so se linha ja tem algum dado real)
                if ($defaultTipoAtivo !== null && $hasData && empty(trim((string) ($line['tipo_ativo'] ?? '')))) {
                    $line['tipo_ativo'] = $defaultTipoAtivo;
                }
                if ($hasData) {
                    $rows[] = $line;
                }
            }
        }

        return [
            'headers'   => array_keys($globalKeys),
            'headerMap' => $colIndexMap,
            'rows'      => $rows,
        ];
    }

    public static function mapRow(array $row, array $headerMap): array
    {
        // Se ja veio mapeado por chave (nova logica multi-aba), retorna direto filtrando chaves validas
        $hasStringKey = false;
        foreach ($row as $k => $v) {
            if (!is_int($k)) {
                $hasStringKey = true;
                break;
            }
        }
        if ($hasStringKey) {
            $out = [];
            $validKeys = array_flip(array_column(self::COLUMNS, 'key'));
            // inclui chaves de aliases como serial, categoria etc que sao custom mas validas
            $validKeys['serial'] = true;
            $validKeys['categoria_equipamento'] = true;
            $validKeys['ciee'] = true;
            foreach ($row as $k => $v) {
                if (is_string($k) && isset($validKeys[$k])) {
                    $out[$k] = $v;
                } elseif (is_string($k) && in_array($k, ['ambiente','memoria_ram','armazenamento','tipo_storage','imei','avaliacao_tecnica','observacoes','categoria_equipamento','ciee'], true)) {
                    $out[$k] = $v;
                }
            }
            // fallback: se vazio, tenta mapear via indice
            if (!empty($out)) {
                return $out;
            }
        }
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
        if ($row) {
            return (int) $row['id'];
        }

        // Fallback: busca normalizada (sem acento, caixa) para tolerar "Disponivel" vs "Disponível"
        $normNeedle = self::normalize($name);
        if ($normNeedle === '') {
            return 0;
        }
        $iterator = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => $table,
            'WHERE'  => $extraWhere,
        ]);
        foreach ($iterator as $candidate) {
            if (self::normalize($candidate['name']) === $normNeedle) {
                return (int) $candidate['id'];
            }
        }
        // Ultimo fallback: LIKE case-insensitive para Status/Fabricante
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $table,
            'WHERE'  => ['name' => ['LIKE', $name]] + $extraWhere,
            'LIMIT'  => 1,
        ]);
        $row = $iterator->current();
        return $row ? (int) $row['id'] : 0;
    }

    public static function ensureManufacturer(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $id = self::findIdByTable('glpi_manufacturers', $name);
        if ($id > 0) {
            return $id;
        }
        global $DB;
        // Tenta via API do GLPI
        if (class_exists('Manufacturer')) {
            try {
                $m = new \Manufacturer();
                $newId = $m->add(['name' => $name]);
                if ($newId) {
                    return (int) $newId;
                }
            } catch (\Throwable $e) {
                // fallback para insert direto
            }
        }
        $res = $DB->insert('glpi_manufacturers', ['name' => $name]);
        return $res ? (int) $DB->insertId() : 0;
    }

    public static function ensureAssetType(string $systemName, string $typeName): int
    {
        $typeName = trim($typeName);
        if ($typeName === '') {
            return 0;
        }
        $isLegacy = AssetManager::isLegacyType($systemName);
        $table = $isLegacy ? 'glpi_phonetypes' : 'glpi_assets_assettypes';
        $extra = [];
        if (!$isLegacy) {
            $def = AssetManager::getDefinition($systemName);
            if ($def !== null) {
                $extra = ['assets_assetdefinitions_id' => (int) $def->getID()];
            }
        }
        $id = self::findIdByTable($table, $typeName, $extra);
        if ($id > 0) {
            return $id;
        }
        if (!empty($extra)) {
            $id = self::findIdByTable($table, $typeName, []);
            if ($id > 0) {
                return $id;
            }
        }
        global $DB;
        $concreteClass = AssetManager::getTypeClass($systemName);
        if ($concreteClass !== null && class_exists($concreteClass)) {
            try {
                $item = new $concreteClass();
                $input = ['name' => $typeName];
                if (!$isLegacy && isset($extra['assets_assetdefinitions_id'])) {
                    $input['assets_assetdefinitions_id'] = $extra['assets_assetdefinitions_id'];
                }
                $newId = $item->add($input);
                if ($newId) {
                    return (int) $newId;
                }
            } catch (\Throwable $e) {
                // fallback
            }
        }
        $now = date('Y-m-d H:i:s');
        $input = ['name' => $typeName, 'date_creation' => $now, 'date_mod' => $now];
        if (!$isLegacy && isset($extra['assets_assetdefinitions_id'])) {
            $input['assets_assetdefinitions_id'] = $extra['assets_assetdefinitions_id'];
        }
        $res = $DB->insert($table, $input);
        return $res ? (int) $DB->insertId() : 0;
    }

    public static function ensureAssetModel(string $systemName, string $modelName): int
    {
        $modelName = trim($modelName);
        if ($modelName === '') {
            return 0;
        }
        $isLegacy = AssetManager::isLegacyType($systemName);
        $table = $isLegacy ? 'glpi_phonemodels' : 'glpi_assets_assetmodels';
        $extra = [];
        if (!$isLegacy) {
            $def = AssetManager::getDefinition($systemName);
            if ($def !== null) {
                $extra = ['assets_assetdefinitions_id' => (int) $def->getID()];
            }
        }
        $id = self::findIdByTable($table, $modelName, $extra);
        if ($id > 0) {
            return $id;
        }
        if (!empty($extra)) {
            $id = self::findIdByTable($table, $modelName, []);
            if ($id > 0) {
                return $id;
            }
        }
        global $DB;
        $concreteClass = AssetManager::getModelClass($systemName);
        if ($concreteClass !== null && class_exists($concreteClass)) {
            try {
                $item = new $concreteClass();
                $input = ['name' => $modelName];
                if (!$isLegacy && isset($extra['assets_assetdefinitions_id'])) {
                    $input['assets_assetdefinitions_id'] = $extra['assets_assetdefinitions_id'];
                }
                $newId = $item->add($input);
                if ($newId) {
                    return (int) $newId;
                }
            } catch (\Throwable $e) {
                // fallback
            }
        }
        $now = date('Y-m-d H:i:s');
        $input = ['name' => $modelName, 'date_creation' => $now, 'date_mod' => $now];
        if (!$isLegacy && isset($extra['assets_assetdefinitions_id'])) {
            $input['assets_assetdefinitions_id'] = $extra['assets_assetdefinitions_id'];
        }
        $res = $DB->insert($table, $input);
        return $res ? (int) $DB->insertId() : 0;
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
        // Fallback Sueli: NÚMERO DE SÉRIE vem como 'serial' mas deve alimentar 'numero_inventario' quando este estiver vazio
        if ($numeroInventario === '' && isset($data['serial']) && trim((string) $data['serial']) !== '') {
            $numeroInventario = trim((string) $data['serial']);
        }
        // Fallback extra: tenta ID de controle ou qualquer campo serial-like se ainda vazio
        if ($numeroInventario === '' && isset($data['id_controle']) && trim((string) $data['id_controle']) !== '') {
            $numeroInventario = trim((string) $data['id_controle']);
        }
        // Agora OPCIONAL: so valida se preenchido — aceita todos os caracteres (ex: 43124/62VR22495)
        // Antes restrito a /^[A-Za-z0-9_-]+$/, agora permite barra, ponto, etc. Apenas limita tamanho.
        if ($numeroInventario !== '' && mb_strlen($numeroInventario) > 255) {
            $errors[] = "Numero de Inventario muito longo (max 255 caracteres).";
        }

        $status     = trim((string) ($data['status'] ?? ''));
        // Regra Sueli: "Chamado aberto" na planilha = "Garantia" no GLPI
        if (self::normalize($status) === 'chamadoaberto') {
            $status = 'Garantia';
        }
        $fabricante = trim((string) ($data['fabricante'] ?? ''));
        $tipo       = trim((string) ($data['tipo'] ?? ''));
        // Fallback: se a planilha da Sueli usa CATEGORIA DO EQUIPAMENTO como TIPO, replica para campo 'tipo' quando vazio
        if ($tipo === '' && isset($data['tipo_ativo']) && trim((string) $data['tipo_ativo']) !== '') {
            $tipo = trim((string) $data['tipo_ativo']);
        }
        // Regra Sueli: coluna "Tipo" com "Doacao" vira "Estado", exceto Desktop/Notebook que mantem Categoria
        $normTipo = self::normalize($tipo);
        if (str_contains($normTipo, 'doacao')) {
            if ($tipoAtivo === 'Desktop' || $tipoAtivo === 'Notebook') {
                $tipo = trim((string) ($data['tipo_ativo'] ?? $tipoAtivo));
            } else {
                $tipo = 'Estado';
            }
        }
        $modelo     = trim((string) ($data['modelo'] ?? ''));

        // Status: se vazio, usa "Disponível" como padrão (evita travar linhas em branco 251-2247)
        if ($status === '') {
            $status = 'Disponível';
        }
        $statesId = self::findIdByTable('glpi_states', $status);
        if ($status === '') {
            $errors[] = 'Status obrigatorio.';
        } elseif ($statesId <= 0) {
            $errors[] = "Status nao encontrado no GLPI: '$status'.";
        }

        $manufacturersId = 0;
        if ($fabricante !== '') {
            $manufacturersId = self::findIdByTable('glpi_manufacturers', $fabricante);
            if ($manufacturersId <= 0) {
                // Auto-cria fabricante quando nao existe (ex: FortiGate)
                $manufacturersId = self::ensureManufacturer($fabricante);
                if ($manufacturersId <= 0) {
                    $errors[] = "Fabricante nao encontrado no GLPI: '$fabricante'.";
                }
            }
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
            // Fallback: tenta sem filtro de definicao (caso tipo exista mas sem vinculo)
            if ($typesId <= 0 && $tipo !== '' && !empty($extraTypes)) {
                $typesId = self::findIdByTable($typesTable, $tipo, []);
            }
            // Fallback geral Sueli: se nao achou tipo e nao é Desktop/Notebook, tenta Estado como padrao (prioridade)
            if ($typesId <= 0 && $tipoAtivo !== 'Desktop' && $tipoAtivo !== 'Notebook') {
                $estadoId = self::findIdByTable($typesTable, 'Estado', $extraTypes);
                if ($estadoId > 0) {
                    $typesId = $estadoId;
                    $tipo = 'Estado';
                }
            }
            // Fallback Sueli: se CATEGORIA/TIPO_ATIVO == TIPO e ainda nao achou, usa primeiro Tipo disponivel da definicao
            if ($typesId <= 0 && $tipo !== '' && $tipoAtivo !== null && self::normalize($tipo) === self::normalize($tipoAtivo)) {
                global $DB;
                $fallback = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => $typesTable,
                    'WHERE'  => $extraTypes,
                    'LIMIT'  => 1,
                ]);
                $row = $fallback->current();
                if ($row) {
                    $typesId = (int) $row['id'];
                }
            }
            // Fallback TV: 'TV' deve usar primeiro Tipo de Televisao quando nao achou 'TV'
            if ($typesId <= 0 && $tipo !== '' && $tipoAtivo === 'Televisao') {
                global $DB;
                $fallback = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => $typesTable,
                    'WHERE'  => $extraTypes,
                    'LIMIT'  => 1,
                ]);
                $row = $fallback->current();
                if ($row) {
                    $typesId = (int) $row['id'];
                }
            }
            if ($tipo === '') {
                $errors[] = 'Tipo obrigatorio.';
            } elseif ($typesId <= 0) {
                // Tenta criar automaticamente o Tipo se nao existe
                $typesId = self::ensureAssetType($tipoAtivo, $tipo);
                if ($typesId <= 0) {
                    $errors[] = "Tipo nao encontrado no GLPI para $tipoAtivo: '$tipo'. Cadastre o Tipo em GLPI > Ativos > Tipos (vinculado a $tipoAtivo).";
                }
            }

            if ($tipoAtivo === 'PlataformadeRecarga' || $tipoAtivo === 'RackdeRede') {
                // Rack e Plataforma permitem modelo em branco
                if ($modelo === '') {
                    $modelsId = 0;
                } else {
                    $modelsId = self::findIdByTable($modelTable, $modelo, $extraModels);
                    if ($modelsId <= 0 && !empty($extraModels)) {
                        $modelsId = self::findIdByTable($modelTable, $modelo, []);
                    }
                    if ($modelsId <= 0) {
                        // Auto-cria modelo
                        $modelsId = self::ensureAssetModel($tipoAtivo, $modelo);
                        if ($modelsId <= 0) {
                            $errors[] = "Modelo nao encontrado no GLPI: '$modelo'.";
                        }
                    }
                }
            } else {
                $modelsId = self::findIdByTable($modelTable, $modelo, $extraModels);
                if ($modelsId <= 0 && $modelo !== '' && !empty($extraModels)) {
                    $modelsId = self::findIdByTable($modelTable, $modelo, []);
                }
                if ($modelo === '') {
                    $errors[] = "Modelo obrigatorio para $tipoAtivo.";
                } elseif ($modelsId <= 0) {
                    // Auto-cria modelo quando nao existe (ex: 1910)
                    $modelsId = self::ensureAssetModel($tipoAtivo, $modelo);
                    if ($modelsId <= 0) {
                        $errors[] = "Modelo nao encontrado no GLPI: '$modelo'.";
                    }
                }
            }
        }

        if (empty($errors) && $numeroInventario !== '') {
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
            $nome = 'Plataforma de Recarga' . ($numeroInventario !== '' ? ' ' . AssetManager::buildAssetName($numeroInventario) : '');
        } else {
            $base = $modelo !== '' ? $modelo : ($fabricante !== '' ? $fabricante : ($tipo !== '' ? $tipo : 'Ativo'));
            $nome = $base . ($numeroInventario !== '' ? ' ' . AssetManager::buildAssetName($numeroInventario) : '');
            $nome = trim($nome);
            if ($nome === '') {
                $nome = 'Ativo ' . ($numeroInventario !== '' ? AssetManager::buildAssetName($numeroInventario) : uniqid());
            }
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
            ['28435', 'Notebook', '1', 'Em uso', 'Dell', 'Notebook', 'Latitude 3420', 'ABC123456', 'Pedagogico', '8 GB', '256 GB', 'SSD', '', 'Bom', 'Nota fiscal 12345'],
            ['28435', 'Celular', '2', 'Disponivel', 'Samsung', 'Smartphone', 'Galaxy A14', '358971234567890', 'Administrativo', '4 GB', '128 GB', '', '358971234567890', 'Bom', ''],
            ['28435', 'Plataforma de Recarga', '3', 'Disponivel', 'Xiaomi', 'Carregador', '', '', 'Pedagogico', '', '', '', '', 'Sem avaliacao', ''],
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

        // Listas suspensas para reduzir erro de digitacao (dinamico por chave para suportar insercao de CIEE)
        $keyToCol = [];
        foreach (self::COLUMNS as $idx => $col) {
            $keyToCol[$col['key']] = Coordinate::stringFromColumnIndex($idx + 1);
        }
        $validacoes = [];
        if (isset($keyToCol['tipo_ativo'])) $validacoes[$keyToCol['tipo_ativo']] = '"' . implode(',', array_values(AssetManager::SUPPORTED_TYPES)) . '"';
        if (isset($keyToCol['status'])) $validacoes[$keyToCol['status']] = '"Em uso,Disponivel,Garantia,Descartado,Em manutencao"';
        if (isset($keyToCol['ambiente'])) $validacoes[$keyToCol['ambiente']] = '"Pedagogico,Administrativo"';
        if (isset($keyToCol['tipo_storage'])) $validacoes[$keyToCol['tipo_storage']] = '"HD,SSD,HD + SSD"';
        if (isset($keyToCol['avaliacao_tecnica'])) $validacoes[$keyToCol['avaliacao_tecnica']] = '"Bom,Desgaste natural,Mau uso,Dano fisico,Obsoleto,Sem avaliacao"';
        foreach ($validacoes as $col => $formula) {
            for ($r = 2; $r <= 1000; $r++) {
                $validation = $sheet->getCell($col . $r)->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Valor invalido');
                $validation->setError('Selecione um valor da lista.');
                $validation->setPromptTitle('Escolha');
                $validation->setPrompt('Selecione um valor da lista.');
                $validation->setFormula1($formula);
            }
        }

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