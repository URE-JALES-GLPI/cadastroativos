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
            return new Response('Acesso negado.', 403);
        }

        $currentEntityId = AssetManager::getCurrentEntityId();
        $availableTypes  = AssetManager::getAvailableTypes();
        $entityName      = Html::cleanInputText(Dropdown::getDropdownName('glpi_entities', $currentEntityId));

        $typeConfig = [
            'Celular'             => ['icon' => 'fa-mobile-alt',  'color' => '#6366f1', 'group' => 'base'],
            'Notebook'            => ['icon' => 'fa-laptop',       'color' => '#0ea5e9', 'group' => 'base'],
            'Tablet'              => ['icon' => 'fa-tablet-alt',   'color' => '#10b981', 'group' => 'base'],
            'Desktop'             => ['icon' => 'fa-desktop',      'color' => '#f59e0b', 'group' => 'base'],
            'Switch'              => ['icon' => 'fa-network-wired','color' => '#64748b', 'group' => 'infra'],
            'Firewall'            => ['icon' => 'fa-shield-alt',   'color' => '#ef4444', 'group' => 'infra'],
            'RackdeRede'          => ['icon' => 'fa-server',       'color' => '#8b5cf6', 'group' => 'infra'],
            'Televisao'           => ['icon' => 'fa-tv',           'color' => '#06b6d4', 'group' => 'av'],
            'PlataformadeRecarga' => ['icon' => 'fa-charging-station', 'color' => '#22c55e', 'group' => 'av'],
        ];

        ob_start();
        Html::header('Cadastro de Ativos', '/plugins/cadastroativos/Cadastro', 'tools', Menu::class);

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
        #ca-app #ca-panel-list { overflow-y:auto; padding:4px 0; }
        </style>

        <div id="ca-app">
            <div class="ca-header">
                <div class="ca-header-icon"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <h1>Cadastro de Ativos</h1>
                    <p><i class="fas fa-building" style="margin-right:4px"></i><?= $entityName ?></p>
                </div>
            </div>

            <div id="ca-msg-container"></div>

            <div class="ca-layout">
            <div class="ca-main" id="ca-main">

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
                        <div class="ca-type-btn" data-value="<?= Html::cleanInputText($sn) ?>" tabindex="0">
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
                                <input type="number" name="numero_inventario" id="numero_inventario" class="ca-input" placeholder="Ex: 1001" min="1" max="999999" step="1" required>
                                <div class="ca-badge"><i class="fas fa-tag"></i><span id="ca-badge-txt">#0</span></div>
                            </div>
                            <span class="ca-hint">Nome gerado automaticamente: <strong id="ca-preview">#0</strong></span>
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
                            <label class="ca-label"><i class="fas fa-layer-group"></i> Tipo <span class="ca-req">*</span></label>
                            <select name="assets_assettypes_id" id="assets_assettypes_id" class="ca-select" required>
                                <option value="">-- Selecione o tipo de ativo --</option>
                            </select>
                        </div>

                        <!-- Modelo dinamico -->
                        <div class="ca-group">
                            <label class="ca-label"><i class="fas fa-cube"></i> Modelo <span class="ca-req">*</span></label>
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
            root: (typeof CFG_GLPI !== 'undefined' ? CFG_GLPI.root_doc : '')
        };
        /* Cadastro de Ativos — JS v1.5 */
function initCadastro() {
    var grid = document.getElementById('ca-types-grid');
    if (!grid || grid._caInited) return;
    grid._caInited = true;

    var cfg           = window.CA_CONFIG || { ajaxBase: '/plugins/cadastroativos/ajax/', root: '' };
    var ajaxBase      = cfg.ajaxBase;
    var root          = cfg.root;
    var tipoHidden    = document.getElementById('tipo_ativo_hidden');
    var invInput      = document.getElementById('numero_inventario');
    var badgeTxt      = document.getElementById('ca-badge-txt');
    var previewTxt    = document.getElementById('ca-preview');
    var tiposSelect   = document.getElementById('assets_assettypes_id');
    var modelosSelect = document.getElementById('assets_assetmodels_id');
    var dupContainer  = document.getElementById('ca-dup-container');
    var temSerialChk  = document.getElementById('tem_serial_check');
    var serialField   = document.getElementById('serial_field');
    var serialInput   = document.getElementById('serial');
    var panel         = document.getElementById('ca-panel');
    var panelInner    = document.getElementById('ca-panel-inner');
    var panelList     = document.getElementById('ca-panel-list');
    var panelTitle    = document.getElementById('ca-panel-title');
    var panelCount    = document.getElementById('ca-panel-count');
    var panelIcon     = document.getElementById('ca-panel-icon');
    var msgContainer  = document.getElementById('ca-msg-container');
    var btnCadastrar  = document.getElementById('btn-cadastrar');
    var mainEl        = document.getElementById('ca-main');

    if (!tipoHidden || !invInput) return;

    var extras = {
        Celular: document.getElementById('extras_Celular'),
        Desktop: document.getElementById('extras_Desktop')
    };

    var typeIconMap = {
        Celular:             { icon: 'fa-mobile-alt',       color: '#6366f1' },
        Notebook:            { icon: 'fa-laptop',            color: '#0ea5e9' },
        Tablet:              { icon: 'fa-tablet-alt',        color: '#10b981' },
        Desktop:             { icon: 'fa-desktop',           color: '#f59e0b' },
        Switch:              { icon: 'fa-network-wired',     color: '#64748b' },
        Firewall:            { icon: 'fa-shield-alt',        color: '#ef4444' },
        RackdeRede:          { icon: 'fa-server',            color: '#8b5cf6' },
        Televisao:           { icon: 'fa-tv',                color: '#06b6d4' },
        PlataformadeRecarga: { icon: 'fa-charging-station',  color: '#22c55e' }
    };

    /* Toggle serial */
    if (temSerialChk && serialField && serialInput) {
        temSerialChk.addEventListener('change', function () {
            serialField.style.display = this.checked ? 'block' : 'none';
            serialInput.required = this.checked;
            if (!this.checked) { serialInput.value = ''; } else { serialInput.focus(); }
        });
    }

    /* Cards — event delegation em todos os grupos */
    grid.addEventListener('click', function (e) {
        var el = e.target;
        while (el && el !== grid) {
            if (el.classList && el.classList.contains('ca-type-btn')) { selectType(el); return; }
            el = el.parentElement;
        }
    });

    function selectType(btn) {
        grid.querySelectorAll('.ca-type-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var val = btn.getAttribute('data-value') || '';
        tipoHidden.value = val;
        var radio = btn.querySelector('input[type="radio"]');
        if (radio) { radio.checked = true; }
        carregarDropdowns(val);
        atualizarExtras(val);
        atualizarPreview();
        validarDup();
        carregarPainel(val);
    }

    /* Extras por tipo */
    function atualizarExtras(tipo) {
        Object.keys(extras).forEach(function (t) {
            var s = extras[t]; if (!s) return;
            s.style.display = 'none';
            s.querySelectorAll('.ca-extra-input').forEach(function (el) { el.value = ''; });
        });
        if (extras[tipo]) { extras[tipo].style.display = 'block'; }
    }

    /* Preview nome */
    function atualizarPreview() {
        var v   = invInput.value.trim();
        var inv = v ? '#' + v : '#0';
        var sel = modelosSelect ? modelosSelect.options[modelosSelect.selectedIndex] : null;
        var mod = (sel && sel.value) ? sel.text : (tipoHidden.value || '');
        var t   = mod ? mod + ' ' + inv : inv;
        if (badgeTxt)   { badgeTxt.textContent  = inv; }
        if (previewTxt) { previewTxt.textContent = t; }
    }
    invInput.addEventListener('input', atualizarPreview);

    /* Combos dinamicos */
    function carregarDropdowns(tipo) {
        if (!tipo) {
            tiposSelect.innerHTML   = '<option value="">-- Selecione o tipo --</option>';
            modelosSelect.innerHTML = '<option value="">-- Selecione o tipo --</option>';
            return;
        }
        tiposSelect.innerHTML   = '<option value="">Carregando...</option>';
        modelosSelect.innerHTML = '<option value="">Carregando...</option>';
        fetch(ajaxBase + 'GetTypesModels?tipo_ativo=' + encodeURIComponent(tipo), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                fill(tiposSelect,   d.types,  'Selecione o Tipo');
                fill(modelosSelect, d.models, 'Selecione o Modelo');
            })
            .catch(function () {
                tiposSelect.innerHTML   = '<option value="">Erro ao carregar</option>';
                modelosSelect.innerHTML = '<option value="">Erro ao carregar</option>';
            });
    }

    function fill(el, items, ph) {
        if (items && items.length) {
            el.innerHTML = '<option value="">-- ' + ph + ' --</option>' +
                items.map(function (i) { return '<option value="' + i.id + '">' + i.name + '</option>'; }).join('');
        } else {
            el.innerHTML = '<option value="">Nenhum registro</option>';
        }
        if (el === modelosSelect) {
            el.addEventListener('change', atualizarPreview);
            atualizarPreview();
        }
    }

    /* Painel lateral */
    function ajustarAlturaPainel() {
        if (!mainEl || !panel || panel.style.display === 'none') return;
        var h = mainEl.getBoundingClientRect().height;
        if (panelInner) {
            panelInner.style.height    = h + 'px';
            panelInner.style.maxHeight = h + 'px';
        }
        if (panelList) {
            var headerH = panelInner && panelInner.querySelector('#ca-panel-header')
                ? panelInner.querySelector('#ca-panel-header').offsetHeight : 60;
            panelList.style.maxHeight = (h - headerH - 2) + 'px';
            panelList.style.overflowY = 'auto';
        }
    }

    if (window.ResizeObserver && mainEl) {
        new ResizeObserver(function () { ajustarAlturaPainel(); }).observe(mainEl);
    }

    function carregarPainel(tipo) {
        if (!panel) return;
        if (!tipo) { panel.style.display = 'none'; return; }
        var c = typeIconMap[tipo] || { icon: 'fa-box', color: '#6b7280' };
        panel.style.display = 'block';
        ajustarAlturaPainel();
        if (panelIcon)  { panelIcon.style.background = c.color; panelIcon.innerHTML = '<i class="fas ' + c.icon + '"></i>'; }
        if (panelTitle) { panelTitle.textContent = tipo.replace('deRede', ' de Rede').replace('de Recarga', ' de Recarga') + 's cadastrados'; }
        if (panelCount) { panelCount.textContent = 'carregando...'; }
        if (panelList)  { panelList.innerHTML = '<div style="padding:16px;text-align:center;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>'; }

        fetch(ajaxBase + 'ListarAtivos?tipo_ativo=' + encodeURIComponent(tipo), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var ativos = data.ativos || [];
                if (panelCount) {
                    panelCount.textContent = ativos.length + ' cadastrado' + (ativos.length !== 1 ? 's' : '');
                }
                if (!panelList) return;
                if (ativos.length === 0) {
                    panelList.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:.82rem;"><i class="fas fa-inbox" style="font-size:1.6rem;display:block;margin-bottom:6px;opacity:.4;"></i>Nenhum ativo cadastrado.</div>';
                    return;
                }
                var rows = ativos.map(function (a) {
                    return '<div style="display:flex;align-items:center;gap:9px;padding:8px 14px;border-bottom:1px solid #f8fafc;">'
                        + '<div style="width:32px;height:32px;border-radius:7px;background:' + c.color + '18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                        + '<span style="font-size:.68rem;font-weight:800;color:' + c.color + ';">' + (a.otherserial || '-') + '</span>'
                        + '</div>'
                        + '<div style="min-width:0;flex:1;">'
                        + '<div style="font-size:.8rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + a.name + '">' + a.name + '</div>'
                        + '<div style="font-size:.7rem;color:#94a3b8;">' + (a.modelo || '') + '</div>'
                        + '</div>'
                        + '<a href="' + root + '/front/asset/asset.form.php?class=' + encodeURIComponent(tipo) + '&id=' + a.id + '" target="_blank" style="color:#cbd5e1;font-size:.72rem;flex-shrink:0;"><i class="fas fa-external-link-alt"></i></a>'
                        + '</div>';
                }).join('');
                panelList.innerHTML = rows;
                setTimeout(ajustarAlturaPainel, 50);
            })
            .catch(function () {
                if (panelList) panelList.innerHTML = '<div style="padding:16px;text-align:center;color:#ef4444;font-size:.82rem;">Erro ao carregar.</div>';
            });
    }

    /* Validacao duplicidade */
    function validarDup() {
        var tipo = tipoHidden.value, num = invInput.value.trim();
        limparDup(); if (!tipo || !num) return;
        fetch(ajaxBase + 'CheckInventory?tipo_ativo=' + encodeURIComponent(tipo) + '&numero_inventario=' + encodeURIComponent(num), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.duplicado) mostrarDup(); })
            .catch(function () {});
    }
    function mostrarDup() {
        limparDup(); invInput.classList.add('ca-error');
        var d = document.createElement('div'); d.id = 'ca-dup-msg'; d.className = 'ca-dup';
        d.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Numero ja utilizado nesta entidade para este tipo.';
        dupContainer.appendChild(d);
    }
    function limparDup() {
        invInput.classList.remove('ca-error');
        var el = document.getElementById('ca-dup-msg'); if (el) el.remove();
    }
    var timer;
    invInput.addEventListener('blur', function () { clearTimeout(timer); timer = setTimeout(validarDup, 250); });

    /* Envio AJAX */
    if (btnCadastrar) {
        btnCadastrar.addEventListener('click', function () {
            var form = document.getElementById('form_cadastro_ativo_ajax');
            if (!form || !form.checkValidity()) { if (form) form.reportValidity(); return; }
            btnCadastrar.disabled = true;
            btnCadastrar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            var data = new FormData(form);
            data.set('tipo_ativo', tipoHidden.value);
            fetch(root + '/plugins/cadastroativos/ajax/SalvarAtivo', { method: 'POST', credentials: 'same-origin', body: data })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (resp.success) {
                        mostrarSucesso(resp.nome, resp.id, resp.tipoAtivo);
                        form.reset();
                        tipoHidden.value = '';
                        grid.querySelectorAll('.ca-type-btn').forEach(function (b) { b.classList.remove('active'); });
                        tiposSelect.innerHTML   = '<option value="">-- Selecione o tipo --</option>';
                        modelosSelect.innerHTML = '<option value="">-- Selecione o tipo --</option>';
                        Object.keys(extras).forEach(function (t) { var s = extras[t]; if (s) s.style.display = 'none'; });
                        if (serialField) serialField.style.display = 'none';
                        if (panel) panel.style.display = 'none';
                        atualizarPreview();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        if (resp.tipoAtivo) { setTimeout(function () { carregarPainel(resp.tipoAtivo); }, 300); }
                    } else {
                        mostrarErro(resp.errors || ['Erro desconhecido.']);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                })
                .catch(function () { mostrarErro(['Erro de conexao. Tente novamente.']); })
                .finally(function () {
                    btnCadastrar.disabled = false;
                    btnCadastrar.innerHTML = '<i class="fas fa-save"></i> Cadastrar Ativo';
                });
        });
    }

    function mostrarSucesso(nome, id, tipo) {
        if (!msgContainer) return;
        var url = root + '/front/asset/asset.form.php?class=' + encodeURIComponent(tipo) + '&id=' + id;
        msgContainer.innerHTML = '<div class="ca-msg success" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">'
            + '<div style="display:flex;gap:12px;align-items:center;"><i class="fas fa-check-circle" style="font-size:1.1rem;flex-shrink:0;"></i>'
            + '<div><strong>Ativo cadastrado com sucesso!</strong><br>O ativo <strong>' + nome + '</strong> foi registrado no GLPI.</div></div>'
            + '<a href="' + url + '" style="padding:7px 16px;background:#16a34a;color:#fff;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">'
            + '<i class="fas fa-external-link-alt"></i> Visualizar</a></div>';
    }
    function mostrarErro(erros) {
        if (!msgContainer) return;
        msgContainer.innerHTML = '<div class="ca-msg error"><i class="fas fa-exclamation-circle" style="font-size:1.1rem;flex-shrink:0;margin-top:2px;"></i>'
            + '<div><strong>Corrija os erros:</strong><ul style="margin:5px 0 0;padding-left:16px;">'
            + erros.map(function (e) { return '<li>' + e + '</li>'; }).join('')
            + '</ul></div></div>';
    }

    atualizarPreview();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCadastro);
} else {
    initCadastro();
}
window.addEventListener('load', function () {
    var g = document.getElementById('ca-types-grid');
    if (g && !g._caInited) initCadastro();
});

        </script>
        <?php

        Html::footer();
        $html = ob_get_clean();
        return new Response($html);
    }
}
