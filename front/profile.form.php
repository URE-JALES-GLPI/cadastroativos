<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('profile', UPDATE);

$profilesId = (int) ($_POST['profiles_id'] ?? 0);

if ($profilesId > 0 && isset($_POST['update'])) {
    $pr = new ProfileRight();

    $rights = [
        PluginCadastroativosProfile::RIGHT_USE   => (int) ($_POST['rights_use']   ?? 0),
        PluginCadastroativosProfile::RIGHT_INFRA => (int) ($_POST['rights_infra'] ?? 0),
        PluginCadastroativosProfile::RIGHT_AV    => (int) ($_POST['rights_av']    ?? 0),
    ];

    foreach ($rights as $rightName => $value) {
        $rows = $pr->find(['profiles_id' => $profilesId, 'name' => $rightName]);
        if (count($rows) > 0) {
            $existing = array_values($rows)[0];
            $pr->update(['id' => (int) $existing['id'], 'rights' => $value]);
        } else {
            $pr->add(['profiles_id' => $profilesId, 'name' => $rightName, 'rights' => $value]);
        }
    }

    PluginCadastroativosProfile::changeProfile();
    Session::addMessageAfterRedirect('Permissoes salvas com sucesso!', true, INFO);
}

global $CFG_GLPI;

Html::redirect(
    $CFG_GLPI['root_doc']
    . '/front/profile.form.php?id=' . $profilesId
    . '&forcetab=PluginCadastroativosProfile$1'
);
