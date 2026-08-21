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
}
