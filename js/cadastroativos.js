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
    var mainH = main.offsetHeight;
    panel.style.height    = mainH + 'px';
    panel.style.maxHeight = mainH + 'px';
    panel.style.overflowY = 'hidden';
    // A lista interna rola dentro da altura disponivel
    var list = document.getElementById('ca-panel-list');
    if (list) {
        var headerH = panel.querySelector('div > div').offsetHeight || 60;
        list.style.maxHeight = (mainH - headerH) + 'px';
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
