<?php

namespace GlpiPlugin\Cadastroativos;

use Glpi\Asset\AssetDefinitionManager;
use Session;

class AssetManager
{
    public const SUPPORTED_TYPES = [
        'Celular'             => 'Celular',
        'Notebook'            => 'Notebook',
        'Tablet'              => 'Tablet',
        'Desktop'             => 'Desktop',
        'Switch'              => 'Switch',
        'Firewall'            => 'Firewall',
        'RackdeRede'          => 'Rack de Rede',
        'Televisao'           => 'Televisão',
        'PlataformadeRecarga' => 'Plataforma de Recarga',
    ];

    public const INFRA_TYPES = ['Switch', 'Firewall', 'RackdeRede'];
    public const AV_TYPES    = ['Televisao', 'PlataformadeRecarga'];
    public const BASE_TYPES  = ['Celular', 'Notebook', 'Tablet', 'Desktop'];

    public const AMBIENTE_OPTIONS = [
        ''               => '-- Selecione --',
        'Pedagogico'     => 'Pedagógico',
        'Administrativo' => 'Administrativo',
    ];

    public static function getAvailableTypes(): array
    {
        $available = [];
        foreach (self::SUPPORTED_TYPES as $systemName => $label) {
            if (self::getDefinition($systemName) === null) continue;

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

    public static function buildAssetName(string $inventoryNumber): string
    {
        return '#' . trim($inventoryNumber);
    }

    public static function inventoryNumberExists(
        string $systemName,
        string $inventoryNumber,
        int $entityId,
        int $ignoreId = 0
    ): bool {
        $assetClass = self::getAssetClass($systemName);
        if ($assetClass === null || !class_exists($assetClass)) return false;

        $asset    = new $assetClass();
        $criteria = ['otherserial' => $inventoryNumber, 'entities_id' => $entityId, 'is_deleted' => 0];
        if ($ignoreId > 0) $criteria['id'] = ['!=', $ignoreId];
        return count($asset->find($criteria, [], 1)) > 0;
    }

    public static function createAsset(string $systemName, array $input): int
    {
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
}
