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
        Session::checkRight('plugin_cadastroativos_use', READ);

        $currentEntityId = AssetManager::getCurrentEntityId();
        $availableTypes  = AssetManager::getAvailableTypes();
        $success     = false;
        $successName = '';
        $successId   = 0;

        // Formulario enviado via AJAX (SalvarAtivoController)
        // Este controller so exibe o formulario (GET)

                // ----------------------------------------------------------------
        // Render
        // ----------------------------------------------------------------
        ob_start();
        Html::header('Cadastro de Ativos', '/plugins/cadastroativos/Cadastro', 'tools', Menu::class);

        $entityName = Html::cleanInputText(Dropdown::getDropdownName('glpi_entities', $currentEntityId));
        $csrfToken  = Session::getNewCSRFToken();

        $typeConfig = [
            'Celular'  => ['icon' => 'fa-mobile-alt', 'color' => '#6366f1'],
            'Notebook' => ['icon' => 'fa-laptop',      'color' => '#0ea5e9'],
            'Tablet'   => ['icon' => 'fa-tablet-alt',  'color' => '#10b981'],
            'Desktop'  => ['icon' => 'fa-desktop',     'color' => '#f59e0b'],
        ];

        ?>
        <style>
        #ca-app *, #ca-app *::before, #ca-app *::after { box-sizing: border-box; }
        #ca-app {
            margin: 24px 20px; padding: 0 0 40px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
        }
        /* Header */
        #ca-app .ca-header { display:flex; align-items:center; gap:14px; margin-bottom:20px; padding-bottom:16px; border-bottom:2px solid #f1f5f9; }
        #ca-app .ca-header-icon { width:48px; height:48px; background:linear-gradient(135deg,#f59e0b,#fbbf24); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.3rem; box-shadow:0 4px 12px rgba(245,158,11,.3); flex-shrink:0; }
        #ca-app .ca-header h1 { margin:0 0 3px; font-size:1.4rem; font-weight:700; color:#0f172a; line-height:1.2; }
        #ca-app .ca-header p { margin:0; font-size:.82rem; color:#64748b; }
        /* Alertas */
        #ca-app .ca-msg { display:flex; gap:12px; align-items:flex-start; padding:14px 18px; border-radius:10px; margin-bottom:20px; font-size:.88rem; line-height:1.5; }
        #ca-app .ca-msg-icon { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
        #ca-app .ca-msg.success { background:#dcfce7; border:1px solid #86efac; color:#14532d; }
        #ca-app .ca-msg.error   { background:#fee2e2; border:1px solid #fca5a5; color:#7f1d1d; }
        #ca-app .ca-msg ul { margin:6px 0 0; padding-left:18px; }
        /* Card */
        #ca-app .ca-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 1px 10px rgba(0,0,0,.06),0 4px 24px rgba(0,0,0,.04); overflow:visible; }
        /* Secao tipo */
        #ca-app .ca-section { padding:24px 28px; }
        #ca-app .ca-section-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; margin:0 0 14px; display:flex; align-items:center; gap:6px; }
        /* Cards de tipo */
        #ca-app .ca-types { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        #ca-app .ca-type-btn { position:relative; display:flex; flex-direction:column; align-items:center; gap:8px; padding:18px 8px 14px; border:2px solid #e2e8f0; border-radius:12px; cursor:pointer; background:#f8fafc; transition:all .18s ease; user-select:none; }
        #ca-app .ca-type-btn:hover { border-color:#cbd5e1; background:#f1f5f9; transform:translateY(-1px); }
        #ca-app .ca-type-btn.active { background:#fff; transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.1); }
        #ca-app .ca-type-btn input[type="radio"] { position:absolute; opacity:0; width:0; height:0; }
        #ca-app .ca-type-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; color:#fff; transition:transform .18s ease; }
        #ca-app .ca-type-btn.active .ca-type-icon { transform:scale(1.08); }
        #ca-app .ca-type-label { font-size:.8rem; font-weight:600; color:#475569; transition:color .18s; }
        #ca-app .ca-type-btn.active .ca-type-label { color:#0f172a; font-weight:700; }
        #ca-app .ca-type-check { position:absolute; top:7px; right:7px; width:16px; height:16px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:8px; color:transparent; transition:all .18s; }
        #ca-app .ca-type-btn.active .ca-type-check { background:#22c55e; color:#fff; }
        /* Divisor */
        #ca-app .ca-divider { height:1px; background:#f1f5f9; margin:0; }
        /* Form */
        #ca-app .ca-form { padding:24px 28px; }
        #ca-app .ca-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px 22px; }
        #ca-app .ca-col-full { grid-column:1/-1; }
        #ca-app .ca-group { display:flex; flex-direction:column; gap:5px; }
        #ca-app .ca-label { font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#64748b; display:flex; align-items:center; gap:5px; }
        #ca-app .ca-label i { opacity:.6; font-size:.75rem; }
        #ca-app .ca-req { color:#ef4444; }
        #ca-app .ca-input { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.9rem; color:#1e293b; background:#fff; outline:none; transition:border-color .15s,box-shadow .15s; }
        #ca-app .ca-input:focus { border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.14); }
        #ca-app .ca-input::placeholder { color:#cbd5e1; }
        #ca-app .ca-input.ca-error { border-color:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.1); }
        /* Inventario */
        #ca-app .ca-inv-row { display:flex; gap:10px; align-items:stretch; }
        #ca-app .ca-inv-row .ca-input { flex:1; }
        #ca-app .ca-badge { padding:0 16px; background:linear-gradient(135deg,#f59e0b,#fbbf24); border-radius:8px; color:#fff; font-weight:700; font-size:.85rem; white-space:nowrap; display:flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(245,158,11,.25); min-width:68px; justify-content:center; }
        #ca-app .ca-hint { font-size:.76rem; color:#94a3b8; margin-top:2px; }
        /* Select nativo */
        #ca-app .ca-select { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.9rem; color:#1e293b; background:#fff; cursor:pointer; outline:none; transition:border-color .15s,box-shadow .15s; appearance:auto; }
        #ca-app .ca-select:focus { border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.14); }
        /* Dropdown GLPI */
        #ca-app .ca-dropdown-wrap { width:100%; }
        #ca-app .ca-dropdown-wrap select { width:100% !important; }
        #ca-app .ca-dropdown-wrap .select2-container,
        #ca-app .ca-dropdown-wrap .chosen-container { width:100% !important; max-width:100% !important; }
        /* Serial toggle */
        #ca-app .ca-serial-toggle { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
        #ca-app .ca-toggle-label { display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.88rem; color:#475569; font-weight:500; }
        #ca-app .ca-toggle-label input[type="checkbox"] { width:17px; height:17px; cursor:pointer; accent-color:#f59e0b; }
        /* Campos extras por tipo */
        #ca-app .ca-extra-fields { display:none; }
        #ca-app .ca-extra-fields.visible { display:contents; }
        /* Storage row (Desktop) */
        #ca-app .ca-storage-row { display:grid; grid-template-columns:140px 1fr; gap:10px; }
        /* Alerta duplicidade */
        #ca-app .ca-dup { display:flex; align-items:center; gap:7px; padding:8px 12px; background:#fee2e2; border:1px solid #fca5a5; border-radius:7px; font-size:.8rem; color:#991b1b; margin-top:6px; animation:ca-in .2s ease; }
        /* Secao extras (fundo leve) */
        #ca-app .ca-extras-section { background:#fafbff; border:1px solid #e8edff; border-radius:10px; padding:16px 18px; display:none; }
        #ca-app .ca-extras-section.visible { display:block; }
        #ca-app .ca-extras-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#6366f1; margin:0 0 14px; display:flex; align-items:center; gap:6px; }
        #ca-app .ca-extras-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
        #ca-app .ca-extras-grid .ca-col-full { grid-column:1/-1; }
        /* Rodape */
        #ca-app .ca-footer { display:flex; align-items:center; justify-content:space-between; margin-top:24px; padding-top:20px; border-top:1px solid #f1f5f9; gap:12px; flex-wrap:wrap; }
        #ca-app .ca-entity { display:flex; align-items:center; gap:6px; font-size:.8rem; color:#64748b; font-weight:600; background:#f8fafc; padding:6px 14px; border-radius:20px; border:1px solid #e2e8f0; }
        #ca-app .ca-submit { padding:11px 30px; background:linear-gradient(135deg,#f59e0b,#fbbf24); color:#fff; border:none; border-radius:10px; font-size:.92rem; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(245,158,11,.35); display:flex; align-items:center; gap:8px; transition:all .18s ease; }
        #ca-app .ca-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(245,158,11,.45); }
        #ca-app .ca-submit:active { transform:translateY(0); }
        @keyframes ca-in { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:translateY(0)} }
        </style>

        <style>/* 
 * Cadastro de Ativos — estilos auxiliares
 * Os estilos principais estao embutidos diretamente no controller
 * para garantir que nao sejam sobrescritos pelos estilos do GLPI.
 */

/* Garante que os selects do GLPI (select2/chosen) fiquem dentro do grid */
#ca-app .ca-dropdown-wrap .select2-container,
#ca-app .ca-dropdown-wrap .chosen-container {
    width: 100% !important;
    max-width: 100% !important;
}
</style>
        <div id="ca-app">
        <div class="ca-header">
                <div class="ca-header-icon"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <h1>Cadastro de Ativos</h1>
                    <p><i class="fas fa-building" style="margin-right:4px"></i><?= $entityName ?></p>
                </div>
            </div>

        <div id="ca-layout" style="display:flex;gap:20px;align-items:flex-start;width:100%;margin-top:20px;">
        <div id="ca-main" style="flex:1;min-width:0;max-width:820px;">

            <!-- Container de mensagens AJAX (erros e sucesso aparecem aqui sem recarregar) -->
            <div id="ca-msg-container">
            <?php if ($success): ?>
            <div class="ca-msg success" style="justify-content:space-between;align-items:center;">
                <div style="display:flex;gap:12px;align-items:flex-start;">
                    <i class="fas fa-check-circle ca-msg-icon"></i>
                    <div><strong>Ativo cadastrado com sucesso!</strong><br>
                    O ativo <strong><?= Html::cleanInputText($successName) ?></strong> foi registrado no GLPI.</div>
                </div>
                <?php if ($successId > 0): ?>
                <a href="/glpi/front/asset/asset.form.php?class=<?= urlencode($tipoAtivo) ?>&id=<?= $successId ?>"
                   style="flex-shrink:0;padding:8px 18px;background:#16a34a;color:#fff;border-radius:8px;font-size:.85rem;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:6px;white-space:nowrap;">
                    <i class="fas fa-external-link-alt"></i> Visualizar Ativo
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="ca-msg error">
                <i class="fas fa-exclamation-circle ca-msg-icon"></i>
                <div><strong>Corrija os erros abaixo:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= Html::cleanInputText($e) ?></li><?php endforeach; ?></ul></div>
            </div>
            <?php endif; ?>

            </div><!-- /ca-msg-container -->

            <?php if (empty($availableTypes)): ?>
            <div class="ca-msg" style="background:#fef3c7;border:1px solid #fcd34d;color:#78350f;">
                <i class="fas fa-exclamation-triangle ca-msg-icon"></i>
                <span>Nenhum tipo de ativo encontrado. Verifique em <strong>Configurar &gt; Ativos &gt; Definicoes de Ativos</strong>.</span>
            </div>
            <?php else: ?>

            <div class="ca-card">

                <!-- Selecao de tipo -->
                <div class="ca-section">
                    <p class="ca-section-title"><i class="fas fa-tags"></i> Selecione o tipo de ativo</p>
                    <div class="ca-types" id="ca-types-grid">
                        <?php foreach ($availableTypes as $systemName => $label):
                            $cfg   = $typeConfig[$systemName] ?? ['icon' => 'fa-box', 'color' => '#6b7280'];
                            $color = $cfg['color'];
                            $icon  = $cfg['icon'];
                        ?>
                        <div class="ca-type-btn" data-value="<?= Html::cleanInputText($systemName) ?>" tabindex="0">
                            <input type="radio" name="_tipo_radio" value="<?= Html::cleanInputText($systemName) ?>">
                            <div class="ca-type-check"><i class="fas fa-check"></i></div>
                            <div class="ca-type-icon" style="background:<?= $color ?>22;color:<?= $color ?>">
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            <span class="ca-type-label"><?= Html::cleanInputText($label) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ca-divider"></div>

                <!-- Formulario -->
                <form name="form_cadastro_ativo" method="post" id="form_cadastro_ativo_ajax" class="ca-form">
                    <?= Html::hidden('_glpi_csrf_token', ['value' => $csrfToken]) ?>
                    <input type="hidden" name="tipo_ativo" id="tipo_ativo_hidden" value="">

                    <div class="ca-grid">

                        <!-- N° Inventario -->
                        <div class="ca-group ca-col-full">
                            <label class="ca-label"><i class="fas fa-barcode"></i> Numero de Inventario <span class="ca-req">*</span></label>
                            <div class="ca-inv-row">
                                <input type="number" name="numero_inventario" id="numero_inventario"
                                    class="ca-input" placeholder="Ex: 1001" min="1" max="999999" step="1" required>
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

                        <!-- Numero de Serie com toggle -->
                        <div class="ca-group ca-col-full">
                            <label class="ca-label"><i class="fas fa-hashtag"></i> Numero de Serie</label>
                            <div class="ca-serial-toggle">
                                <label class="ca-toggle-label">
                                    <input type="checkbox" id="tem_serial_check" name="tem_serial" value="1">
                                    Este ativo possui Numero de Serie
                                </label>
                            </div>
                            <div id="serial_field" style="display:none;">
                                <input type="text" name="serial" id="serial"
                                    class="ca-input" placeholder="Digite o numero de serie"
                                    maxlength="255" style="margin-top:4px;">
                            </div>
                        </div>

                        <!-- Campos extras por tipo (injetados via JS / visibilidade controlada) -->

                        <!-- CELULAR: RAM + Armazenamento + IMEI -->
                        <div class="ca-col-full" id="extras_Celular" style="display:none;">
                            <div class="ca-extras-section visible">
                                <p class="ca-extras-title"><i class="fas fa-mobile-alt"></i> Informacoes do Celular</p>
                                <div class="ca-extras-grid">
                                    <div class="ca-group">
                                        <label class="ca-label"><i class="fas fa-memory"></i> Memoria RAM <span class="ca-req">*</span></label>
                                        <input type="text" name="custom_memoria_ram" class="ca-input ca-extra-input" placeholder="Ex: 4 GB" maxlength="50">
                                    </div>
                                    <div class="ca-group">
                                        <label class="ca-label"><i class="fas fa-hdd"></i> Armazenamento <span class="ca-req">*</span></label>
                                        <input type="text" name="custom_armazenamento" class="ca-input ca-extra-input" placeholder="Ex: 128 GB" maxlength="50">
                                    </div>
                                    <div class="ca-group ca-col-full">
                                        <label class="ca-label"><i class="fas fa-sim-card"></i> IMEI <span class="ca-req">*</span></label>
                                        <input type="text" name="custom_imei" class="ca-input ca-extra-input" placeholder="Ex: 358971234567890" maxlength="20">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notebook: sem campos extras -->

                        <!-- DESKTOP: RAM + Tipo Storage + Capacidade -->
                        <div class="ca-col-full" id="extras_Desktop" style="display:none;">
                            <div class="ca-extras-section visible">
                                <p class="ca-extras-title"><i class="fas fa-desktop"></i> Informacoes do Desktop</p>
                                <div class="ca-extras-grid">
                                    <div class="ca-group">
                                        <label class="ca-label"><i class="fas fa-memory"></i> Memoria RAM <span class="ca-req">*</span></label>
                                        <input type="text" name="custom_memoria_ram" class="ca-input ca-extra-input" placeholder="Ex: 16 GB" maxlength="50">
                                    </div>
                                    <div class="ca-group">
                                        <label class="ca-label"><i class="fas fa-hdd"></i> Armazenamento <span class="ca-req">*</span></label>
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

                    <!-- Rodape -->
                    <div class="ca-footer">
                        <div class="ca-entity"><i class="fas fa-building"></i><?= $entityName ?></div>
                        <button type="button" id="btn-cadastrar" class="ca-submit">
                            <i class="fas fa-save"></i> Cadastrar Ativo
                        </button>
                    </div>

                </form>
            </div>

            <?php endif; ?>

        </div><!-- /ca-main -->

        <!-- Painel lateral: lista de ativos cadastrados -->
        <div id="ca-panel" style="width:320px;flex-shrink:0;display:none;align-self:flex-start;">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 1px 10px rgba(0,0,0,.06);overflow:hidden;display:flex;flex-direction:column;">
                <div style="padding:16px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div id="ca-panel-icon" style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff;background:#6366f1;flex-shrink:0;">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div>
                            <div id="ca-panel-title" style="font-weight:700;font-size:.875rem;color:#0f172a;">Ativos cadastrados</div>
                            <div id="ca-panel-count" style="font-size:.72rem;color:#94a3b8;">carregando...</div>
                        </div>
                    </div>
                </div>
                <div id="ca-panel-list" style="flex:1;overflow-y:auto;padding:8px 0;">
                    <div style="padding:20px;text-align:center;color:#94a3b8;font-size:.85rem;">
                        <i class="fas fa-spinner fa-spin"></i> Carregando...
                    </div>
                </div>
            </div>
        </div><!-- /ca-panel -->

        </div><!-- /ca-layout -->

                <script>
        var CA_CONFIG = {
            ajaxBase: (typeof CFG_GLPI !== 'undefined' ? CFG_GLPI.root_doc : '') + '/plugins/cadastroativos/ajax/',
            root: (typeof CFG_GLPI !== 'undefined' ? CFG_GLPI.root_doc : '')
        };
        /* Cadastro de Ativos - JS principal */

function initCadastro() {
    var grid = document.getElementById('ca-types-grid');
    if (!grid || grid._caInited) return;
    grid._caInited = true;

    var cfg       = window.CA_CONFIG || { ajaxBase: '/plugins/cadastroativos/ajax/', root: '' };
    var ajaxBase  = cfg.ajaxBase;
    var root      = cfg.root;

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
    var panelList     = document.getElementById('ca-panel-list');
    var panelTitle    = document.getElementById('ca-panel-title');
    var panelCount    = document.getElementById('ca-panel-count');
    var panelIcon     = document.getElementById('ca-panel-icon');
    var msgContainer  = document.getElementById('ca-msg-container');
    var btnCadastrar  = document.getElementById('btn-cadastrar');

    if (!tipoHidden || !invInput) return;

    var extras = {
        Celular: document.getElementById('extras_Celular'),
        Desktop: document.getElementById('extras_Desktop')
    };

    var typeIconMap = {
        Celular:  { icon: 'fa-mobile-alt', color: '#6366f1' },
        Notebook: { icon: 'fa-laptop',     color: '#0ea5e9' },
        Tablet:   { icon: 'fa-tablet-alt', color: '#10b981' },
        Desktop:  { icon: 'fa-desktop',    color: '#f59e0b' }
    };

    /* Toggle serial */
    if (temSerialChk && serialField && serialInput) {
        temSerialChk.addEventListener('change', function () {
            serialField.style.display = this.checked ? 'block' : 'none';
            serialInput.required = this.checked;
            if (!this.checked) { serialInput.value = ''; } else { serialInput.focus(); }
        });
    }

    /* Cards de tipo */
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
            var s = extras[t];
            if (!s) { return; }
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
    function carregarPainel(tipo) {
        if (!panel) { return; }
        if (!tipo) { panel.style.display = 'none'; return; }
        var c = typeIconMap[tipo] || { icon: 'fa-box', color: '#6b7280' };
        panel.style.display = 'block';
        ajustarAlturaPainel();
        if (panelIcon)  { panelIcon.style.background = c.color; panelIcon.innerHTML = '<i class="fas ' + c.icon + '"></i>'; }
        if (panelTitle) { panelTitle.textContent = tipo + 's cadastrados'; }
        if (panelCount) { panelCount.textContent  = 'carregando...'; }
        if (panelList)  { panelList.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>'; }

        fetch(ajaxBase + 'ListarAtivos?tipo_ativo=' + encodeURIComponent(tipo), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var ativos = data.ativos || [];
                if (panelCount) {
                    panelCount.textContent = ativos.length + ' ativo' + (ativos.length !== 1 ? 's' : '') + ' cadastrado' + (ativos.length !== 1 ? 's' : '');
                }
                if (!panelList) { return; }
                if (ativos.length === 0) {
                    panelList.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:.85rem;"><i class="fas fa-inbox" style="font-size:1.8rem;display:block;margin-bottom:8px;opacity:.4;"></i>Nenhum ativo cadastrado<br>nesta entidade ainda.</div>';
                    return;
                }
                var lastInv = 0;
                var rows = '';
                ativos.forEach(function (a) {
                    var inv = parseInt(a.otherserial, 10) || 0;
                    if (inv > lastInv) { lastInv = inv; }
                    rows += '<div style="display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid #f1f5f9;">'
                          +   '<div style="width:34px;height:34px;border-radius:8px;background:' + c.color + '22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                          +     '<span style="font-size:.7rem;font-weight:800;color:' + c.color + ';">' + (a.otherserial || '-') + '</span>'
                          +   '</div>'
                          +   '<div style="min-width:0;flex:1;">'
                          +     '<div style="font-size:.82rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + a.name + '">' + a.name + '</div>'
                          +     '<div style="font-size:.72rem;color:#94a3b8;">' + (a.modelo || '') + '</div>'
                          +   '</div>'
                          +   '<a href="' + root + '/front/asset/asset.form.php?class=' + encodeURIComponent(tipo) + '&id=' + a.id + '" target="_blank" style="color:#cbd5e1;font-size:.75rem;flex-shrink:0;"><i class="fas fa-external-link-alt"></i></a>'
                          + '</div>';
                });
                var proximo = lastInv + 1;
                rows += '<div style="padding:10px 16px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">'
                      +   '<span style="font-size:.75rem;color:#64748b;">Proximo sugerido: </span>'
                      +   '<strong id="ca-proximo" style="font-size:.82rem;color:#f59e0b;cursor:pointer;" data-num="' + proximo + '">'
                      +     '#' + proximo + ' <i class="fas fa-mouse-pointer" style="font-size:.65rem;"></i>'
                      +   '</strong>'
                      + '</div>';
                panelList.innerHTML = rows;
                setTimeout(ajustarAlturaPainel, 100);

                /* Clique no proximo sugerido */
                var elProximo = document.getElementById('ca-proximo');
                if (elProximo) {
                    elProximo.addEventListener('click', function () {
                        invInput.value = this.getAttribute('data-num');
                        invInput.dispatchEvent(new Event('input'));
                        invInput.focus();
                    });
                }
            })
            .catch(function () {
                if (panelList) { panelList.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;">Erro ao carregar lista.</div>'; }
            });
    }

    /* Validacao duplicidade */
    function validarDup() {
        var tipo = tipoHidden.value;
        var num  = invInput.value.trim();
        limparDup();
        if (!tipo || !num) { return; }
        fetch(ajaxBase + 'CheckInventory?tipo_ativo=' + encodeURIComponent(tipo) + '&numero_inventario=' + encodeURIComponent(num), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.duplicado) { mostrarDup(); } })
            .catch(function () {});
    }
    function mostrarDup() {
        limparDup();
        invInput.classList.add('ca-error');
        var d = document.createElement('div');
        d.id = 'ca-dup-msg';
        d.className = 'ca-dup';
        d.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Numero ja utilizado nesta entidade para este tipo.';
        dupContainer.appendChild(d);
    }
    function limparDup() {
        invInput.classList.remove('ca-error');
        var el = document.getElementById('ca-dup-msg');
        if (el) { el.remove(); }
    }
    var timer;
    invInput.addEventListener('blur', function () { clearTimeout(timer); timer = setTimeout(validarDup, 250); });

    /* Envio AJAX */
    if (btnCadastrar) {
        btnCadastrar.addEventListener('click', function () {
            var form = document.getElementById('form_cadastro_ativo_ajax');
            if (!form || !form.checkValidity()) { if (form) { form.reportValidity(); } return; }
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
                        Object.keys(extras).forEach(function (t) { var s = extras[t]; if (s) { s.style.display = 'none'; } });
                        if (serialField) { serialField.style.display = 'none'; }
                        atualizarPreview();
                        if (resp.tipoAtivo) { carregarPainel(resp.tipoAtivo); }
                        window.scrollTo({ top: 0, behavior: 'smooth' });
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
        if (!msgContainer) { return; }
        var url = root + '/front/asset/asset.form.php?class=' + encodeURIComponent(tipo) + '&id=' + id;
        msgContainer.innerHTML = '<div class="ca-msg success" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">'
            + '<div style="display:flex;gap:12px;align-items:center;"><i class="fas fa-check-circle ca-msg-icon"></i>'
            + '<div><strong>Ativo cadastrado com sucesso!</strong><br>O ativo <strong>' + nome + '</strong> foi registrado no GLPI.</div></div>'
            + '<a href="' + url + '" style="padding:8px 18px;background:#16a34a;color:#fff;border-radius:8px;font-size:.85rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">'
            + '<i class="fas fa-external-link-alt"></i> Visualizar Ativo</a></div>';
    }
    function mostrarErro(erros) {
        if (!msgContainer) { return; }
        msgContainer.innerHTML = '<div class="ca-msg error"><i class="fas fa-exclamation-circle ca-msg-icon"></i>'
            + '<div><strong>Corrija os erros abaixo:</strong><ul>'
            + erros.map(function (e) { return '<li>' + e + '</li>'; }).join('')
            + '</ul></div></div>';
    }

    atualizarPreview();
}

/* Ajusta altura do painel para igualar o card esquerdo */
function ajustarAlturaPainel() {
    var main  = document.getElementById('ca-main');
    var panel = document.getElementById('ca-panel');
    if (!main || !panel || panel.style.display === 'none') { return; }

    // Usar getBoundingClientRect para altura real incluindo padding/border/margin
    var mainRect  = main.getBoundingClientRect();
    var mainH     = mainRect.height;

    panel.style.height    = mainH + 'px';
    panel.style.maxHeight = mainH + 'px';
    panel.style.overflowY = 'hidden';

    // Lista interna: altura total menos o cabecalho do painel
    var list    = document.getElementById('ca-panel-list');
    var panelEl = panel.querySelector('div');
    if (list && panelEl) {
        var panelInner  = panelEl;
        var headerDiv   = panelInner.querySelector('div');
        var headerH     = headerDiv ? headerDiv.offsetHeight : 60;
        var availableH  = mainH - headerH;
        list.style.maxHeight = availableH + 'px';
        list.style.height    = availableH + 'px';
        list.style.overflowY = 'auto';
    }
}

/* Observa mudancas de tamanho no card esquerdo */
if (window.ResizeObserver) {
    var ro = new ResizeObserver(function() { ajustarAlturaPainel(); });
    var mainEl = document.getElementById('ca-main');
    if (mainEl) { ro.observe(mainEl); }
}

/* Inicializa assim que possivel */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCadastro);
} else {
    initCadastro();
}
window.addEventListener('load', function () {
    var g = document.getElementById('ca-types-grid');
    if (g && !g._caInited) { initCadastro(); }
    ajustarAlturaPainel();
});

        </script>

        <?php
        Html::footer();
        $html = ob_get_clean();
        return new Response($html);
    }
}
