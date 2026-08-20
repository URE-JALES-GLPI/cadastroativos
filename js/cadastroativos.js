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
    }

    if (btn) {
        btn.addEventListener('click', function () {
            var f = fileInput.files && fileInput.files[0];
            if (!f) return;
            if (!/\.xlsx$/i.test(f.name)) {
                mostrarImportResult(['Somente arquivos .xlsx sao aceitos.'], null, 0);
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
            if (result) {
                result.innerHTML = '<div class="ca-msg" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;">'
                    + '<i class="fas fa-spinner fa-spin" style="flex-shrink:0;margin-top:2px;"></i>'
                    + '<span>Processando arquivo, aguarde...</span></div>';
            }

            var fd = new FormData();
            fd.append('arquivo', f);

            fetch(cfg.ajaxBase + 'ImportarXlsx', {
                method: 'POST',
                credentials: 'same-origin',
                headers: getAjaxHeaders(),
                body: fd
            })
                .then(function (r) {
                    return r.json().catch(function () { throw new Error('Resposta invalida do servidor.'); });
                })
                .then(function (resp) {
                    if (!resp.success) {
                        mostrarImportResult(resp.errors || ['Erro ao processar o arquivo.'], null, 0);
                        return;
                    }
                    mostrarImportResult(resp.erros || [], resp.importados, resp.total);
                })
                .catch(function (e) {
                    mostrarImportResult([e && e.message ? e.message : 'Erro de conexao. Tente novamente.'], null, 0);
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-upload"></i> Importar';
                    }
                });
        });
    }

    function mostrarImportResult(erros, importados, total) {
        if (!result) return;
        var html = '';
        if (typeof importados === 'number') {
            var plural = importados !== 1 ? 's' : '';
            html += '<div class="ca-msg success"><i class="fas fa-check-circle" style="flex-shrink:0;margin-top:2px;"></i>'
                + '<div><strong>Importacao concluida!</strong><br>' + importados + ' de ' + total + ' ativo' + plural
                + ' cadastrado' + plural + ' com sucesso.</div></div>';
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
