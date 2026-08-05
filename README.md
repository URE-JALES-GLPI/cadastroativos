# Plugin Cadastro de Ativos v1.1 — GLPI 11

Plugin que adiciona ao menu **Ferramentas** uma tela rápida para cadastro
de ativos (Celular, Notebook, Tablet, Desktop), com:

1. **Nome automático**: `#<número de inventário>` (ex: `#125`) — gerado pelo backend, nunca digitado pelo usuário.
2. **Inventário único por entidade e tipo**: impede cadastro de dois ativos do mesmo tipo com o mesmo número na mesma entidade.

Usa exclusivamente a estrutura nativa de **Custom Assets** (Definições de Ativos) do GLPI 11.
**Não cria tabelas próprias.** Os ativos aparecem normalmente em todas as listagens e buscas do GLPI.

---

## Arquitetura (GLPI 11 obrigatório)

> No GLPI 11, os diretórios `front/` e `ajax/` foram substituídos por **Controllers Symfony**. Esta versão utiliza a arquitetura oficial.

```text
cadastroativos/
├── setup.php                               Inicialização, hooks e menu
├── hook.php                                Instalação e desinstalação
├── README.md
├── css/
│   └── cadastroativos.css                  Estilos
├── js/
│   └── cadastroativos.js                   Combos dinâmicos, prévia do nome e validações AJAX
└── src/
    ├── AssetManager.php                    Regras de negócio
    ├── Menu.php                            Menu em Ferramentas
    └── Controller/
        ├── CadastroController.php
        ├── GetTypesModelsController.php
        └── CheckInventoryController.php
```

### Rotas

- `GET/POST /glpi/plugins/cadastroativos/Cadastro`
- `GET /glpi/plugins/cadastroativos/ajax/GetTypesModels`
- `GET /glpi/plugins/cadastroativos/ajax/CheckInventory`

---

## Requisitos

- GLPI **>= 11.0.0**
- PHP **>= 8.1**
- Definições de Ativos com os seguintes **System Names**:
  - Celular
  - Notebook
  - Tablet
  - Desktop

---

## Instalação

Clone diretamente o repositório na pasta de plugins do GLPI:

```bash
cd /var/www/html/glpi/plugins
git clone git@github.com:poiattileo/cadastroativos.git
```

Ajuste as permissões:

```bash
sudo chown -R www-data:www-data /var/www/html/glpi/plugins/cadastroativos
sudo find /var/www/html/glpi/plugins/cadastroativos -type d -exec chmod 755 {} \;
sudo find /var/www/html/glpi/plugins/cadastroativos -type f -exec chmod 644 {} \;
```

---

## Atualização

Sempre que houver uma nova versão:

```bash
cd /var/www/html/glpi/plugins/cadastroativos
git pull
sudo chown -R www-data:www-data .
```

---

## Ativação

1. Acesse como **Super-Admin**
2. Vá em **Configurar → Plugins**
3. Instale o plugin **Cadastro de Ativos**
4. Ative o plugin
5. Faça logout e login no GLPI

O menu ficará disponível em:

**Ferramentas → Cadastro de Ativos**

---

## Personalização

- Adicionar/remover tipos: `src/AssetManager.php`
- Alterar o padrão do nome: `AssetManager::buildAssetName()`
- Alterar a regra de unicidade: `AssetManager::inventoryNumberExists()`

---

## Desinstalação

Desative e desinstale o plugin pelo GLPI e, se desejar, remova os arquivos:

```bash
sudo rm -rf /var/www/html/glpi/plugins/cadastroativos
```