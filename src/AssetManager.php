<?php

namespace GlpiPlugin\Cadastroativos;

use Glpi\Asset\AssetDefinitionManager;
use Session;

/**
 * Classe central de regras de negocio do plugin Cadastro de Ativos.
 *
 * Centraliza:
 *  - Mapeamento entre Tipo de Ativo e Asset Definitions nativas do GLPI 11
 *  - Geracao automatica do nome: #<numero de inventario>
 *  - Validacao de N° de inventario unico por entidade e tipo
 *  - Criacao do ativo via API nativa (CommonDBTM::add())
 */
class AssetManager
{
    /**
     * Tipos de ativo suportados pelo plugin.
     * Chave = system_name da Asset Definition no GLPI.
     * Valor = rotulo exibido no formulario.
     *
     * Para adicionar/remover tipos: edite este array com o system_name
     * exato cadastrado em Configurar > Ativos > Definicoes de Ativos.
     *
     * @var array<string,string>
     */
    public const SUPPORTED_TYPES = [
        'Celular'  => 'Celular',
        'Notebook' => 'Notebook',
        'Tablet'   => 'Tablet',
        'Desktop'  => 'Desktop',
    ];

    /**
     * Retorna apenas os tipos cuja Asset Definition realmente existe e esta
     * ativa no GLPI (protege contra definicoes removidas).
     *
     * @return array<string,string>
     */
    public static function getAvailableTypes(): array
    {
        $available = [];
        foreach (self::SUPPORTED_TYPES as $systemName => $label) {
            if (self::getDefinition($systemName) !== null) {
                $available[$systemName] = $label;
            }
        }
        return $available;
    }

    /**
     * Busca a Asset Definition pelo system_name.
     *
     * @return \Glpi\Asset\AssetDefinition|null
     */
    public static function getDefinition(string $systemName)
    {
        $manager = AssetDefinitionManager::getInstance();
        $definition = $manager->getDefinition($systemName);
        return $definition ?: null;
    }

    /**
     * Retorna o nome completo da classe PHP do ativo customizado.
     * Ex: Glpi\CustomAsset\NotebookAsset
     */
    public static function getAssetClass(string $systemName): ?string
    {
        $definition = self::getDefinition($systemName);
        return $definition?->getAssetClassName();
    }

    /**
     * Gera o nome automatico do ativo: #<numero de inventario>
     */
    public static function buildAssetName(string $inventoryNumber): string
    {
        return '#' . trim($inventoryNumber);
    }

    /**
     * Verifica se ja existe ativo do mesmo tipo na mesma entidade
     * com o mesmo numero de inventario (campo otherserial).
     * Usa API nativa (find()), sem SQL direto.
     *
     * @param string $systemName      Tipo do ativo (system_name)
     * @param string $inventoryNumber Numero de inventario
     * @param int    $entityId        Entidade onde o ativo sera criado
     * @param int    $ignoreId        ID a ignorar (uso futuro em edicoes)
     */
    public static function inventoryNumberExists(
        string $systemName,
        string $inventoryNumber,
        int $entityId,
        int $ignoreId = 0
    ): bool {
        $assetClass = self::getAssetClass($systemName);

        if ($assetClass === null || !class_exists($assetClass)) {
            return false;
        }

        /** @var \Glpi\Asset\Asset $asset */
        $asset = new $assetClass();

        $criteria = [
            'otherserial' => $inventoryNumber,
            'entities_id' => $entityId,
            'is_deleted'  => 0,
        ];

        if ($ignoreId > 0) {
            $criteria['id'] = ['!=', $ignoreId];
        }

        return count($asset->find($criteria, [], 1)) > 0;
    }

    /**
     * Cria o ativo usando a API nativa do GLPI (CommonDBTM::add()).
     * Garante que o item apareca normalmente em todas as listagens,
     * historico e permissoes, como se criado pelo formulario padrao.
     *
     * @param  string $systemName Tipo do ativo
     * @param  array  $input      Dados validados do formulario
     * @return int    ID do ativo criado
     * @throws \RuntimeException Em caso de falha
     */
    public static function createAsset(string $systemName, array $input): int
    {
        $assetClass = self::getAssetClass($systemName);

        if ($assetClass === null || !class_exists($assetClass)) {
            throw new \RuntimeException(
                __('Tipo de ativo invalido ou nao configurado no GLPI.', 'cadastroativos')
            );
        }

        /** @var \Glpi\Asset\Asset $asset */
        $asset = new $assetClass();
        $newId = $asset->add($input);

        if (!$newId) {
            throw new \RuntimeException(
                __('Nao foi possivel criar o ativo. Verifique os dados informados.', 'cadastroativos')
            );
        }

        return (int) $newId;
    }

    /**
     * Retorna a entidade ativa da sessao do usuario logado.
     */
    public static function getCurrentEntityId(): int
    {
        return (int) Session::getActiveEntity();
    }
}
