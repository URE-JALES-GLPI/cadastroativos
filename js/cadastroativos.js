/* Cadastro de Ativos — JS v1.6 */
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
