# Plugin Cadastro de Ativos v1.1 — GLPI 11

Plugin que adiciona ao menu **Ferramentas** uma tela rapida para cadastro
de ativos (Celular, Notebook, Tablet, Desktop), com:

1. **Nome automatico**: `#<numero de inventario>` (ex: `#125`) — gerado pelo backend, nunca digitado pelo usuario.
2. **Inventario unico por entidade e tipo**: impede cadastro de dois ativos do mesmo tipo com o mesmo numero na mesma entidade.

Usa exclusivamente a estrutura nativa de *Custom Assets* (Definicoes de Ativos) do GLPI 11.
**Nao cria tabelas proprias.** Os ativos aparecem normalmente em todas as listagens e buscas do GLPI.

## Arquitetura (GLPI 11 obrigatorio)

> No GLPI 11, os arquivos `front/` e `ajax/` foram substituidos por
> **Controllers Symfony**. Esta versao usa a arquitetura correta.

```
cadastroativos/
├── setup.php                               Inicializacao, hooks, menu
├── hook.php                                Install/uninstall
├── README.md
├── css/
│   └── cadastroativos.css                  Estilos (padrao visual GLPI 11)
├── js/
│   └── cadastroativos.js                   Combos dinamicos, previa do nome, validacao AJAX
└── src/
    ├── AssetManager.php                    Regras de negocio centrais
    ├── Menu.php                            Item de menu em Ferramentas
    └── Controller/
        ├── CadastroController.php          GET /Cadastro = tela; POST /Cadastro = salvar
        ├── GetTypesModelsController.php    GET /ajax/GetTypesModels = combos dinamicos
        └── CheckInventoryController.php   GET /ajax/CheckInventory = validacao tempo real
```

**Rotas geradas automaticamente pelo GLPI 11** (sem registro manual):
- `GET/POST /glpi/plugins/cadastroativos/Cadastro` — tela e processamento do formulario
- `GET /glpi/plugins/cadastroativos/ajax/GetTypesModels` — combo Tipo/Modelo
- `GET /glpi/plugins/cadastroativos/ajax/CheckInventory` — validacao de duplicidade

## Pre-requisitos

- GLPI **>= 11.0.0**
- PHP >= 8.1
- Asset Definitions com system names exatamente: `Celular`, `Notebook`, `Tablet`, `Desktop`
  (Configurar > Ativos > Definicoes de Ativos)

## Instalacao

### 1. Enviar arquivos para o servidor (MobaXterm / SCP)

Copie a pasta `cadastroativos` para o servidor:

```bash
# No terminal do MobaXterm (no seu computador):
scp -r cadastroativos usuario@SEU_SERVIDOR:/tmp/
```

### 2. Mover para o diretorio de plugins do GLPI

```bash
# No SSH (MobaXterm > botao SSH):
sudo mv /tmp/cadastroativos /var/www/html/glpi/plugins/cadastroativos
sudo chown -R www-data:www-data /var/www/html/glpi/plugins/cadastroativos
sudo chmod -R 755 /var/www/html/glpi/plugins/cadastroativos
```

> Ajuste o caminho `/var/www/html/glpi` e o usuario `www-data` conforme seu ambiente.

### 3. Ativar no GLPI

1. Acesse como Super-Admin
2. Va em **Configurar > Plugins**
3. Localize **Cadastro de Ativos** > clique **Instalar** > clique **Ativar**
4. Faca **logout e login** (o menu e cacheado na sessao)

### 4. Acessar

**Ferramentas > Cadastro de Ativos**

## Personalizacao

- **Adicionar/remover tipos**: edite `SUPPORTED_TYPES` em `src/AssetManager.php` com o `system_name` exato da Asset Definition.
- **Mudar padrao do nome**: altere `AssetManager::buildAssetName()`.
- **Mudar escopo da unicidade**: altere `AssetManager::inventoryNumberExists()`.

## Desinstalacao

Configurar > Plugins > Desativar > Desinstalar. Os ativos ja cadastrados **nao sao removidos**.

```bash
sudo rm -rf /var/www/html/glpi/plugins/cadastroativos
```
