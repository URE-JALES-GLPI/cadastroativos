/* Cadastro de Ativos — JS v1.7 */
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
    var panelSearch   = document.getElementById('ca-panel-search');
    var ativosAtuais  = [];
    var tipoAtualPainel = '';
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
        Telefones:           { icon: 'fa-phone',            color: '#0d9488', legacy: true },
        Notebook:            { icon: 'fa-laptop',            color: '#0ea5e9' },
        Tablet:              { icon: 'fa-tablet-alt',        color: '#10b981' },
        Desktop:             { icon: 'fa-desktop',           color: '#f59e0b' },
        Switch:              { icon: 'fa-network-wired',     color: '#64748b' },
        Firewall:            { icon: 'fa-shield-alt',        color: '#ef4444' },
        RackdeRede:          { icon: 'fa-server',            color: '#8b5cf6' },
        Nobreak:             { icon: 'fa-bolt',              color: '#ca8a04' },
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

        // Esconder Modelo para Plataforma de Recarga
        var hideModelo = btn.getAttribute('data-hide-modelo') === '1';
        var modeloGroup = modelosSelect ? modelosSelect.closest('.ca-group') : null;
        if (modeloGroup) {
            modeloGroup.style.display = hideModelo ? 'none' : '';
            modelosSelect.required = !hideModelo;
            if (hideModelo) modelosSelect.value = '';
        }

        carregarDropdowns(val);
        atualizarExtras(val);
        atualizarPreview();
        validarDup();
        carregarPainel(val);
        atualizarAddBtns();
    }

    /* Botoes [+] Tipo/Modelo */
    var addTipoBtn    = document.querySelector('.ca-add-btn[data-campo="assets_assettypes_id"]');
    var addModeloBtn  = document.querySelector('.ca-add-btn[data-campo="assets_assetmodels_id"]');
    function atualizarAddBtns() {
        var t = tipoHidden.value;
        if (addTipoBtn)   { addTipoBtn.disabled   = !t; }
        if (addModeloBtn) { addModeloBtn.disabled = !t || t === 'PlataformadeRecarga'; }
    }
    function abrirModalCriar(campo, tipo) {
        var isTipo = campo === 'assets_assettypes_id';
        var overlay = document.createElement('div');
        overlay.id = 'ca-modal-overlay';
        overlay.innerHTML = '<div id="ca-modal">'
            + '<h3>' + (isTipo ? 'Cadastrar novo Tipo' : 'Cadastrar novo Modelo') + '</h3>'
            + '<p class="ca-modal-sub">Tipo de ativo: <strong>' + tipo + '</strong></p>'
            + '<input type="text" id="ca-modal-nome" placeholder="Nome" maxlength="255">'
            + '<div id="ca-modal-err" class="ca-modal-err"></div>'
            + '<div class="ca-modal-actions">'
            + '<button type="button" class="ca-modal-cancel">Cancelar</button>'
            + '<button type="button" class="ca-modal-save">Salvar</button>'
            + '</div></div>';
        document.getElementById('ca-app').appendChild(overlay);
        var input = overlay.querySelector('#ca-modal-nome');
        var errEl = overlay.querySelector('#ca-modal-err');
        var saveBtn = overlay.querySelector('.ca-modal-save');
        function fechar() { overlay.remove(); }
        overlay.querySelector('.ca-modal-cancel').addEventListener('click', fechar);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) fechar(); });
        saveBtn.addEventListener('click', function () {
            var nome = input.value.trim();
            if (!nome) { errEl.textContent = 'Informe o nome.'; input.focus(); return; }
            errEl.textContent = '';
            saveBtn.disabled = true;
            fetch(root + '/plugins/cadastroativos/ajax/AddDropdown', {
                method: 'POST',
                credentials: 'same-origin',
                headers: getAjaxHeaders(),
                body: new URLSearchParams({ tipo_ativo: tipo, campo: campo, nome: nome })
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('Erro HTTP ' + r.status);
                    return r.json();
                })
                .then(function (resp) {
                    if (!resp.success) {
                        errEl.textContent = (resp.errors || ['Nao foi possivel cadastrar.']).join(' ');
                        saveBtn.disabled = false;
                        return;
                    }
                    fechar();
                    carregarDropdowns(tipo, isTipo ? resp.id : null, isTipo ? null : resp.id);
                })
                .catch(function (e) {
                    errEl.textContent = e && e.message ? e.message : 'Erro de conexao. Tente novamente.';
                    saveBtn.disabled = false;
                });
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') saveBtn.click();
            if (e.key === 'Escape') fechar();
        });
        setTimeout(function () { input.focus(); }, 50);
    }
    if (addTipoBtn) {
        addTipoBtn.addEventListener('click', function () {
            if (tipoHidden.value) abrirModalCriar('assets_assettypes_id', tipoHidden.value);
        });
    }
    if (addModeloBtn) {
        addModeloBtn.addEventListener('click', function () {
            if (tipoHidden.value && tipoHidden.value !== 'PlataformadeRecarga') {
                abrirModalCriar('assets_assetmodels_id', tipoHidden.value);
            }
        });
    }
    atualizarAddBtns();

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
    function carregarDropdowns(tipo, selTipoId, selModeloId) {
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
                fill(tiposSelect,   d.types,  'Selecione o Tipo',   selTipoId);
                fill(modelosSelect, d.models, 'Selecione o Modelo', selModeloId);
            })
            .catch(function () {
                tiposSelect.innerHTML   = '<option value="">Erro ao carregar</option>';
                modelosSelect.innerHTML = '<option value="">Erro ao carregar</option>';
            });
    }

    function fill(el, items, ph, preselect) {
        if (items && items.length) {
            el.innerHTML = '<option value="">-- ' + ph + ' --</option>' +
                items.map(function (i) { return '<option value="' + i.id + '">' + i.name + '</option>'; }).join('');
            if (preselect && items.some(function (i) { return i.id == preselect; })) {
                el.value = String(preselect);
            }
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
        if (!tipo) {
            panel.style.display = 'none';
            if (panelSearch) panelSearch.value = '';
            return;
        }
        tipoAtualPainel = tipo;
        var c = typeIconMap[tipo] || { icon: 'fa-box', color: '#6b7280' };
        panel.style.display = 'block';
        if (panelSearch) panelSearch.value = '';
        ajustarAlturaPainel();
        if (panelIcon)  { panelIcon.style.background = c.color; panelIcon.innerHTML = '<i class="fas ' + c.icon + '"></i>'; }
        if (panelTitle) {
            var nomePainel = tipo.replace('deRede', ' de Rede').replace('de Recarga', ' de Recarga');
            panelTitle.textContent = (nomePainel.slice(-1) === 's' ? nomePainel : nomePainel + 's') + ' cadastrados';
        }
        if (panelCount) { panelCount.textContent = 'carregando...'; }
        if (panelList)  { panelList.innerHTML = '<div style="padding:16px;text-align:center;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>'; }

        fetch(ajaxBase + 'ListarAtivos?tipo_ativo=' + encodeURIComponent(tipo), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                ativosAtuais = data.ativos || [];
                renderPainel();
                setTimeout(ajustarAlturaPainel, 50);
            })
            .catch(function () {
                if (panelList) panelList.innerHTML = '<div style="padding:16px;text-align:center;color:#ef4444;font-size:.82rem;">Erro ao carregar.</div>';
            });
    }

    if (panelSearch) {
        panelSearch.addEventListener('input', function () {
            clearTimeout(panelSearch._timer);
            panelSearch._timer = setTimeout(renderPainel, 150);
        });
    }

    function renderPainel() {
        if (!panelList) return;
        var termo  = (panelSearch ? panelSearch.value : '').trim().toLowerCase();
        var total  = ativosAtuais.length;
        var ativos = termo
            ? ativosAtuais.filter(function (a) {
                return (a.name || '').toLowerCase().indexOf(termo) !== -1
                    || (a.otherserial || '').toLowerCase().indexOf(termo) !== -1
                    || (a.modelo || '').toLowerCase().indexOf(termo) !== -1;
            })
            : ativosAtuais;
        if (panelCount) {
            panelCount.textContent = termo
                ? ativos.length + ' de ' + total
                : total + ' cadastrado' + (total !== 1 ? 's' : '');
        }
        if (ativos.length === 0) {
            panelList.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:.82rem;"><i class="fas fa-inbox" style="font-size:1.6rem;display:block;margin-bottom:6px;opacity:.4;"></i>'
                + (termo ? 'Nenhum resultado para "' + termo + '".' : 'Nenhum ativo cadastrado.') + '</div>';
            return;
        }
        var c = typeIconMap[tipoAtualPainel] || { icon: 'fa-box', color: '#6b7280' };
        var rows = ativos.map(function (a) {
            var url = c.legacy
                ? root + '/front/phone.form.php?id=' + a.id
                : root + '/front/asset/asset.form.php?class=' + encodeURIComponent(tipoAtualPainel) + '&id=' + a.id;
            return '<div style="display:flex;align-items:center;gap:9px;padding:8px 14px;border-bottom:1px solid #f8fafc;">'
                + '<div style="width:32px;height:32px;border-radius:7px;background:' + c.color + '18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '<span style="font-size:.68rem;font-weight:800;color:' + c.color + ';">' + (a.otherserial || '-') + '</span>'
                + '</div>'
                + '<div style="min-width:0;flex:1;">'
                + '<div style="font-size:.8rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + a.name + '">' + a.name + '</div>'
                + '<div style="font-size:.7rem;color:#94a3b8;">' + (a.modelo || '') + '</div>'
                + '</div>'
                + '<a href="' + url + '" target="_blank" style="color:#cbd5e1;font-size:.72rem;flex-shrink:0;"><i class="fas fa-external-link-alt"></i></a>'
                + '</div>';
        }).join('');
        panelList.innerHTML = rows;
    }

    /* Validacao duplicidade */
    function validarDup() {
        var tipo = tipoHidden.value, num = invInput.value.trim();
        limparDup(); if (!tipo || !num) return;
        fetch(ajaxBase + 'CheckInventory?tipo_ativo=' + encodeURIComponent(tipo) + '&numero_inventario=' + encodeURIComponent(num), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (invInput.value.trim() !== num) return;
                if (d.duplicado) mostrarDup();
            })
            .catch(function () {});
    }
    function mostrarDup() {
        limparDup(); invInput.classList.add('ca-error');
        if (btnCadastrar) btnCadastrar.disabled = true;
        var d = document.createElement('div'); d.id = 'ca-dup-msg'; d.className = 'ca-dup';
        d.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Numero ja utilizado nesta entidade para este tipo.';
        dupContainer.appendChild(d);
    }
    function limparDup() {
        invInput.classList.remove('ca-error');
        if (btnCadastrar && !btnCadastrar.dataset.saving) btnCadastrar.disabled = false;
        var el = document.getElementById('ca-dup-msg'); if (el) el.remove();
    }
    var dupTimer;
    invInput.addEventListener('input', function () {
        clearTimeout(dupTimer);
        dupTimer = setTimeout(validarDup, 400);
    });

    /* Envio AJAX */
    function getGlpiCsrfToken() {
        var meta = document.querySelector('meta[property="glpi:csrf_token"]');
        var token = meta ? meta.getAttribute('content') : '';
        if (!token) {
            var m = document.cookie.match(/(?:^|;\s*)glpi_csrf_token=([^;]+)/);
            token = m ? decodeURIComponent(m[1]) : '';
        }
        return token;
    }
    function getAjaxHeaders() {
        return {
            'X-Glpi-Csrf-Token': getGlpiCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        };
    }
    if (btnCadastrar) {
        btnCadastrar.addEventListener('click', function () {
            var form = document.getElementById('form_cadastro_ativo_ajax');
            if (!form || !form.checkValidity()) { if (form) form.reportValidity(); return; }
            btnCadastrar.disabled = true;
            btnCadastrar.dataset.saving = '1';
            btnCadastrar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            var data = new FormData(form);
            data.set('tipo_ativo', tipoHidden.value);
            fetch(root + '/plugins/cadastroativos/ajax/SalvarAtivo', {
                method: 'POST',
                credentials: 'same-origin',
                headers: getAjaxHeaders(),
                body: data
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('Erro HTTP ' + r.status);
                    return r.json();
                })
                .then(function (resp) {
                    if (resp.success) {
                        limparDup();
                        mostrarSucesso(resp.nome, resp.id, resp.tipoAtivo);
                        form.querySelectorAll('input, textarea').forEach(function (el) {
                            if (el.type === 'checkbox' || el.type === 'radio') { el.checked = false; }
                            else { el.value = ''; }
                        });
                        form.querySelectorAll('select').forEach(function (el) {
                            el.selectedIndex = 0;
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        tipoHidden.value = '';
                        grid.querySelectorAll('.ca-type-btn').forEach(function (b) { b.classList.remove('active'); });
                        tiposSelect.innerHTML   = '<option value="">-- Selecione o tipo --</option>';
                        modelosSelect.innerHTML = '<option value="">-- Selecione o tipo --</option>';
                        Object.keys(extras).forEach(function (t) { var s = extras[t]; if (s) s.style.display = 'none'; });
                        if (serialField) serialField.style.display = 'none';
                        if (panel) panel.style.display = 'none';
                        var modeloGroup2 = modelosSelect ? modelosSelect.closest('.ca-group') : null;
                        if (modeloGroup2) { modeloGroup2.style.display = ''; modelosSelect.required = true; }
                        atualizarAddBtns();
                        atualizarPreview();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        if (resp.tipoAtivo) { setTimeout(function () { carregarPainel(resp.tipoAtivo); }, 300); }
                    } else {
                        mostrarErro(resp.errors || ['Erro desconhecido.']);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                })
                .catch(function (e) { mostrarErro([e && e.message ? e.message : 'Erro de conexao. Tente novamente.']); })
                .finally(function () {
                    delete btnCadastrar.dataset.saving;
                    btnCadastrar.disabled = invInput.classList.contains('ca-error');
                    btnCadastrar.innerHTML = '<i class="fas fa-save"></i> Cadastrar Ativo';
                });
        });
    }

    function mostrarSucesso(nome, id, tipo) {
        if (!msgContainer) return;
        var c = typeIconMap[tipo] || {};
        var url = c.legacy
            ? root + '/front/phone.form.php?id=' + id
            : root + '/front/asset/asset.form.php?class=' + encodeURIComponent(tipo) + '&id=' + id;
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

/* Importacao em massa via XLSX */
function initImportXlsx() {
    var fileInput = document.getElementById('ca-import-file');
    if (!fileInput || fileInput._caInited) return;
    fileInput._caInited = true;

    var cfg      = window.CA_CONFIG || { ajaxBase: '/plugins/cadastroativos/ajax/', root: '' };
    var fileName = document.getElementById('ca-import-filename');
    var btn      = document.getElementById('ca-import-btn');
    var result   = document.getElementById('ca-import-result');
    var modelo   = document.getElementById('ca-import-modelo');

    if (modelo) {
        modelo.href = cfg.root + '/plugins/cadastroativos/ModeloXlsx';
    }

    function getGlpiCsrfToken() {
        var meta = document.querySelector('meta[property="glpi:csrf_token"]');
        var token = meta ? meta.getAttribute('content') : '';
        if (!token) {
            var m = document.cookie.match(/(?:^|;\s*)glpi_csrf_token=([^;]+)/);
            token = m ? decodeURIComponent(m[1]) : '';
        }
        return token;
    }
    function getAjaxHeaders() {
        return {
            'X-Glpi-Csrf-Token': getGlpiCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var f = fileInput.files && fileInput.files[0];
            if (fileName) fileName.textContent = f ? f.name : 'Nenhum arquivo selecionado';
            if (btn) btn.disabled = !f;
            if (result) result.innerHTML = '';
        });
        // Arrasta e solta
        var importRow = document.querySelector('.ca-import-row');
        if (importRow) {
            importRow.addEventListener('dragover', function (e) {
                e.preventDefault();
                importRow.style.background = '#eff6ff';
                importRow.style.border = '2px dashed #bfdbfe';
                importRow.style.borderRadius = '10px';
            });
            importRow.addEventListener('dragleave', function (e) {
                e.preventDefault();
                importRow.style.background = '';
                importRow.style.border = '';
            });
            importRow.addEventListener('drop', function (e) {
                e.preventDefault();
                importRow.style.background = '';
                importRow.style.border = '';
                var f = e.dataTransfer.files && e.dataTransfer.files[0];
                if (f) {
                    if (!/\.xlsx$/i.test(f.name)) {
                        mostrarImportResult(['Arraste apenas arquivos .xlsx'], null, 0);
                        return;
                    }
                    var dt = new DataTransfer();
                    dt.items.add(f);
                    fileInput.files = dt.files;
                    if (fileName) fileName.textContent = f.name;
                    if (btn) btn.disabled = false;
                    if (result) result.innerHTML = '<div class="ca-msg" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;"><i class="fas fa-check"></i> Arquivo pronto: ' + f.name + ' — clique em Importar ou Validar</div>';
                }
            });
        }
        // Controles: prévia e duplicados + permitir branco (injetado apenas se nao existe no PHP)
        var importSection = document.querySelector('.ca-import-row');
        if (importSection && !document.getElementById('ca-import-preview-btn')) {
            var opts = document.createElement('div');
            opts.id = 'ca-import-opts';
            opts.style.cssText = 'display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px;font-size:.78rem;color:#475569;';
            var html = '<button type="button" id="ca-import-preview-btn" style="padding:8px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-weight:600;">🔍 Validar antes</button>'
                + '<label style="display:flex;gap:6px;align-items:center;cursor:pointer;"><input type="checkbox" id="ca-import-update" style="accent-color:#f59e0b;"> Atualizar se já existe</label>'
                + '<label style="display:flex;gap:6px;align-items:center;cursor:pointer;">Se duplicado: <select id="ca-import-dup" style="padding:4px 8px;border:1px solid #e2e8f0;border-radius:6px;"><option value="skip" selected>Pular duplicado</option><option value="abort">Parar tudo</option></select></label>';
            opts.innerHTML = html;
            importSection.parentNode.insertBefore(opts, importSection.nextSibling);
            var previewBtn = document.getElementById('ca-import-preview-btn');
            if (previewBtn) {
                previewBtn.addEventListener('click', function () { doImport(true); });
            }
        }
        // Inicializa area de revert
        initRevertArea();
    }

    function doImport(isPreview) {
        var f = fileInput.files && fileInput.files[0];
        if (!f) return;
        if (!/\.xlsx$/i.test(f.name)) {
            mostrarImportResult(['Somente arquivos .xlsx sao aceitos.'], null, 0);
            return;
        }
        var dupSel = document.getElementById('ca-import-dup');
        var updChk = document.getElementById('ca-import-update');
        var onDup = dupSel ? dupSel.value : 'skip';
        var doUpd = updChk && updChk.checked ? '1' : '0';
        var allowEmpty = '1';
        btn.disabled = true;
        var previewBtn = document.getElementById('ca-import-preview-btn');
        if (previewBtn) previewBtn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (isPreview ? 'Validando...' : 'Importando...');
        if (result) {
            result.innerHTML = '<div class="ca-msg" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;">'
                + '<i class="fas fa-spinner fa-spin" style="flex-shrink:0;margin-top:2px;"></i>'
                + '<span>' + (isPreview ? 'Validando arquivo, aguarde...' : 'Processando arquivo, aguarde...') + '</span>'
                + '<div style="width:100%;height:6px;background:#dbeafe;border-radius:3px;margin-top:8px;overflow:hidden;"><div id="ca-progress-bar" style="height:100%;width:0%;background:#3b82f6;transition:width .2s;"></div></div></div>';
        }

        var fd = new FormData();
        fd.append('arquivo', f);
        if (isPreview) fd.append('preview', '1');
        fd.append('on_duplicate', onDup);
        fd.append('update_existing', doUpd);
        fd.append('allow_empty_serial', allowEmpty);
        fd.append('permitir_branco', allowEmpty);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.ajaxBase + 'ImportarXlsx', true);
        xhr.withCredentials = true;
        var headers = getAjaxHeaders();
        for (var k in headers) xhr.setRequestHeader(k, headers[k]);
        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                var bar = document.getElementById('ca-progress-bar');
                if (bar) bar.style.width = pct + '%';
            }
        };
        function renderBlanksInfo(info) {
            if (!info || typeof info !== 'object') return '';
            var keys = Object.keys(info);
            if (!keys.length) return '';
            var rows = keys.map(function (k) {
                var v = info[k];
                if (v && typeof v === 'object') {
                    var sheet = v.sheet != null ? v.sheet : '-';
                    var db = v.db != null ? v.db : '-';
                    var allowed = v.allowed != null ? v.allowed : (v.sheet != null && v.db != null ? Math.max(0, v.sheet - v.db) : '-');
                    var toDelete = v.to_delete != null ? v.to_delete : (v.need_delete != null ? v.need_delete : (v.toDelete != null ? v.toDelete : (v.deleted != null ? v.deleted : 0)));
                    var skipped = v.skipped != null ? v.skipped : (sheet !== '-' && allowed !== '-' ? (sheet - allowed) : 0);
                    return '<tr><td style="padding:4px 8px;border:1px solid #e2e8f0;">' + k + '</td><td style="padding:4px 8px;border:1px solid #e2e8f0;text-align:center;">' + sheet + '</td><td style="padding:4px 8px;border:1px solid #e2e8f0;text-align:center;">' + db + '</td><td style="padding:4px 8px;border:1px solid #e2e8f0;text-align:center;font-weight:700;color:#16a34a;">' + allowed + '</td><td style="padding:4px 8px;border:1px solid #e2e8f0;text-align:center;font-weight:700;color:#dc2626;">' + toDelete + '</td><td style="padding:4px 8px;border:1px solid #e2e8f0;text-align:center;">' + skipped + '</td></tr>';
                }
                return '<tr><td style="padding:4px 8px;border:1px solid #e2e8f0;">' + k + '</td><td style="padding:4px 8px;border:1px solid #e2e8f0;text-align:center;" colspan="5">' + v + '</td></tr>';
            }).join('');
            return '<div class="ca-msg" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#14532d;display:block;"><strong>Detalhe sincronização em branco por categoria:</strong><table style="width:100%;margin-top:8px;border-collapse:collapse;font-size:.78rem;"><thead><tr style="background:#dcfce7;"><th style="padding:4px 8px;border:1px solid #e2e8f0;text-align:left;">Categoria</th><th style="padding:4px 8px;border:1px solid #e2e8f0;">Na planilha</th><th style="padding:4px 8px;border:1px solid #e2e8f0;">Já no GLPI</th><th style="padding:4px 8px;border:1px solid #e2e8f0;">Vai cadastrar</th><th style="padding:4px 8px;border:1px solid #e2e8f0;">Vai apagar</th><th style="padding:4px 8px;border:1px solid #e2e8f0;">Ignorados</th></tr></thead><tbody>' + rows + '</tbody></table><div style="font-size:.72rem;color:#15803d;margin-top:6px;">Sincronização automática: vai cadastrar = max(0, planilha - GLPI) e vai apagar = max(0, GLPI - planilha). Ex: GLPI 10 + planilha 14 = +4; GLPI 14 + planilha 10 = apaga 4.</div></div>';
        }
        xhr.onload = function () {
            btn.disabled = false;
            if (previewBtn) previewBtn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Importar';
            var bar = document.getElementById('ca-progress-bar');
            if (bar) bar.style.width = '100%';
            try {
                var resp = JSON.parse(xhr.responseText);
                if (!resp.success) {
                    mostrarImportResult(resp.errors || ['Erro ao processar o arquivo.'], null, 0, resp.pulados, resp.deletados);
                    if (resp.erros) mostrarImportResult(resp.erros, resp.importados, resp.total, resp.pulados, resp.deletados);
                    if (resp.blanks_info || resp.sheet_blank_per_type) {
                        var info = resp.blanks_info || resp.sheet_blank_per_type;
                        var html = renderBlanksInfo(info);
                        if (html && result) { var d=document.createElement('div'); d.innerHTML=html; result.appendChild(d.firstChild); }
                    }
                    if (resp.deletados && resp.deletados > 0 && result) {
                        var delInfo=document.createElement('div');
                        delInfo.className='ca-msg';
                        delInfo.style.cssText='background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;margin-top:8px;';
                        delInfo.innerHTML='🗑️ ' + resp.deletados + ' ativo(s) em branco apagado(s) para sincronizar.';
                        result.appendChild(delInfo);
                    }
                    return;
                }
                if (resp.preview) {
                    var msg = resp.erros && resp.erros.length ? 'Prévia: ' + resp.erros.length + ' erro(s) encontrados' : 'Prévia OK: ' + resp.importados + ' pronto(s) pra importar' + (resp.deletados ? ' e ' + resp.deletados + ' para apagar' : '');
                    mostrarImportResult(resp.erros || [], resp.importados, resp.total, resp.pulados, resp.deletados);
                    if (result) {
                        var extra = document.createElement('div');
                        extra.className = 'ca-msg ' + (resp.erros && resp.erros.length ? 'error' : 'success');
                        extra.style.marginTop = '8px';
                        extra.innerHTML = '<strong>' + msg + '</strong> — clique em Importar para gravar.';
                        result.appendChild(extra);
                        if (resp.blanks_info) {
                            var h = renderBlanksInfo(resp.blanks_info);
                            if (h) { var tmp=document.createElement('div'); tmp.innerHTML=h; result.appendChild(tmp.firstChild); }
                        } else if (resp.sheet_blank_per_type) {
                            var h2 = renderBlanksInfo(resp.sheet_blank_per_type);
                            if (h2) { var tmp2=document.createElement('div'); tmp2.innerHTML=h2; result.appendChild(tmp2.firstChild); }
                        }
                    }
                    return;
                }
                mostrarImportResult(resp.erros || [], resp.importados, resp.total, resp.pulados, resp.deletados);
                if (result && resp.blanks_info) {
                    var hh = renderBlanksInfo(resp.blanks_info);
                    if (hh) { var t=document.createElement('div'); t.innerHTML=hh; result.appendChild(t.firstChild); }
                }
                if (result && resp.deletados && resp.deletados > 0) {
                    var delOk=document.createElement('div');
                    delOk.className='ca-msg';
                    delOk.style.cssText='background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;margin-top:8px;';
                    delOk.innerHTML='🗑️ ' + resp.deletados + ' ativo(s) em branco removido(s) do GLPI para bater a diferença com a planilha.';
                    result.appendChild(delOk);
                }
                refreshRevertArea();
            } catch (e) {
                mostrarImportResult(['Resposta invalida do servidor.'], null, 0);
            }
        };
        xhr.onerror = function () {
            btn.disabled = false;
            if (previewBtn) previewBtn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Importar';
            mostrarImportResult(['Erro de conexao. Tente novamente.'], null, 0);
        };
        xhr.send(fd);
        return;
    }

    if (btn) {
        btn.addEventListener('click', function () { doImport(false); });
    }

    function mostrarImportResult(erros, importados, total, pulados, deletados) {
        if (!result) return;
        var html = '';
        if (typeof importados === 'number') {
            var plural = importados !== 1 ? 's' : '';
            var puladosTxt = (typeof pulados === 'number' && pulados > 0) ? ' (' + pulados + ' pulado' + (pulados!==1?'s':'') + ')' : '';
            var deletadosTxt = (typeof deletados === 'number' && deletados > 0) ? ' <span style="color:#dc2626;font-weight:700;">| ' + deletados + ' apagado(s)</span>' : '';
            html += '<div class="ca-msg success"><i class="fas fa-check-circle" style="flex-shrink:0;margin-top:2px;"></i>'
                + '<div><strong>Importacao concluida!</strong><br>' + importados + ' de ' + total + ' ativo' + plural
                + ' cadastrado' + plural + ' com sucesso.' + puladosTxt + deletadosTxt + '</div></div>';
        } else if (typeof pulados === 'number' && pulados > 0) {
            html += '<div class="ca-msg" style="background:#fef3c7;border:1px solid #fcd34d;color:#78350f;"><i class="fas fa-info-circle"></i> ' + pulados + ' linha(s) pulada(s) (duplicadas ou série em branco ignorada).</div>';
        }
        if (typeof deletados === 'number' && deletados > 0 && typeof importados !== 'number') {
            html += '<div class="ca-msg" style="background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;"><i class="fas fa-trash"></i> ' + deletados + ' ativo(s) em branco apagado(s) para sincronizar com a planilha.</div>';
        }
        if (erros && erros.length) {
            var items = erros.map(function (e) {
                if (typeof e === 'string') return '<li>' + e + '</li>';
                return '<li>Linha ' + e.linha + ': ' + e.motivo + '</li>';
            }).join('');
            html += '<div class="ca-msg error"><i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>'
                + '<div><strong>Linhas com erro:</strong><ul>' + items + '</ul></div></div>';
        }
        if (!html) {
            html = '<div class="ca-msg success"><i class="fas fa-check-circle" style="flex-shrink:0;margin-top:2px;"></i>'
                + '<span>Nenhum registro encontrado no arquivo.</span></div>';
        }
        result.innerHTML = html;
    }

    // === Revert: ultima implantacao ===
    var lastImportData = null;
    function getRevertEls() {
        return {
            area: document.getElementById('ca-revert-area'),
            btn: document.getElementById('ca-revert-btn'),
            info: document.getElementById('ca-revert-info'),
            result: document.getElementById('ca-revert-result')
        };
    }
    function initRevertArea() {
        var els = getRevertEls();
        if (!els.btn || els.btn._caRevertInited) return;
        els.btn._caRevertInited = true;
        els.btn.addEventListener('click', function () {
            if (!lastImportData) {
                refreshRevertArea(function () {
                    if (lastImportData) abrirModalRevert1(lastImportData);
                });
                return;
            }
            abrirModalRevert1(lastImportData);
        });
        refreshRevertArea();
    }
    window.refreshRevertArea = refreshRevertArea;
    function refreshRevertArea(cb) {
        var els = getRevertEls();
        if (!els.area) { if (cb) cb(); return; }
        fetch(cfg.ajaxBase + 'UltimaImportacao', { credentials: 'same-origin', headers: getAjaxHeaders() })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp.success && resp.has_import && resp.import) {
                    lastImportData = resp.import;
                    els.area.style.display = 'flex';
                    var d = lastImportData.date_creation || '';
                    var f = lastImportData.filename || '';
                    var imp = lastImportData.importados != null ? lastImportData.importados : lastImportData.created_count;
                    var del = lastImportData.deletados != null ? lastImportData.deletados : lastImportData.deleted_count;
                    var txt = 'Última implantação: <strong>' + d + '</strong> — ' + imp + ' criado(s), ' + del + ' apagado(s)';
                    if (f) txt += ' <span style="opacity:.7;">(' + f + ')</span>';
                    if (els.info) els.info.innerHTML = txt;
                    if (els.btn) els.btn.style.display = 'inline-flex';
                } else {
                    lastImportData = null;
                    els.area.style.display = 'none';
                    if (els.info) els.info.innerHTML = '';
                }
                if (cb) cb();
            })
            .catch(function () {
                if (cb) cb();
            });
    }
    function criarOverlayRevert() {
        var overlay = document.createElement('div');
        overlay.id = 'ca-modal-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;z-index:1060;';
        document.body.appendChild(overlay);
        return overlay;
    }
    function abrirModalRevert1(data) {
        var overlay = criarOverlayRevert();
        var imp = data.importados != null ? data.importados : data.created_count;
        var del = data.deletados != null ? data.deletados : data.deleted_count;
        overlay.innerHTML = '<div id="ca-modal" style="width:520px;max-width:calc(100vw - 24px);background:#fff;border-radius:14px;padding:22px;box-shadow:0 20px 50px rgba(0,0,0,.3);">'
            + '<div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;"><div style="width:40px;height:40px;border-radius:10px;background:#fef2f2;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:1.1rem;flex-shrink:0;"><i class="fas fa-exclamation-triangle"></i></div>'
            + '<div><h3 style="margin:0 0 6px;font-size:1rem;color:#0f172a;">Reverter última implantação?</h3>'
            + '<p style="margin:0;font-size:.84rem;color:#475569;line-height:1.5;">Você está prestes a <strong style="color:#dc2626;">reverter TUDO</strong> da sua última implantação nesta entidade.<br>'
            + '<span style="display:inline-block;margin-top:8px;padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:.82rem;">'
            + '<strong>' + imp + '</strong> ativo(s) criado(s) serão <strong>apagados</strong> e <strong>' + del + '</strong> ativo(s) apagado(s) serão <strong>restaurados</strong>.<br>'
            + '<span style="font-size:.76rem;color:#64748b;">Arquivo: ' + (data.filename || '-') + ' — ' + (data.date_creation || '-') + '</span></span></p></div></div>'
            + '<p style="font-size:.78rem;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;padding:8px 10px;border-radius:8px;margin:0;"><i class="fas fa-info-circle"></i> Esta ação afeta apenas a <strong>diferença de série em branco + importados</strong> da ÚLTIMA implantação feita por você nesta entidade.</p>'
            + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">'
            + '<button type="button" class="ca-modal-cancel" style="padding:9px 16px;border-radius:8px;background:#f1f5f9;color:#475569;border:none;font-weight:700;cursor:pointer;">Cancelar</button>'
            + '<button type="button" class="ca-modal-save" style="padding:9px 16px;border-radius:8px;background:#f59e0b;color:#fff;border:none;font-weight:700;cursor:pointer;">Continuar &raquo;</button>'
            + '</div></div>';
        var btnCancel = overlay.querySelector('.ca-modal-cancel');
        var btnCont = overlay.querySelector('.ca-modal-save');
        function fechar(){ overlay.remove(); }
        btnCancel.addEventListener('click', fechar);
        overlay.addEventListener('click', function(e){ if(e.target===overlay) fechar(); });
        btnCont.addEventListener('click', function(){ overlay.remove(); abrirModalRevert2(data); });
    }
    function abrirModalRevert2(data) {
        var overlay = criarOverlayRevert();
        overlay.innerHTML = '<div id="ca-modal" style="width:520px;max-width:calc(100vw - 24px);background:#fff;border-radius:14px;padding:22px;box-shadow:0 20px 50px rgba(0,0,0,.3);">'
            + '<div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;"><div style="width:40px;height:40px;border-radius:10px;background:#dc2626;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;flex-shrink:0;"><i class="fas fa-undo"></i></div>'
            + '<div><h3 style="margin:0 0 6px;font-size:1rem;color:#991b1b;">Confirmação final — tem certeza?</h3>'
            + '<p style="margin:0;font-size:.84rem;color:#475569;line-height:1.5;">Esta é a <strong>segunda confirmação</strong>. Ao confirmar, <strong>TODOS</strong> os ativos da última implantação serão revertidos imediatamente e não há desfazer automático.<br>'
            + '<span style="display:block;margin-top:8px;font-weight:700;color:#dc2626;">Digite REVERTER para habilitar o botão se necessário, ou apenas confirme.</span></p></div></div>'
            + '<div style="margin-top:10px;"><input type="text" id="ca-revert-confirm-input" placeholder="Digite REVERTER para confirmar" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.86rem;outline:none;">'
            + '<div id="ca-revert-err" style="font-size:.76rem;color:#dc2626;margin-top:6px;min-height:1em;"></div></div>'
            + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">'
            + '<button type="button" class="ca-modal-cancel" style="padding:9px 16px;border-radius:8px;background:#f1f5f9;color:#475569;border:none;font-weight:700;cursor:pointer;">Cancelar</button>'
            + '<button type="button" class="ca-modal-save" id="ca-revert-final-btn" style="padding:9px 16px;border-radius:8px;background:#dc2626;color:#fff;border:none;font-weight:700;cursor:pointer;opacity:.6;" disabled><i class="fas fa-undo"></i> SIM, REVERTER TUDO</button>'
            + '</div></div>';
        var btnCancel = overlay.querySelector('.ca-modal-cancel');
        var btnFinal = overlay.querySelector('#ca-revert-final-btn');
        var input = overlay.querySelector('#ca-revert-confirm-input');
        var errEl = overlay.querySelector('#ca-revert-err');
        var allowByTyping = false;
        // Se quiser exigir digitar REVERTER, habilita só quando digitar. Senão, habilita após 1.2s como "leitura". Aqui exigimos digitar.
        // Para flexibilizar: habilita também se usuário apenas clicar após ver o aviso, mas vamos exigir digitar REVERTER ou clicar 2x: vamos habilitar após digitar OU após 1 segundo permitir clique com segundo confirm nativo.
        // Implementação: habilita quando input == REVERTER (case-insensitive) ou após confirmar via confirm() fallback.
        function updateBtn(){
            var v = input.value.trim().toUpperCase();
            if (v === 'REVERTER') {
                btnFinal.disabled = false;
                btnFinal.style.opacity = '1';
                errEl.textContent = '';
            } else {
                btnFinal.disabled = true;
                btnFinal.style.opacity = '.6';
            }
        }
        input.addEventListener('input', updateBtn);
        input.focus();
        function fechar(){ overlay.remove(); }
        btnCancel.addEventListener('click', fechar);
        overlay.addEventListener('click', function(e){ if(e.target===overlay) fechar(); });
        btnFinal.addEventListener('click', function(){
            var v = input.value.trim().toUpperCase();
            if (v !== 'REVERTER') {
                errEl.textContent = 'Digite REVERTER para confirmar.';
                input.focus();
                return;
            }
            overlay.remove();
            executarRevert(data);
        });
        input.addEventListener('keydown', function(e){
            if(e.key==='Enter' && !btnFinal.disabled) btnFinal.click();
            if(e.key==='Escape') fechar();
        });
    }
    function executarRevert(data) {
        var els = getRevertEls();
        if (els.btn) { els.btn.disabled = true; els.btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Revertendo...'; }
        if (els.result) els.result.innerHTML = '<div class="ca-msg" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;"><i class="fas fa-spinner fa-spin"></i> Revertendo implantação #' + data.id + ', aguarde...</div>';
        var fd = new FormData();
        fd.append('confirm', '1');
        fd.append('confirm2', '1');
        fetch(cfg.ajaxBase + 'ReverterImportacao', {
            method: 'POST',
            credentials: 'same-origin',
            headers: getAjaxHeaders(),
            body: fd
        })
        .then(function(r){ return r.json(); })
        .then(function(resp){
            if (els.btn) { els.btn.disabled = false; els.btn.innerHTML = '<i class="fas fa-undo"></i> Reverter última implantação'; }
            if (!resp.success) {
                var msg = (resp.errors || ['Erro ao reverter.']).join(' ');
                if (els.result) els.result.innerHTML = '<div class="ca-msg error"><i class="fas fa-exclamation-circle"></i><div><strong>Erro:</strong> ' + msg + '</div></div>';
                return;
            }
            if (els.result) els.result.innerHTML = '<div class="ca-msg success" style="background:#dcfce7;border-color:#86efac;color:#14532d;"><i class="fas fa-check-circle"></i><div><strong>Reversão concluída!</strong><br>' + resp.message + '</div></div>';
            lastImportData = null;
            refreshRevertArea();
            // Opcional: limpar resultado de importação e recarregar lista
        })
        .catch(function(e){
            if (els.btn) { els.btn.disabled = false; els.btn.innerHTML = '<i class="fas fa-undo"></i> Reverter última implantação'; }
            if (els.result) els.result.innerHTML = '<div class="ca-msg error"><i class="fas fa-exclamation-circle"></i> Erro de conexão: ' + (e.message||'') + '</div>';
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initImportXlsx(); });
} else {
    initImportXlsx();
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
