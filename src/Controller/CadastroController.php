<?php

namespace GlpiPlugin\Cadastroativos\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Cadastroativos\AssetManager;
use GlpiPlugin\Cadastroativos\Menu;
use Html;
use Session;
use State;
use Manufacturer;
use Dropdown;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CadastroController extends AbstractController
{
    #[Route('/Cadastro', name: 'cadastroativos_cadastro', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $plugin = new \Plugin();
        if (!$plugin->isInstalled('cadastroativos') || !$plugin->isActivated('cadastroativos')) {
            return new Response('Plugin nao encontrado.', 404);
        }

        Session::checkLoginUser();

        if (!Menu::canView()) {
            // Log detalhado para diagnosticar 403
            try {
                global $DB;
                $pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
                $dbg = [
                    'pid' => $pid,
                    'profile' => $_SESSION['glpiactiveprofile']['name'] ?? 'N/A',
                    'sessionRights' => [
                        'use' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_use'] ?? 'NOT SET',
                        'infra' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_infra'] ?? 'NOT SET',
                        'av' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_av'] ?? 'NOT SET',
                        'import' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_import'] ?? 'NOT SET',
                    ],
                    'haveRight' => [
                        'use' => Session::haveRight('plugin_cadastroativos_use', READ) ? 1 : 0,
                        'infra' => Session::haveRight('plugin_cadastroativos_infra', READ) ? 1 : 0,
                        'av' => Session::haveRight('plugin_cadastroativos_av', READ) ? 1 : 0,
                        'import' => Session::haveRight('plugin_cadastroativos_import', READ) ? 1 : 0,
                    ],
                ];
                if ($DB && $DB->tableExists('glpi_profilerights') && $pid > 0) {
                    $iter = $DB->request([
                        'SELECT' => ['name', 'rights'],
                        'FROM'   => 'glpi_profilerights',
                        'WHERE'  => ['profiles_id' => $pid, 'name' => ['IN', ['plugin_cadastroativos_use','plugin_cadastroativos_infra','plugin_cadastroativos_av','plugin_cadastroativos_import']]],
                    ]);
                    foreach ($iter as $r) { $dbg['db_'.$r['name']] = (int)$r['rights']; }
                }
                @file_put_contents(GLPI_ROOT.'/files/_log/cadastroativos_403.log', date('Y-m-d H:i:s').' '.json_encode($dbg, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                // Se pedir ?debug=1 retorna JSON para facilitar teste sem olhar log
                if ($request->query->get('debug') === '1') {
                    return new \Symfony\Component\HttpFoundation\JsonResponse(['success'=>false,'error'=>'Acesso negado - debug',$dbg], 403);
                }
            } catch (\Throwable $e) {}
            return new Response('Acesso negado.', 403);
        }

        $currentEntityId = AssetManager::getCurrentEntityId();
        $availableTypes  = AssetManager::getAvailableTypes();
        $entityName      = Html::cleanInputText(Dropdown::getDropdownName('glpi_entities', $currentEntityId));

        $typeConfig = [
            'Celular'             => ['icon' => 'fa-mobile-alt',  'color' => '#6366f1', 'group' => 'base'],
            'Telefones'           => ['icon' => 'fa-phone',       'color' => '#0d9488', 'group' => 'base'],
            'Notebook'            => ['icon' => 'fa-laptop',       'color' => '#0ea5e9', 'group' => 'base'],
            'Tablet'              => ['icon' => 'fa-tablet-alt',   'color' => '#10b981', 'group' => 'base'],
            'Desktop'             => ['icon' => 'fa-desktop',      'color' => '#f59e0b', 'group' => 'base'],
            'Switch'              => ['icon' => 'fa-network-wired','color' => '#64748b', 'group' => 'infra'],
            'Firewall'            => ['icon' => 'fa-shield-alt',   'color' => '#ef4444', 'group' => 'infra'],
            'RackdeRede'          => ['icon' => 'fa-server',       'color' => '#8b5cf6', 'group' => 'infra'],
            'Nobreak'             => ['icon' => 'fa-bolt',          'color' => '#ca8a04', 'group' => 'infra'],
            'Televisao'           => ['icon' => 'fa-tv',           'color' => '#06b6d4', 'group' => 'av'],
            'PlataformadeRecarga' => ['icon' => 'fa-charging-station', 'color' => '#22c55e', 'group' => 'av'],
        ];

        $isHelpdesk = Session::getCurrentInterface() === 'helpdesk';
        // Para helpdesk, Html::header('tools', Menu) e Html::helpHeader ainda dão 403 para PROATI helpdesk
        // mesmo com Menu::canView() = YES. Solução: para helpdesk não usa Html::header com checagem de setor,
        // renderiza a página sem depender do header do GLPI (evita "Você não tem permissão").
        if (!$isHelpdesk) {
            ob_start();
            try {
                Html::header('Cadastro de Inventario', '/plugins/cadastroativos/Cadastro', 'tools', Menu::class);
            } catch (\Throwable $e) {
                Html::header('Cadastro de Inventario', '/plugins/cadastroativos/Cadastro', 'common', 'computer');
            }
        } else {
            // helpdesk: NÃO chama Html::helpHeader (ainda dá 403 para PROATI helpdesk com poucos direitos)
            // Renderiza header manual mínimo sem checagem de Menu/central — só Menu::canView() acima já validou
            ob_start();
            echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Cadastro de Inventario</title>";
            echo "<meta name='viewport' content='width=device-width, initial-scale=1'>";
            if (isset($CFG_GLPI['root_doc'])) {
                $root = $CFG_GLPI['root_doc'];
                echo "<link rel='stylesheet' href='$root/lib/base.min.css'>";
                echo "<link rel='stylesheet' href='$root/css/palettes/auror.min.css'>";
            }
            echo "</head><body style='margin:0; background:#f8fafc;'>";
            echo "<div style='background:#fff; border-bottom:1px solid #e2e8f0; padding:10px 20px; font-family:sans-serif; font-size:.9rem; color:#334155;'><a href='{$CFG_GLPI['root_doc']}/front/central.php' style='color:#0f172a; text-decoration:none; font-weight:700;'><i class='ti ti-arrow-left'></i> Voltar</a> <span style='margin-left:12px; color:#64748b;'>Cadastro de Inventario — ".htmlspecialchars($entityName)."</span></div>";
        }

        // Agrupar tipos por grupo para exibicao
        $grupos = [
            'base'  => ['label' => 'Ativos', 'types' => []],
            'infra' => ['label' => 'Infraestrutura', 'types' => []],
            'av'    => ['label' => 'AV / Recarga', 'types' => []],
        ];
        foreach ($availableTypes as $sn => $label) {
            $g = $typeConfig[$sn]['group'] ?? 'base';
            $grupos[$g]['types'][$sn] = $label;
        }

        ?>
        <style>
        #ca-app *, #ca-app *::before, #ca-app *::after { box-sizing: border-box; }
        #ca-app {
            margin: 16px 20px 40px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
        }
        #ca-app .ca-header { display:flex; align-items:center; gap:14px; margin-bottom:20px; padding-bottom:16px; border-bottom:2px solid #f1f5f9; }
        #ca-app .ca-header-icon { width:48px; height:48px; background:linear-gradient(135deg,#f59e0b,#fbbf24); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.3rem; box-shadow:0 4px 12px rgba(245,158,11,.3); flex-shrink:0; }
        #ca-app .ca-header h1 { margin:0 0 3px; font-size:1.4rem; font-weight:700; color:#0f172a; }
        #ca-app .ca-header p { margin:0; font-size:.82rem; color:#64748b; }
        #ca-app .ca-msg { display:flex; gap:12px; align-items:flex-start; padding:14px 18px; border-radius:10px; margin-bottom:16px; font-size:.88rem; line-height:1.5; }
        #ca-app .ca-msg.success { background:#dcfce7; border:1px solid #86efac; color:#14532d; }
        #ca-app .ca-msg.error   { background:#fee2e2; border:1px solid #fca5a5; color:#7f1d1d; }
        #ca-app .ca-msg ul { margin:6px 0 0; padding-left:18px; }
        #ca-app .ca-layout { display:flex; gap:20px; align-items:flex-start; width:100%; margin-top:0; }
        #ca-app .ca-main { flex:1; min-width:0; max-width:820px; }
        #ca-app .ca-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 1px 10px rgba(0,0,0,.06); overflow:visible; }
        #ca-app .ca-section { padding:20px 24px; }
        #ca-app .ca-section-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; margin:0 0 12px; display:flex; align-items:center; gap:6px; }
        #ca-app .ca-group-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#cbd5e1; margin:14px 0 8px; }
        #ca-app .ca-group-label:first-child { margin-top:0; }
        #ca-app .ca-types { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:8px; }
        #ca-app .ca-type-btn { position:relative; display:flex; flex-direction:column; align-items:center; gap:6px; padding:14px 6px 10px; border:2px solid #e2e8f0; border-radius:12px; cursor:pointer; background:#f8fafc; transition:all .18s; user-select:none; }
        #ca-app .ca-type-btn:hover { border-color:#cbd5e1; background:#f1f5f9; transform:translateY(-1px); }
        #ca-app .ca-type-btn.active { background:#fff; transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.1); }
        #ca-app .ca-type-btn input[type="radio"] { position:absolute; opacity:0; width:0; height:0; }
        #ca-app .ca-type-icon { width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; transition:transform .18s; }
        #ca-app .ca-type-btn.active .ca-type-icon { transform:scale(1.1); }
        #ca-app .ca-type-label { font-size:.72rem; font-weight:600; color:#475569; text-align:center; line-height:1.2; transition:color .18s; }
        #ca-app .ca-type-btn.active .ca-type-label { color:#0f172a; font-weight:700; }
        #ca-app .ca-type-check { position:absolute; top:6px; right:6px; width:16px; height:16px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:8px; color:transparent; transition:all .18s; }
        #ca-app .ca-type-btn.active .ca-type-check { background:#22c55e; color:#fff; }
        #ca-app .ca-divider { height:1px; background:#f1f5f9; margin:0; }
        #ca-app .ca-form { padding:20px 24px; }
        #ca-app .ca-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px 20px; }
        #ca-app .ca-col-full { grid-column:1/-1; }
        #ca-app .ca-group { display:flex; flex-direction:column; gap:5px; }
        #ca-app .ca-label { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#64748b; display:flex; align-items:center; gap:5px; }
        #ca-app .ca-label i { opacity:.6; font-size:.72rem; }
        #ca-app .ca-req { color:#ef4444; }
        #ca-app .ca-input { width:100%; padding:9px 13px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.88rem; color:#1e293b; background:#fff; outline:none; transition:border-color .15s,box-shadow .15s; }
        #ca-app .ca-input:focus { border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.14); }
        #ca-app .ca-input::placeholder { color:#cbd5e1; }
        #ca-app .ca-input.ca-error { border-color:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.1); }
        #ca-app .ca-select { width:100%; padding:9px 13px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.88rem; color:#1e293b; background:#fff; cursor:pointer; outline:none; transition:border-color .15s,box-shadow .15s; }
        #ca-app .ca-select:focus { border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.14); }
        #ca-app .ca-inv-row { display:flex; gap:10px; align-items:stretch; }
        #ca-app .ca-inv-row .ca-input { flex:1; }
        #ca-app .ca-badge { padding:0 14px; background:linear-gradient(135deg,#f59e0b,#fbbf24); border-radius:8px; color:#fff; font-weight:700; font-size:.82rem; display:flex; align-items:center; gap:5px; min-width:64px; justify-content:center; }
        #ca-app .ca-hint { font-size:.74rem; color:#94a3b8; margin-top:2px; }
        #ca-app .ca-dropdown-wrap { width:100%; }
        #ca-app .ca-dropdown-wrap select { width:100% !important; }
        #ca-app .ca-serial-toggle { display:flex; align-items:center; gap:8px; }
        #ca-app .ca-toggle-label { display:flex; align-items:center; gap:7px; cursor:pointer; font-size:.86rem; color:#475569; font-weight:500; }
        #ca-app .ca-toggle-label input[type="checkbox"] { width:16px; height:16px; cursor:pointer; accent-color:#f59e0b; }
        #ca-app .ca-extras-section { background:#fafbff; border:1px solid #e8edff; border-radius:10px; padding:14px 16px; display:none; }
        #ca-app .ca-extras-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; margin:0 0 12px; display:flex; align-items:center; gap:6px; }
        #ca-app .ca-extras-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 16px; }
        #ca-app .ca-extras-grid .ca-col-full { grid-column:1/-1; }
        #ca-app .ca-storage-row { display:grid; grid-template-columns:130px 1fr; gap:8px; }
        #ca-app .ca-dup { display:flex; align-items:center; gap:7px; padding:7px 11px; background:#fee2e2; border:1px solid #fca5a5; border-radius:7px; font-size:.78rem; color:#991b1b; margin-top:5px; }
        #ca-app .ca-add-btn { margin-left:auto; width:22px; height:22px; border-radius:6px; border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:.66rem; padding:0; transition:all .15s; }
        #ca-app .ca-add-btn:hover:not(:disabled) { border-color:#f59e0b; color:#f59e0b; background:#fff; }
        #ca-app .ca-add-btn:disabled { opacity:.35; cursor:not-allowed; }
        #ca-app #ca-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; z-index:1050; }
        #ca-app #ca-modal { background:#fff; border-radius:14px; box-shadow:0 20px 50px rgba(0,0,0,.25); width:380px; max-width:calc(100vw - 32px); padding:20px; }
        #ca-app #ca-modal h3 { margin:0 0 4px; font-size:.95rem; color:#0f172a; }
        #ca-app #ca-modal .ca-modal-sub { margin:0 0 14px; font-size:.78rem; color:#64748b; }
        #ca-app #ca-modal input { width:100%; padding:9px 13px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.88rem; outline:none; }
        #ca-app #ca-modal input:focus { border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.14); }
        #ca-app #ca-modal .ca-modal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:16px; }
        #ca-app #ca-modal .ca-modal-actions button { padding:8px 16px; border-radius:8px; font-size:.82rem; font-weight:700; cursor:pointer; border:none; }
        #ca-app #ca-modal .ca-modal-cancel { background:#f1f5f9; color:#475569; }
        #ca-app #ca-modal .ca-modal-save { background:linear-gradient(135deg,#f59e0b,#fbbf24); color:#fff; }
        #ca-app #ca-modal .ca-modal-save:disabled { opacity:.6; cursor:wait; }
        #ca-app #ca-modal .ca-modal-err { color:#b91c1c; font-size:.76rem; margin-top:8px; }
        #ca-app .ca-footer { display:flex; align-items:center; justify-content:space-between; margin-top:20px; padding-top:16px; border-top:1px solid #f1f5f9; gap:12px; flex-wrap:wrap; }
        #ca-app .ca-entity { display:flex; align-items:center; gap:6px; font-size:.78rem; color:#64748b; font-weight:600; background:#f8fafc; padding:6px 13px; border-radius:20px; border:1px solid #e2e8f0; }
        #ca-app .ca-submit { padding:10px 26px; background:linear-gradient(135deg,#f59e0b,#fbbf24); color:#fff; border:none; border-radius:10px; font-size:.9rem; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(245,158,11,.35); display:flex; align-items:center; gap:7px; transition:all .18s; }
        #ca-app .ca-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(245,158,11,.45); }
        /* Painel lateral */
        #ca-app #ca-panel { width:300px; flex-shrink:0; display:none; align-self:flex-start; }
        #ca-app #ca-panel-inner { background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 1px 10px rgba(0,0,0,.06); overflow:hidden; display:flex; flex-direction:column; }
        #ca-app #ca-panel-header { padding:14px 16px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:10px; flex-shrink:0; }
        #ca-app #ca-panel-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.95rem; color:#fff; background:#6366f1; flex-shrink:0; }
        #ca-app #ca-panel-title { font-weight:700; font-size:.82rem; color:#0f172a; }
        #ca-app #ca-panel-count { font-size:.7rem; color:#94a3b8; }
        #ca-app #ca-panel-tools { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
        #ca-app #ca-panel-search { width:100%; padding:6px 10px; font-size:.76rem; }
        #ca-app #ca-panel-list { overflow-y:auto; padding:4px 0; }
        #ca-app .ca-dropdown-wrap .select2-container,
        #ca-app .ca-dropdown-wrap .chosen-container {
            width: 100% !important;
            max-width: 100% !important;
        }
        /* Importacao em massa */
        #ca-app .ca-import-desc { font-size:.82rem; color:#64748b; margin:0 0 14px; line-height:1.55; }
        #ca-app .ca-import-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        #ca-app .ca-import-modelo { display:inline-flex; align-items:center; gap:7px; padding:10px 20px; background:#0f172a; color:#fff; border-radius:10px; font-size:.84rem; font-weight:700; text-decoration:none; transition:background .15s; }
        #ca-app .ca-import-modelo:hover { background:#1e293b; }
        #ca-app .ca-import-file { display:flex; align-items:center; gap:9px; }
        #ca-app .ca-import-file input[type="file"] { font-size:.8rem; max-width:240px; }
        #ca-app .ca-import-file span { font-size:.78rem; color:#94a3b8; }
        #ca-app .ca-import-result { margin-top:16px; }
        #ca-app .ca-import-result .ca-msg { margin-bottom:8px; }
        #ca-app .ca-import-result ul { max-height:220px; overflow-y:auto; }
        </style>

        <div id="ca-app">
            <div class="ca-header">
                <div class="ca-header-icon"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <h1>Cadastro de Inventario</h1>
                    <p><i class="fas fa-building" style="margin-right:4px"></i><?= $entityName ?></p>
                </div>
            </div>

            <div id="ca-msg-container"></div>

            <div class="ca-layout">
            <div class="ca-main" id="ca-main">

            <?php if (Session::haveRight(PLUGIN_CADASTROATIVOS_RIGHT_IMPORT, READ)): ?>
            <div class="ca-card" style="margin-bottom:20px;">
                <div class="ca-section">
                    <p class="ca-section-title"><i class="fas fa-file-import"></i> Importacao em massa (XLSX)</p>
                    <p class="ca-import-desc">Baixe o <strong>modelo</strong>, preencha uma linha por ativo (conforme as instrucoes na segunda aba da planilha) e envie o arquivo para cadastrar varios equipamentos de uma vez.</p>
                    <div class="ca-import-row">
                        <a id="ca-import-modelo" href="#" class="ca-import-modelo"><i class="fas fa-download"></i> Baixar modelo</a>
                        <button type="button" id="ca-import-btn" class="ca-submit" style="padding:10px 22px;font-size:.84rem;">
                            <i class="fas fa-upload"></i> Importar
                        </button>
                    </div>
                    <input type="file" id="ca-import-file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" style="display:none;">
                    <span id="ca-import-filename" style="display:none;"></span>
                    <div style="margin-top:10px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; font-size:.78rem; color:#14532d; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-sync-alt" style="color:#16a34a;"></i>
                        <span><strong>Sincronização automática:</strong> itens com série em branco são sincronizados por diferença — se a planilha tiver mais que o GLPI, cadastra a diferença; se tiver menos, apaga o excedente do GLPI para bater a planilha. Ex: 10 Tablets em branco no GLPI + 14 na planilha = cadastra 4; 14 no GLPI e 10 na planilha = apaga 4.</span>
                    </div>
                    <div id="ca-import-result"></div>
                    <div id="ca-revert-area" style="margin-top:14px; display:none; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:10px;">
                        <button type="button" id="ca-revert-btn" style="padding:9px 16px; background:linear-gradient(135deg,#dc2626,#ef4444); color:#fff; border:none; border-radius:8px; font-size:.84rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:7px; box-shadow:0 2px 8px rgba(220,38,38,.3);"><i class="fas fa-undo"></i> Reverter última implantação</button>
                        <span id="ca-revert-info" style="font-size:.78rem; color:#7f1d1d; line-height:1.4;"></span>
                    </div>
                    <div id="ca-revert-result" style="margin-top:10px;"></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($availableTypes)): ?>
            <div class="ca-msg" style="background:#fef3c7;border:1px solid #fcd34d;color:#78350f;">
                <i class="fas fa-exclamation-triangle" style="flex-shrink:0;margin-top:2px;"></i>
                <span>Voce nao possui permissao para cadastrar nenhum tipo de ativo. Contate o administrador.</span>
            </div>
            <?php else: ?>

            <div class="ca-card">
                <!-- Selecao de tipo -->
                <div class="ca-section">
                    <p class="ca-section-title"><i class="fas fa-tags"></i> Selecione o tipo de ativo</p>
                    <div id="ca-types-grid">
                    <?php foreach ($grupos as $grupoKey => $grupo):
                        if (empty($grupo['types'])) continue; ?>
                        <div class="ca-group-label"><?= $grupo['label'] ?></div>
                        <div class="ca-types" style="margin-bottom:4px;">
                        <?php foreach ($grupo['types'] as $sn => $label):
                            $cfg   = $typeConfig[$sn] ?? ['icon' => 'fa-box', 'color' => '#6b7280'];
                        ?>
                        <div class="ca-type-btn" data-value="<?= Html::cleanInputText($sn) ?>" <?= $sn === 'PlataformadeRecarga' ? 'data-hide-modelo="1"' : '' ?> tabindex="0">
                            <input type="radio" name="_tipo_radio" value="<?= Html::cleanInputText($sn) ?>">
                            <div class="ca-type-check"><i class="fas fa-check"></i></div>
                            <div class="ca-type-icon" style="background:<?= $cfg['color'] ?>22;color:<?= $cfg['color'] ?>">
                                <i class="fas <?= $cfg['icon'] ?>"></i>
                            </div>
                            <span class="ca-type-label"><?= Html::cleanInputText($label) ?></span>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>

                <div class="ca-divider"></div>

                <form id="form_cadastro_ativo_ajax" method="post" class="ca-form" autocomplete="off">
                    <input type="hidden" name="tipo_ativo" id="tipo_ativo_hidden" value="">

                    <div class="ca-grid">

                        <!-- N° Inventario -->
                        <div class="ca-group ca-col-full">
                            <label class="ca-label"><i class="fas fa-barcode"></i> Numero de Inventario <span class="ca-req">*</span></label>
                            <div class="ca-inv-row">
                                <input type="number" name="numero_inventario" id="numero_inventario" class="ca-input" placeholder="Ex: 1, 2, 3, 4..." min="1" max="999999" step="1" required>
                                <div class="ca-badge"><i class="fas fa-tag"></i><span id="ca-badge-txt">#0</span></div>
                            </div>
                            <span class="ca-hint">Numeração sequencial a partir de 1 (1, 2, 3...). Nome gerado automaticamente: <strong id="ca-preview">#0</strong></span>
                            <div id="ca-dup-container"></div>
                        </div>

                        <!-- Status -->
                        <div class="ca-group">
                            <label class="ca-label"><i class="fas fa-circle-dot"></i> Status <span class="ca-req">*</span></label>
                            <div class="ca-dropdown-wrap">
                                <?php State::dropdown(['name' => 'states_id', 'value' => 0, 'rand' => mt_rand(), 'entity' => $currentEntityId]); ?>
                            </div>
                        </div>

                        <!-- Fabricante -->
                        <div class="ca-group">
                            <label class="ca-label"><i class="fas fa-industry"></i> Fabricante <span class="ca-req">*</span></label>
                            <div class="ca-dropdown-wrap">
                                <?php Manufacturer::dropdown(['name' => 'manufacturers_id', 'value' => 0, 'rand' => mt_rand(), 'entity' => $currentEntityId]); ?>
                            </div>
                        </div>

                        <!-- Tipo dinamico -->
                        <div class="ca-group">
                            <label class="ca-label"><i class="fas fa-layer-group"></i> Tipo <span class="ca-req">*</span>
                                <button type="button" class="ca-add-btn" data-campo="assets_assettypes_id" title="Cadastrar novo Tipo" disabled><i class="fas fa-plus"></i></button>
                            </label>
                            <select name="assets_assettypes_id" id="assets_assettypes_id" class="ca-select" required>
                                <option value="">-- Selecione o tipo de ativo --</option>
                            </select>
                        </div>

                        <!-- Modelo dinamico -->
                        <div class="ca-group">
                            <label class="ca-label"><i class="fas fa-cube"></i> Modelo <span class="ca-req">*</span>
                                <button type="button" class="ca-add-btn" data-campo="assets_assetmodels_id" title="Cadastrar novo Modelo" disabled><i class="fas fa-plus"></i></button>
                            </label>
                            <select name="assets_assetmodels_id" id="assets_assetmodels_id" class="ca-select" required>
                                <option value="">-- Selecione o tipo de ativo --</option>
                            </select>
                        </div>

                        <!-- Ambiente -->
                        <div class="ca-group ca-col-full">
                            <label class="ca-label"><i class="fas fa-building-user"></i> Ambiente</label>
                            <select name="custom_ambiente" id="custom_ambiente" class="ca-select">
                                <option value="">-- Selecione --</option>
                                <option value="Pedagogico">Pedagógico</option>
                                <option value="Administrativo">Administrativo</option>
                            </select>
                        </div>

                        <!-- Avaliacao Tecnica -->
                        <div class="ca-group ca-col-full">
                            <label class="ca-label"><i class="fas fa-clipboard-check"></i> Avaliacao Tecnica</label>
                            <select name="custom_avaliacao_tecnica" id="custom_avaliacao_tecnica" class="ca-select">
                                <option value="">-- Selecione --</option>
                                <option value="Bom">Bom</option>
                                <option value="Desgaste natural">Desgaste natural</option>
                                <option value="Mau uso">Mau uso</option>
                                <option value="Dano fisico">Dano físico</option>
                                <option value="Obsoleto">Obsoleto</option>
                                <option value="Sem avaliacao">Sem avaliação</option>
                            </select>
                        </div>

                        <!-- Observacoes -->
                        <div class="ca-group ca-col-full">
                            <label class="ca-label"><i class="fas fa-comment-alt"></i> Observacoes</label>
                            <textarea name="custom_observacao" id="custom_observacao" class="ca-input" placeholder="Informacoes adicionais sobre o ativo..." rows="3" style="resize:vertical;"></textarea>
                        </div>

                        <!-- N° Serie -->
                        <div class="ca-group ca-col-full">
                            <label class="ca-label"><i class="fas fa-hashtag"></i> Numero de Serie</label>
                            <div class="ca-serial-toggle">
                                <label class="ca-toggle-label">
                                    <input type="checkbox" id="tem_serial_check" name="tem_serial" value="1">
                                    Este ativo possui Numero de Serie
                                </label>
                            </div>
                            <div id="serial_field" style="display:none;margin-top:6px;">
                                <input type="text" name="serial" id="serial" class="ca-input" placeholder="Digite o numero de serie" maxlength="255">
                            </div>
                        </div>

                        <!-- Extras: Celular -->
                        <div class="ca-col-full" id="extras_Celular" style="display:none;">
                            <div class="ca-extras-section visible">
                                <p class="ca-extras-title" style="color:#6366f1;"><i class="fas fa-mobile-alt"></i> Informacoes do Celular</p>
                                <div class="ca-extras-grid">
                                    <div class="ca-group">
                                        <label class="ca-label"><i class="fas fa-memory"></i> Memoria RAM</label>
                                        <input type="text" name="custom_memoria_ram" class="ca-input ca-extra-input" placeholder="Ex: 4 GB" maxlength="50">
                                    </div>
                                    <div class="ca-group">
                                        <label class="ca-label"><i class="fas fa-hdd"></i> Armazenamento</label>
                                        <input type="text" name="custom_armazenamento" class="ca-input ca-extra-input" placeholder="Ex: 128 GB" maxlength="50">
                                    </div>
                                    <div class="ca-group ca-col-full">
                                        <label class="ca-label"><i class="fas fa-sim-card"></i> IMEI</label>
                                        <input type="text" name="custom_imei" class="ca-input ca-extra-input" placeholder="Ex: 358971234567890" maxlength="20">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Extras: Desktop -->
                        <div class="ca-col-full" id="extras_Desktop" style="display:none;">
                            <div class="ca-extras-section visible">
                                <p class="ca-extras-title" style="color:#f59e0b;"><i class="fas fa-desktop"></i> Informacoes do Desktop</p>
                                <div class="ca-extras-grid">
                                    <div class="ca-group">
                                        <label class="ca-label"><i class="fas fa-memory"></i> Memoria RAM</label>
                                        <input type="text" name="custom_memoria_ram" class="ca-input ca-extra-input" placeholder="Ex: 16 GB" maxlength="50">
                                    </div>
                                    <div class="ca-group">
                                        <label class="ca-label"><i class="fas fa-hdd"></i> Armazenamento</label>
                                        <div class="ca-storage-row">
                                            <select name="custom_tipo_storage" class="ca-select ca-extra-input">
                                                <option value="">Tipo</option>
                                                <option value="HD">HD</option>
                                                <option value="SSD">SSD</option>
                                                <option value="HD+SSD">HD + SSD</option>
                                            </select>
                                            <input type="text" name="custom_armazenamento" class="ca-input ca-extra-input" placeholder="Ex: 500 GB" maxlength="50">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- .ca-grid -->

                    <div class="ca-footer">
                        <div class="ca-entity"><i class="fas fa-building"></i><?= $entityName ?></div>
                        <button type="button" id="btn-cadastrar" class="ca-submit">
                            <i class="fas fa-save"></i> Cadastrar Ativo
                        </button>
                    </div>
                </form>
            </div><!-- .ca-card -->

            <?php endif; ?>

            </div><!-- /ca-main -->

            <!-- Painel lateral -->
            <div id="ca-panel">
                <div id="ca-panel-inner">
                    <div id="ca-panel-header">
                        <div id="ca-panel-icon"><i class="fas fa-box"></i></div>
                        <div>
                            <div id="ca-panel-title">Ativos cadastrados</div>
                            <div id="ca-panel-count">carregando...</div>
                        </div>
                    </div>
                    <div id="ca-panel-tools">
                        <input type="search" id="ca-panel-search" class="ca-input ca-panel-search" placeholder="Buscar por nome, numero ou modelo...">
                    </div>
                    <div id="ca-panel-list" style="max-height:500px;overflow-y:auto;padding:4px 0;">
                        <div style="padding:20px;text-align:center;color:#94a3b8;font-size:.85rem;">
                            <i class="fas fa-spinner fa-spin"></i> Carregando...
                        </div>
                    </div>
                </div>
            </div><!-- /ca-panel -->

            </div><!-- /ca-layout -->

        </div><!-- /ca-app -->

        <script>
        var CA_CONFIG = {
            ajaxBase: (typeof CFG_GLPI !== 'undefined' ? CFG_GLPI.root_doc : '') + '/plugins/cadastroativos/ajax/',
            root: (typeof CFG_GLPI !== 'undefined' ? CFG_GLPI.root_doc : ''),
            entityName: <?= json_encode($entityName, JSON_UNESCAPED_UNICODE) ?>,
            entityId: <?= (int)$currentEntityId ?>
        };
        </script>
        <script>
        <?php echo file_get_contents(__DIR__ . '/../../js/cadastroativos.js'); ?>
        </script>
        <?php

        if ($isHelpdesk) {
            echo "</body></html>";
        } else {
            Html::footer();
        }
        $html = ob_get_clean();
        return new Response($html);
    }
}
