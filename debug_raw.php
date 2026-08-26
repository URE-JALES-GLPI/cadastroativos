<?php
// Acesso direto sem rota Symfony: http://10.180.152.27/glpi/plugins/cadastroativos/debug_raw.php
// Alternativa: http://10.180.152.27/glpi/plugins/cadastroativos/front/debug.php (recomendado, tem botão de correção)
// So precisa estar logado no GLPI
$includes = __DIR__ . '/../../inc/includes.php';
if (!file_exists($includes)) $includes = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($includes)) $includes = __DIR__ . '/../../glpi/inc/includes.php';
include($includes);
Session::checkLoginUser();
global $DB;
$pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
header('Content-Type: application/json; charset=utf-8');
$out = [
    'pid' => $pid,
    'profileName' => $_SESSION['glpiactiveprofile']['name'] ?? 'N/A',
    'loginUser' => (int) Session::getLoginUserID(),
    'sessionRights' => [
        'use' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_use'] ?? 'NOT SET',
        'infra' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_infra'] ?? 'NOT SET',
        'av' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_av'] ?? 'NOT SET',
        'import' => $_SESSION['glpiactiveprofile']['plugin_cadastroativos_import'] ?? 'NOT SET',
    ],
    'haveRight' => [
        'use' => Session::haveRight('plugin_cadastroativos_use', READ) ? 'YES' : 'NO',
        'infra' => Session::haveRight('plugin_cadastroativos_infra', READ) ? 'YES' : 'NO',
        'av' => Session::haveRight('plugin_cadastroativos_av', READ) ? 'YES' : 'NO',
        'import' => Session::haveRight('plugin_cadastroativos_import', READ) ? 'YES' : 'NO',
    ],
    'menuCanView' => class_exists('GlpiPlugin\\Cadastroativos\\Menu') ? (\GlpiPlugin\Cadastroativos\Menu::canView() ? 'YES' : 'NO') : 'NO CLASS',
];
try {
    if ($DB && $DB->tableExists('glpi_profilerights') && $pid > 0) {
        $iter = $DB->request([
            'SELECT' => ['name','rights'],
            'FROM' => 'glpi_profilerights',
            'WHERE' => ['profiles_id'=>$pid, 'name'=>['IN',['plugin_cadastroativos_use','plugin_cadastroativos_infra','plugin_cadastroativos_av','plugin_cadastroativos_import']]]
        ]);
        foreach ($iter as $r) $out['db_'.$r['name']] = (int)$r['rights'];
        if (!isset($out['db_plugin_cadastroativos_use'])) $out['db_hint'] = 'Nenhuma linha encontrada - abra Administracao > Perfis > [perfil] > Cadastro de Inventario e clique Salvar';
    }
} catch (Throwable $e) { $out['db_error']=$e->getMessage(); }
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
