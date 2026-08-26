<?php
// Acesso direto: /glpi/plugins/cadastroativos/front/debug.php
// Não exige permissão do plugin, só login. Mostra diagnóstico e permite corrigir.

include('../../../inc/includes.php');

Session::checkLoginUser();
global $DB, $CFG_GLPI;

$pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
$profileName = $_SESSION['glpiactiveprofile']['name'] ?? 'N/A';
$loginUser = (int) Session::getLoginUserID();
$activeEntity = (int) Session::getActiveEntity();
$isAdmin = Session::haveRight('profile', UPDATE);

// Ação de correção
$fixResult = null;
if (isset($_GET['fix'])) {
    $fixTarget = $_GET['fix']; // self | proati | id
    $targetPid = 0;
    $targetName = '';

    if ($fixTarget === 'self') {
        $targetPid = $pid;
        $targetName = $profileName;
    } elseif ($fixTarget === 'proati') {
        // Busca PROATI por nome (case-insensitive)
        $row = $DB->request(['SELECT' => ['id','name'], 'FROM' => 'glpi_profiles', 'WHERE' => ['name' => 'PROATI']])->current();
        if (!$row) {
            // tenta LIKE
            $row = $DB->request(['SELECT' => ['id','name'], 'FROM' => 'glpi_profiles', 'WHERE' => ['name' => ['LIKE', '%PROATI%']]])->current();
        }
        if ($row) { $targetPid = (int)$row['id']; $targetName = $row['name']; }
    } elseif (ctype_digit((string)$fixTarget)) {
        $targetPid = (int)$fixTarget;
        $row = $DB->request(['SELECT' => ['id','name'], 'FROM' => 'glpi_profiles', 'WHERE' => ['id' => $targetPid]])->current();
        if ($row) $targetName = $row['name'];
    }

    // Permissão para corrigir:
    // - admin (profile UPDATE) pode corrigir qualquer
    // - não-admin só pode corrigir a si mesmo (self)
    $canFix = ($isAdmin && $targetPid > 0) || (!$isAdmin && $fixTarget === 'self' && $targetPid === $pid && $targetPid > 0);

    if (!$canFix || $targetPid <= 0) {
        $fixResult = ['success' => false, 'msg' => 'Sem permissão ou perfil não encontrado. Admin pode usar ?fix=proati, usuário comum só ?fix=self.'];
    } else {
        // Garante linhas e seta READ=1 para os 4 direitos
        $rights = ['plugin_cadastroativos_use','plugin_cadastroativos_infra','plugin_cadastroativos_av','plugin_cadastroativos_import'];
        $pr = new ProfileRight();
        $fixed = [];
        foreach ($rights as $rname) {
            $rows = $pr->find(['profiles_id' => $targetPid, 'name' => $rname]);
            if (count($rows) > 0) {
                $existing = array_values($rows)[0];
                $ok = $pr->update(['id' => (int)$existing['id'], 'rights' => READ]);
                $fixed[$rname] = $ok ? 'updated to 1' : 'fail update';
            } else {
                $newId = $pr->add(['profiles_id' => $targetPid, 'name' => $rname, 'rights' => READ]);
                $fixed[$rname] = $newId ? "created id $newId" : 'fail create';
            }
        }
        // Se corrigiu o próprio perfil, recarrega sessão
        if ($targetPid === $pid) {
            if (class_exists('PluginCadastroativosProfile')) {
                PluginCadastroativosProfile::changeProfile();
            }
        }
        $fixResult = ['success' => true, 'msg' => "Perfil $targetName (id $targetPid) corrigido.", 'details' => $fixed];
    }
}

// Coleta diagnóstico
$sessionRights = [
    'plugin_cadastroativos_use'    => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_use'] ?? 'NOT SET',
    'plugin_cadastroativos_infra'  => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_infra'] ?? 'NOT SET',
    'plugin_cadastroativos_av'     => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_av'] ?? 'NOT SET',
    'plugin_cadastroativos_import' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_import'] ?? 'NOT SET',
];
$haveRightCheck = [
    'use'    => Session::haveRight('plugin_cadastroativos_use', READ) ? 'YES' : 'NO',
    'infra'  => Session::haveRight('plugin_cadastroativos_infra', READ) ? 'YES' : 'NO',
    'av'     => Session::haveRight('plugin_cadastroativos_av', READ) ? 'YES' : 'NO',
    'import' => Session::haveRight('plugin_cadastroativos_import', READ) ? 'YES' : 'NO',
];
$dbRights = [];
$dbError = null;
try {
    if ($DB && $DB->tableExists('glpi_profilerights') && $pid > 0) {
        $iter = $DB->request([
            'SELECT' => ['name','rights'],
            'FROM' => 'glpi_profilerights',
            'WHERE' => ['profiles_id'=>$pid, 'name'=>['IN',['plugin_cadastroativos_use','plugin_cadastroativos_infra','plugin_cadastroativos_av','plugin_cadastroativos_import']]]
        ]);
        foreach ($iter as $row) $dbRights[$row['name']] = (int)$row['rights'];
        if (empty($dbRights)) $dbError = 'Nenhuma linha em glpi_profilerights para este pid. Clique em Corrigir.';
    }
} catch (Throwable $e) { $dbError = $e->getMessage(); }

$menuCanView = 'UNKNOWN';
try { $menuCanView = class_exists('GlpiPlugin\\Cadastroativos\\Menu') ? (\GlpiPlugin\Cadastroativos\Menu::canView() ? 'YES' : 'NO') : 'NO CLASS'; } catch (Throwable $e) { $menuCanView = 'ERR: '.$e->getMessage(); }

// Se pedir JSON
if (isset($_GET['json']) && $_GET['json']==='1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'pid'=>$pid,'profileName'=>$profileName,'loginUser'=>$loginUser,'activeEntity'=>$activeEntity,'isAdmin'=>$isAdmin,
        'sessionRights'=>$sessionRights,'haveRightCheck'=>$haveRightCheck,'dbRights'=>$dbRights,'dbError'=>$dbError,'menuCanView'=>$menuCanView,
        'fixResult'=>$fixResult,
        'allProfiles' => (function() use ($DB) {
            $out=[]; try { foreach ($DB->request(['SELECT'=>['id','name'],'FROM'=>'glpi_profiles','ORDER'=>'name']) as $r) $out[]=$r; } catch(Throwable $e){} return $out;
        })()
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

// Header sem checar Menu::canView (evita "Item requisitado não encontrado" para helpdesk)
try {
    Html::header('Diagnóstico Cadastro de Inventário', $_SERVER['PHP_SELF'], 'tools', 'computer');
} catch (Throwable $e) {
    // fallback sem GLPI header (acesso direto ainda funciona)
    echo "<html><head><meta charset='utf-8'><title>Diagnóstico</title></head><body style='margin:0; padding:0; background:#f8fafc;'>";
}

echo "<div style='margin:16px 20px; font-family: sans-serif;'>";
echo "<h2 style='color:#0f172a;'><i class='ti ti-clipboard-list'></i> Diagnóstico - Cadastro de Inventário</h2>";

if ($fixResult) {
    $c = $fixResult['success'] ? '#dcfce7; border-color:#86efac; color:#14532d;' : '#fee2e2; border-color:#fca5a5; color:#7f1d1d;';
    echo "<div style='padding:12px 16px; background:".explode(';',$c)[0]."; border:1px solid ".explode(';',explode(':', $c)[2])[0]."; border-radius:8px; margin-bottom:16px;'><strong>".($fixResult['success']?'OK':'Erro').":</strong> ".htmlspecialchars($fixResult['msg']);
    if (!empty($fixResult['details'])) {
        echo "<pre style='margin:8px 0 0; background:#fff; padding:8px; border-radius:6px; font-size:.8rem;'>".htmlspecialchars(json_encode($fixResult['details'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."</pre>";
    }
    echo "<div style='margin-top:8px; font-size:.85rem;'>Faça <strong>logout/login</strong> ou <a href='".$CFG_GLPI['root_doc']."/front/preference.php'>troque de perfil</a> e teste novamente <a href='".$CFG_GLPI['root_doc']."/plugins/cadastroativos/Cadastro'>/plugins/cadastroativos/Cadastro</a></div>";
    echo "</div>";
}

echo "<div style='background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px;'>";
echo "<h3 style='margin:0 0 12px; font-size:1rem; color:#1e293b;'>Sessão atual</h3>";
echo "<table class='tab_cadre_fixehov' style='width:100%;'>";
echo "<tr><th>pid (profiles_id)</th><td>$pid</td></tr>";
echo "<tr><th>profileName</th><td>".htmlspecialchars($profileName)."</td></tr>";
echo "<tr><th>loginUser</th><td>$loginUser</td></tr>";
echo "<tr><th>activeEntity</th><td>$activeEntity</td></tr>";
echo "<tr><th>isAdmin (profile UPDATE)</th><td>".($isAdmin?'YES':'NO')."</td></tr>";
echo "<tr><th>menuCanView()</th><td><strong>$menuCanView</strong></td></tr>";
echo "</table>";
echo "</div>";

echo "<div style='background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px;'>";
echo "<h3 style='margin:0 0 12px; font-size:1rem; color:#1e293b;'>Direitos na sessão vs banco</h3>";
echo "<table class='tab_cadre_fixehov' style='width:100%; text-align:center;'>";
echo "<tr><th>Right</th><th>Sessão</th><th>haveRight()</th><th>DB (glpi_profilerights)</th></tr>";
foreach (['plugin_cadastroativos_use'=>'Ativos Básicos','plugin_cadastroativos_infra'=>'Infra','plugin_cadastroativos_av'=>'AV','plugin_cadastroativos_import'=>'Import'] as $k=>$label) {
    $sess = htmlspecialchars((string)($sessionRights[$k] ?? 'NOT SET'));
    $hr = $haveRightCheck[str_replace('plugin_cadastroativos_','',$k)] ?? '?';
    $db = isset($dbRights[$k]) ? (int)$dbRights[$k] : '<span style="color:#dc2626;">AUSENTE</span>';
    $sessColor = ($sess==='1' || $sess===1) ? '#dcfce7' : '#fee2e2';
    $dbColor = ($db===1 || $db==='1') ? '#dcfce7' : '#fee2e2';
    echo "<tr><td style='text-align:left;'>$label<br><small style='color:#64748b;'>$k</small></td><td style='background:$sessColor;'>$sess</td><td>$hr</td><td style='background:$dbColor;'>$db</td></tr>";
}
echo "</table>";
if ($dbError) echo "<div style='margin-top:10px; padding:10px; background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; font-size:.85rem; color:#78350f;'><i class='ti ti-alert-triangle'></i> $dbError</div>";
echo "<div style='margin-top:10px; font-size:.84rem; color:#475569; background:#f8fafc; padding:10px; border-radius:8px;'>Se <strong>DB=1</strong> mas <strong>Sessão=NOT SET/0</strong> e <strong>haveRight=NO</strong>: sessão desatualizada — clique em <em>Corrigir minha sessão</em> ou faça logout/login. Se <strong>DB=AUSENTE ou 0</strong>: perfil nunca foi configurado — admin deve clicar <em>Corrigir PROATI</em> ou ir em <strong>Administração &gt; Perfis &gt; PROATI &gt; Cadastro de Inventário &gt; Permitir acesso &gt; Salvar</strong>.</div>";
echo "</div>";

echo "<div style='display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;'>";
echo "<a href='debug.php?fix=self' class='btn btn-primary' style='padding:10px 16px; background:#f59e0b; border:none; color:#fff; border-radius:8px; text-decoration:none; font-weight:700;'><i class='ti ti-refresh'></i> Corrigir MINHA sessão (fix=self)</a>";
if ($isAdmin) {
    echo "<a href='debug.php?fix=proati' class='btn btn-primary' style='padding:10px 16px; background:#0f172a; border:none; color:#fff; border-radius:8px; text-decoration:none; font-weight:700;'><i class='ti ti-wrench'></i> Corrigir perfil PROATI (fix=proati)</a>";
    echo "<a href='debug.php?json=1' class='btn btn-outline' style='padding:10px 16px; border:1px solid #e2e8f0; background:#fff; border-radius:8px; text-decoration:none;'>Ver JSON</a>";
} else {
    echo "<span style='font-size:.8rem; color:#64748b; align-self:center;'>Logado como PROATI (não-admin): só pode corrigir a própria sessão. Peça a um admin para corrigir PROATI se DB estiver AUSENTE.</span>";
}
echo "<a href='".$CFG_GLPI['root_doc']."/plugins/cadastroativos/Cadastro' class='btn' style='padding:10px 16px; background:#16a34a; color:#fff; border-radius:8px; text-decoration:none; font-weight:700;'>Testar /Cadastro</a>";
echo "<a href='".$CFG_GLPI['root_doc']."/plugins/cadastroativos/Cadastro?debug=1' class='btn' style='padding:10px 16px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none;'>Testar /Cadastro?debug=1</a>";
echo "</div>";

// Lista todos os perfis para admin corrigir individualmente
if ($isAdmin) {
    echo "<div style='background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px;'>";
    echo "<h3 style='margin:0 0 12px; font-size:1rem; color:#1e293b;'>Todos os perfis (admin)</h3>";
    echo "<table class='tab_cadre_fixehov' style='width:100%; font-size:.85rem;'>";
    echo "<tr><th>ID</th><th>Perfil</th><th>DB rights</th><th>Ação</th></tr>";
    try {
        foreach ($DB->request(['SELECT'=>['id','name'],'FROM'=>'glpi_profiles','ORDER'=>'name ASC']) as $prof) {
            $prid = (int)$prof['id'];
            $dbIter = $DB->request(['SELECT'=>['name','rights'],'FROM'=>'glpi_profilerights','WHERE'=>['profiles_id'=>$prid,'name'=>['IN',['plugin_cadastroativos_use','plugin_cadastroativos_infra','plugin_cadastroativos_av','plugin_cadastroativos_import']]]]);
            $map=[]; foreach($dbIter as $r) $map[$r['name']]=(int)$r['rights'];
            $summary=[];
            foreach(['plugin_cadastroativos_use','plugin_cadastroativos_infra','plugin_cadastroativos_av','plugin_cadastroativos_import'] as $rn) {
                $v = $map[$rn] ?? '—';
                $c = ($v===1)?'color:#16a34a;font-weight:700;':(($v===0)?'color:#dc2626;':'color:#94a3b8;');
                $short = str_replace('plugin_cadastroativos_','',$rn);
                $summary[]="<span style='$c'>$short:$v</span>";
            }
            $fixUrl = "debug.php?fix=$prid";
            echo "<tr><td>$prid</td><td>".htmlspecialchars($prof['name'])."</td><td>".implode(' | ',$summary)."</td><td><a href='$fixUrl' class='btn btn-sm' style='padding:4px 8px; font-size:.75rem; border:1px solid #e2e8f0; border-radius:6px; text-decoration:none;'>Corrigir (set 1)</a></td></tr>";
        }
    } catch (Throwable $e) { echo "<tr><td colspan='4'>Erro: ".htmlspecialchars($e->getMessage())."</td></tr>"; }
    echo "</table>";
    echo "<div style='margin-top:8px; font-size:.78rem; color:#64748b;'>Botão <em>Corrigir</em> seta todos os 4 direitos para <code>READ=1</code>. Use para PROATI e para qualquer perfil que deve acessar o Cadastro.</div>";
    echo "</div>";
}

echo "<div style='margin-top:16px; padding:12px; background:#f1f5f9; border-radius:8px; font-size:.8rem; color:#475569;'><strong>Após corrigir:</strong> 1) Se corrigiu <em>self</em>, recarregue <a href='".$CFG_GLPI['root_doc']."/plugins/cadastroativos/Cadastro'>/Cadastro</a> — já deve abrir. 2) Se corrigiu PROATI como admin, peça ao usuário PROATI fazer <strong>logout/login</strong> (ou trocar de perfil) para recarregar a sessão. Logs em <code>files/_log/cadastroativos_*.log</code> ajudam.</div>";

echo "</div>";

try { Html::footer(); } catch (Throwable $e) { echo "</body></html>"; }
