<?php
// Acesso ultra-simples: /glpi/plugins/cadastroativos/debug2.php
// Não usa Html::header, só login
include('../../inc/includes.php');
Session::checkLoginUser();
header('Content-Type: text/html; charset=utf-8');
echo "<h1>debug2 ok - ".htmlspecialchars($_SESSION['glpiactiveprofile']['name'] ?? '?')." pid=". (int)($_SESSION['glpiactiveprofile']['id'] ?? 0) ."</h1>";
echo "<a href='front/debug.php'>ir para front/debug.php</a> | <a href='front/debug.php?json=1'>json</a>";
