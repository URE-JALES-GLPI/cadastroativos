<?php
// Diagnóstico CLI: php front/diag_cli.php
// Não precisa login HTTP, roda direto no servidor como root
// Uso: cd /var/www/html/glpi && php plugins/cadastroativos/front/diag_cli.php

// Tenta achar includes de ambos os caminhos
$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) $inc = __DIR__ . '/../../inc/includes.php';
if (!file_exists($inc)) $inc = '/var/www/html/glpi/inc/includes.php';
if (!file_exists($inc)) $inc = '/var/www/glpi/inc/includes.php';
if (!file_exists($inc)) { fwrite(STDERR, "includes.php não encontrado\n"); exit(1); }
include($inc);

global $DB, $CFG_GLPI;

echo "=== CadastroAtivos diag_cli ===\n";
echo "GLPI_ROOT: ".(defined('GLPI_ROOT')?GLPI_ROOT:'?')."\n";
echo "DB ok: ".($DB && $DB->connected ? 'YES' : 'NO')."\n";

// Plugin ativo?
try {
    $p = $DB->request(['SELECT'=>['id','directory','name','state','version'], 'FROM'=>'glpi_plugins', 'WHERE'=>['directory'=>'cadastroativos']])->current();
    echo "Plugin cadastroativos: ".json_encode($p, JSON_UNESCAPED_UNICODE)."\n";
} catch (Throwable $e) { echo "Plugin query err: ".$e->getMessage()."\n"; }

// Perfis
try {
    foreach ($DB->request(['SELECT'=>['id','name'], 'FROM'=>'glpi_profiles', 'WHERE'=>['name'=>'PROATI']]) as $prof) {
        $pid = (int)$prof['id'];
        echo "\n--- Perfil PROATI id $pid ---\n";
        $iter = $DB->request(['SELECT'=>['name','rights'], 'FROM'=>'glpi_profilerights', 'WHERE'=>['profiles_id'=>$pid, 'name'=>['LIKE','plugin_cadastroativos_%']]]);
        $found=false;
        foreach ($iter as $r) { echo "  ".$r['name']." = ".(int)$r['rights']."\n"; $found=true; }
        if (!$found) echo "  NENHUMA LINHA plugin_cadastroativos_ !\n";
        // Testa addDefaultProfileInfos
        if (class_exists('PluginCadastroativosProfile')) {
            echo "  PluginCadastroativosProfile existe\n";
        } else {
            echo "  PluginCadastroativosProfile NÃO existe (include falhou)\n";
            // tenta incluir
            $f = GLPI_ROOT.'/plugins/cadastroativos/inc/profile.class.php';
            if (file_exists($f)) { include_once $f; echo "  incluido $f, agora existe? ".(class_exists('PluginCadastroativosProfile')?'YES':'NO')."\n"; }
        }
        // Simula Menu::canView para este pid
        // Monta sessão fake
        $_SESSION['glpiactiveprofile'] = ['id'=>$pid, 'name'=>$prof['name']];
        // limpa
        foreach (['plugin_cadastroativos_use','plugin_cadastroativos_infra','plugin_cadastroativos_av','plugin_cadastroativos_import'] as $k) unset($_SESSION['glpiactiveprofile'][$k]);
        if (class_exists('PluginCadastroativosProfile')) {
            PluginCadastroativosProfile::changeProfile();
            echo "  Após changeProfile sessao: ".json_encode([
                'use'=>$_SESSION['glpiactiveprofile']['plugin_cadastroativos_use']??'NOT SET',
                'infra'=>$_SESSION['glpiactiveprofile']['plugin_cadastroativos_infra']??'NOT SET',
                'av'=>$_SESSION['glpiactiveprofile']['plugin_cadastroativos_av']??'NOT SET',
                'import'=>$_SESSION['glpiactiveprofile']['plugin_cadastroativos_import']??'NOT SET',
            ])." \n";
            echo "  haveRight use=". (Session::haveRight('plugin_cadastroativos_use', READ)?'YES':'NO')." infra=". (Session::haveRight('plugin_cadastroativos_infra', READ)?'YES':'NO')." av=". (Session::haveRight('plugin_cadastroativos_av', READ)?'YES':'NO')." import=". (Session::haveRight('plugin_cadastroativos_import', READ)?'YES':'NO')."\n";
        }
        if (class_exists('GlpiPlugin\\Cadastroativos\\Menu')) {
            $can = GlpiPlugin\Cadastroativos\Menu::canView() ? 'YES' : 'NO';
            echo "  Menu::canView() = $can\n";
        } else { echo "  Menu class não existe\n"; }
    }
} catch (Throwable $e) { echo "Erro PROATI: ".$e->getMessage()."\n".$e->getTraceAsString()."\n"; }

// Lista todos os perfis com rights
echo "\n--- Todos perfis com rights ---\n";
try {
    foreach ($DB->request(['SELECT'=>['id','name'], 'FROM'=>'glpi_profiles', 'ORDER'=>'name']) as $prof) {
        $pid=(int)$prof['id'];
        $iter=$DB->request(['SELECT'=>['name','rights'], 'FROM'=>'glpi_profilerights', 'WHERE'=>['profiles_id'=>$pid, 'name'=>['LIKE','plugin_cadastroativos_%']]]);
        $map=[]; foreach($iter as $r) $map[$r['name']]=(int)$r['rights'];
        if (!empty($map)) {
            echo "  ".$prof['name']." (id $pid): ".json_encode($map)."\n";
        }
    }
} catch (Throwable $e) { echo "Erro lista: ".$e->getMessage()."\n"; }

// Verifica arquivos
echo "\n--- Arquivos ---\n";
$checks = [
    GLPI_ROOT.'/plugins/cadastroativos/front/debug.php',
    GLPI_ROOT.'/plugins/cadastroativos/debug_raw.php',
    GLPI_ROOT.'/plugins/cadastroativos/src/Menu.php',
    GLPI_ROOT.'/plugins/cadastroativos/inc/profile.class.php',
    GLPI_ROOT.'/plugins/cadastroativos/src/Controller/CadastroController.php',
];
foreach ($checks as $f) echo "  ".($f)." => ".(file_exists($f)?'EXISTS':'MISSING')." ".(is_readable($f)?'readable':'!readable')."\n";

// Logs
echo "\n--- Logs tail ---\n";
$logs = [GLPI_ROOT.'/files/_log/cadastroativos_canview.log', GLPI_ROOT.'/files/_log/cadastroativos_403.log', GLPI_ROOT.'/files/_log/cadastroativos_debug.log'];
foreach ($logs as $lf) {
    echo "  $lf: ".(file_exists($lf)?'EXISTS '.filesize($lf).' bytes':'MISSING')."\n";
    if (file_exists($lf)) {
        $lines = @file($lf, FILE_IGNORE_NEW_LINES);
        if ($lines) {
            $tail = array_slice($lines, -5);
            foreach ($tail as $l) echo "    $l\n";
        }
    }
}

echo "\n=== FIM ===\n";
echo "Se PROATI tem 4x1 mas Menu::canView=NO, cole este output no chat.\n";
