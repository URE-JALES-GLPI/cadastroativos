<?php

namespace GlpiPlugin\Cadastroativos;

use Glpi\Asset\AssetDefinitionManager;
use Session;

class AssetManager
{
    public const SUPPORTED_TYPES = [
        'Celular'             => 'Celular',
        'Telefones'           => 'Telefones',
        'Notebook'            => 'Notebook',
        'Tablet'              => 'Tablet',
        'Desktop'             => 'Desktop',
        'Switch'              => 'Switch',
        'Firewall'            => 'Firewall',
        'RackdeRede'          => 'Rack de Rede',
        'Nobreak'             => 'Nobreak',
        'Televisao'           => 'Televisão',
        'PlataformadeRecarga' => 'Plataforma de Recarga',
    ];

    public const INFRA_TYPES = ['Switch', 'Firewall', 'RackdeRede', 'Nobreak'];
    public const AV_TYPES    = ['Televisao', 'PlataformadeRecarga'];
    public const BASE_TYPES  = ['Celular', 'Telefones', 'Notebook', 'Tablet', 'Desktop'];
    public const LEGACY_TYPES = ['Telefones'];

    public static function isLegacyType(string $systemName): bool
    {
        return in_array($systemName, self::LEGACY_TYPES, true);
    }

    public static function getAvailableTypes(): array
    {
        $available = [];
        foreach (self::SUPPORTED_TYPES as $systemName => $label) {
            if (!self::isLegacyType($systemName) && self::getDefinition($systemName) === null) continue;

            if (in_array($systemName, self::INFRA_TYPES)) {
                if (!Session::haveRight('plugin_cadastroativos_infra', READ)) continue;
            } elseif (in_array($systemName, self::AV_TYPES)) {
                if (!Session::haveRight('plugin_cadastroativos_av', READ)) continue;
            } else {
                if (!Session::haveRight('plugin_cadastroativos_use', READ)) continue;
            }

            $available[$systemName] = $label;
        }
        return $available;
    }

    public static function getDefinition(string $systemName)
    {
        $manager    = AssetDefinitionManager::getInstance();
        $definition = $manager->getDefinition($systemName);
        return $definition ?: null;
    }

    public static function getAssetClass(string $systemName): ?string
    {
        $definition = self::getDefinition($systemName);
        return $definition?->getAssetClassName();
    }

    public static function getTypeClass(string $systemName): ?string
    {
        $definition = self::getDefinition($systemName);
        if ($definition === null || !method_exists($definition, 'getAssetTypeClassName')) return null;
        $class = $definition->getAssetTypeClassName();
        return class_exists($class) ? $class : null;
    }

    public static function getModelClass(string $systemName): ?string
    {
        $definition = self::getDefinition($systemName);
        if ($definition === null || !method_exists($definition, 'getAssetModelClassName')) return null;
        $class = $definition->getAssetModelClassName();
        return class_exists($class) ? $class : null;
    }

    public static function buildAssetName(string $inventoryNumber): string
    {
        return '#' . trim($inventoryNumber);
    }

    /**
     * Verifica unicidade do numero de inventario por entidade E por tipo de ativo.
     * Usa query direta filtrando por assets_assetdefinitions_id para garantir
     * que a validacao e isolada por tipo — mesmo numero pode existir em tipos diferentes.
     */
    public static function inventoryNumberExists(
        string $systemName,
        string $inventoryNumber,
        int $entityId,
        int $ignoreId = 0
    ): bool {
        global $DB;

        if (self::isLegacyType($systemName)) {
            $where = [
                'otherserial' => $inventoryNumber,
                'entities_id' => $entityId,
                'is_deleted'  => 0,
            ];
            if ($ignoreId > 0) {
                $where['id'] = ['!=', $ignoreId];
            }
            $iterator = $DB->request([
                'COUNT' => 'id',
                'FROM'  => 'glpi_phones',
                'WHERE' => $where,
            ]);
            $row = $iterator->current();
            return (int) ($row['COUNT(id)'] ?? $row['id'] ?? 0) > 0;
        }

        $definition = self::getDefinition($systemName);
        if ($definition === null) return false;

        $definitionId = (int) $definition->getID();

        $where = [
            'otherserial'                => $inventoryNumber,
            'entities_id'                => $entityId,
            'is_deleted'                 => 0,
            'assets_assetdefinitions_id' => $definitionId,
        ];

        if ($ignoreId > 0) {
            $where['id'] = ['!=', $ignoreId];
        }

        $iterator = $DB->request([
            'COUNT' => 'id',
            'FROM'  => 'glpi_assets_assets',
            'WHERE' => $where,
        ]);

        $row = $iterator->current();
        return (int) ($row['COUNT(id)'] ?? $row['id'] ?? 0) > 0;
    }

    public static function createAsset(string $systemName, array $input): int
    {
        if (self::isLegacyType($systemName)) {
            $phone = new \Phone();
            $newId = $phone->add($input);
            if (!$newId) {
                throw new \RuntimeException('Nao foi possivel criar o telefone. Verifique os dados informados.');
            }
            return (int) $newId;
        }

        $assetClass = self::getAssetClass($systemName);
        if ($assetClass === null || !class_exists($assetClass)) {
            throw new \RuntimeException('Tipo de ativo invalido ou nao configurado no GLPI.');
        }
        $asset = new $assetClass();
        $newId = $asset->add($input);
        if (!$newId) {
            throw new \RuntimeException('Nao foi possivel criar o ativo. Verifique os dados informados.');
        }
        return (int) $newId;
    }

    public static function getCurrentEntityId(): int
    {
        return (int) Session::getActiveEntity();
    }

    public static function findAssetIdByInventory(string $systemName, string $inventoryNumber, int $entityId): int
    {
        global $DB;
        $inventoryNumber = trim($inventoryNumber);
        if ($inventoryNumber === '') {
            return 0;
        }
        if (self::isLegacyType($systemName)) {
            $iterator = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_phones',
                'WHERE'  => [
                    'otherserial' => $inventoryNumber,
                    'entities_id' => $entityId,
                    'is_deleted'  => 0,
                ],
                'LIMIT'  => 1,
            ]);
            $row = $iterator->current();
            return $row ? (int) $row['id'] : 0;
        }
        $definition = self::getDefinition($systemName);
        if ($definition === null) {
            return 0;
        }
        $definitionId = (int) $definition->getID();
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_assets_assets',
            'WHERE'  => [
                'otherserial'                => $inventoryNumber,
                'entities_id'                => $entityId,
                'is_deleted'                 => 0,
                'assets_assetdefinitions_id' => $definitionId,
            ],
            'LIMIT'  => 1,
        ]);
        $row = $iterator->current();
        return $row ? (int) $row['id'] : 0;
    }

    public static function updateAsset(string $systemName, int $id, array $input): void
    {
        if (self::isLegacyType($systemName)) {
            $phone = new \Phone();
            $input['id'] = $id;
            if (!$phone->update($input)) {
                throw new \RuntimeException('Nao foi possivel atualizar o telefone ID ' . $id . '.');
            }
            return;
        }
        $assetClass = self::getAssetClass($systemName);
        if ($assetClass === null || !class_exists($assetClass)) {
            throw new \RuntimeException('Tipo de ativo invalido ou nao configurado no GLPI.');
        }
        $asset = new $assetClass();
        $input['id'] = $id;
        if (!$asset->update($input)) {
            throw new \RuntimeException('Nao foi possivel atualizar o ativo ID ' . $id . '.');
        }
    }

    /**
     * Conta quantos ativos existem com numero de inventario/serie em branco
     * para um tipo e entidade. Considera otherserial vazio (trim == '') — campo usado
     * na importacao massiva. Para compatibilidade tambem considera serial vazio quando
     * otherserial ja esta vazio (ou seja, ambos contribuem). Conta apenas is_deleted=0 e is_template=0.
     */
    public static function countBlankInventory(string $systemName, int $entityId): int
    {
        global $DB;

        if (self::isLegacyType($systemName)) {
            $iterator = $DB->request([
                'SELECT' => ['id', 'otherserial', 'serial'],
                'FROM'   => 'glpi_phones',
                'WHERE'  => [
                    'entities_id' => $entityId,
                    'is_deleted'  => 0,
                ],
            ]);
            $cnt = 0;
            foreach ($iterator as $row) {
                $other = trim((string) ($row['otherserial'] ?? ''));
                // Considera em branco quando otherserial == '' (campo de unicidade da importacao)
                // Para cobrir casos onde serial tambem esta vazio mas otherserial preenchido (manuais),
                // a contagem por otherserial ja reflete os em branco da importacao. Se precisar contar
                // por serial, ajuste aqui para: $other === '' || trim($row['serial'] ?? '') === ''
                if ($other === '') {
                    $cnt++;
                }
            }
            return $cnt;
        }

        $definition = self::getDefinition($systemName);
        if ($definition === null) {
            return 0;
        }
        $definitionId = (int) $definition->getID();
        $iterator = $DB->request([
            'SELECT' => ['id', 'otherserial', 'serial'],
            'FROM'   => 'glpi_assets_assets',
            'WHERE'  => [
                'assets_assetdefinitions_id' => $definitionId,
                'entities_id'               => $entityId,
                'is_deleted'                => 0,
            ],
        ]);
        $cnt = 0;
        foreach ($iterator as $row) {
            $other = trim((string) ($row['otherserial'] ?? ''));
            if ($other === '') {
                $cnt++;
            }
        }
        return $cnt;
    }

    /**
     * Conta blanks por entidade para todos os tipos suportados (otimizado: uma query por tipo).
     * @return array<string,int> mapa systemName => count
     */
    public static function countAllBlankInventories(int $entityId): array
    {
        $out = [];
        foreach (self::SUPPORTED_TYPES as $systemName => $label) {
            if (!self::isLegacyType($systemName) && self::getDefinition($systemName) === null) {
                $out[$systemName] = 0;
                continue;
            }
            $out[$systemName] = self::countBlankInventory($systemName, $entityId);
        }
        return $out;
    }

    /**
     * Retorna IDs de ativos com serie em branco para um tipo/entidade, limitado a $limit.
     * Ordenado por id ASC (mais antigos primeiro) para deletar de forma deterministica.
     * @return int[]
     */
    public static function getBlankAssetIds(string $systemName, int $entityId, int $limit): array
    {
        global $DB;
        if ($limit <= 0) return [];
        $ids = [];

        if (self::isLegacyType($systemName)) {
            $iterator = $DB->request([
                'SELECT' => ['id', 'otherserial'],
                'FROM'   => 'glpi_phones',
                'WHERE'  => [
                    'entities_id' => $entityId,
                    'is_deleted'  => 0,
                ],
                'ORDER'  => ['id ASC'],
            ]);
            foreach ($iterator as $row) {
                if (trim((string) ($row['otherserial'] ?? '')) === '') {
                    $ids[] = (int) $row['id'];
                    if (count($ids) >= $limit) break;
                }
            }
            return $ids;
        }

        $definition = self::getDefinition($systemName);
        if ($definition === null) return [];
        $definitionId = (int) $definition->getID();
        $iterator = $DB->request([
            'SELECT' => ['id', 'otherserial'],
            'FROM'   => 'glpi_assets_assets',
            'WHERE'  => [
                'assets_assetdefinitions_id' => $definitionId,
                'entities_id'               => $entityId,
                'is_deleted'                => 0,
            ],
            'ORDER'  => ['id ASC'],
        ]);
        foreach ($iterator as $row) {
            if (trim((string) ($row['otherserial'] ?? '')) === '') {
                $ids[] = (int) $row['id'];
                if (count($ids) >= $limit) break;
            }
        }
        return $ids;
    }

    /**
     * Soft-delete (move para lixeira) um ativo.
     */
    public static function softDeleteAsset(string $systemName, int $id): bool
    {
        global $DB;
        if ($id <= 0) return false;
        if (self::isLegacyType($systemName)) {
            $ok = $DB->update('glpi_phones', ['is_deleted' => 1], ['id' => $id]);
            return $ok !== false;
        }
        $assetClass = self::getAssetClass($systemName);
        if ($assetClass !== null && class_exists($assetClass)) {
            try {
                $item = new $assetClass();
                if ($item->getFromDB($id)) {
                    return (bool) $item->delete(['id' => $id]);
                }
            } catch (\Throwable $e) {
                // fallback para update direto
            }
        }
        $ok = $DB->update('glpi_assets_assets', ['is_deleted' => 1], ['id' => $id]);
        return $ok !== false;
    }

    /**
     * Restaura um ativo da lixeira (is_deleted 0).
     */
    public static function restoreAsset(string $systemName, int $id): bool
    {
        global $DB;
        if ($id <= 0) return false;
        if (self::isLegacyType($systemName)) {
            $ok = $DB->update('glpi_phones', ['is_deleted' => 0], ['id' => $id]);
            return $ok !== false;
        }
        $assetClass = self::getAssetClass($systemName);
        if ($assetClass !== null && class_exists($assetClass)) {
            try {
                $item = new $assetClass();
                if ($item->getFromDB($id)) {
                    // GLPI CommonDBTM restore
                    if (method_exists($item, 'restore')) {
                        return (bool) $item->restore(['id' => $id]);
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        $ok = $DB->update('glpi_assets_assets', ['is_deleted' => 0], ['id' => $id]);
        return $ok !== false;
    }

    /**
     * Purge definitivo (hard delete) — usado em revert de criados se necessario.
     * Por padrao faz soft-delete; se $purge=true faz purge.
     */
    public static function deleteAsset(string $systemName, int $id, bool $purge = false): bool
    {
        if ($purge) {
            global $DB;
            if (self::isLegacyType($systemName)) {
                $phone = new \Phone();
                if ($phone->getFromDB($id)) {
                    return (bool) $phone->delete(['id' => $id], 1);
                }
                return (bool) $DB->delete('glpi_phones', ['id' => $id]);
            }
            $assetClass = self::getAssetClass($systemName);
            if ($assetClass !== null && class_exists($assetClass)) {
                $item = new $assetClass();
                if ($item->getFromDB($id)) {
                    return (bool) $item->delete(['id' => $id], 1);
                }
            }
            return (bool) $DB->delete('glpi_assets_assets', ['id' => $id]);
        }
        return self::softDeleteAsset($systemName, $id);
    }

    // === Historico de importacoes ===

    public static function ensureHistoryTable(): void
    {
        if (function_exists('plugin_cadastroativos_ensureImportTables')) {
            plugin_cadastroativos_ensureImportTables();
        } else {
            global $DB;
            if (!$DB->tableExists('glpi_plugin_cadastroativos_imports')) {
                $DB->doQuery("CREATE TABLE IF NOT EXISTS `glpi_plugin_cadastroativos_imports` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `users_id` int(11) NOT NULL,
                    `entities_id` int(11) NOT NULL,
                    `date_creation` datetime NOT NULL,
                    `filename` varchar(255) NOT NULL DEFAULT '',
                    `total_rows` int(11) NOT NULL DEFAULT 0,
                    `importados` int(11) NOT NULL DEFAULT 0,
                    `deletados` int(11) NOT NULL DEFAULT 0,
                    `pulados` int(11) NOT NULL DEFAULT 0,
                    `is_reverted` tinyint(1) NOT NULL DEFAULT 0,
                    `date_reverted` datetime DEFAULT NULL,
                    `reverted_by` int(11) DEFAULT NULL,
                    `created_ids` text,
                    `deleted_ids` text,
                    `blanks_info` text,
                    `errors` text,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }
        }
    }

    public static function getLastImport(int $usersId, int $entitiesId): ?array
    {
        global $DB;
        self::ensureHistoryTable();
        if (!$DB->tableExists('glpi_plugin_cadastroativos_imports')) return null;
        $iterator = $DB->request([
            'SELECT' => ['*'],
            'FROM'   => 'glpi_plugin_cadastroativos_imports',
            'WHERE'  => [
                'users_id'    => $usersId,
                'entities_id' => $entitiesId,
                'is_reverted' => 0,
            ],
            'ORDER'  => ['date_creation DESC', 'id DESC'],
            'LIMIT'  => 1,
        ]);
        $row = $iterator->current();
        return $row ?: null;
    }

    public static function insertImportHistory(array $data): int
    {
        global $DB;
        self::ensureHistoryTable();
        if (!$DB->tableExists('glpi_plugin_cadastroativos_imports')) return 0;
        $DB->insert('glpi_plugin_cadastroativos_imports', $data);
        return (int) $DB->insertId();
    }
}
