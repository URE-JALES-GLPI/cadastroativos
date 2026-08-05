<?php

/**
 * Processa o POST do formulario de permissoes do plugin Cadastro de Ativos.
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('profile', UPDATE);

$profilesId = (int) ($_POST['profiles_id'] ?? 0);

if ($profilesId > 0 && isset($_POST['update'])) {

    $rightsUse = (int) ($_POST['rights_use'] ?? 0);

    $pr  = new ProfileRight();
    $row = $pr->find([
        'profiles_id' => $profilesId,
        'name'        => PluginCadastroativosProfile::RIGHT_USE,
    ]);

    if (count($row) > 0) {
        $existing = array_values($row)[0];
        $pr->update(['id' => (int) $existing['id'], 'rights' => $rightsUse]);
    } else {
        $pr->add([
            'profiles_id' => $profilesId,
            'name'        => PluginCadastroativosProfile::RIGHT_USE,
            'rights'      => $rightsUse,
        ]);
    }

    PluginCadastroativosProfile::changeProfile();

    Session::addMessageAfterRedirect('Permissoes salvas com sucesso!', true, INFO);
}

Html::redirect(
    $CFG_GLPI['root_doc']
    . '/front/profile.form.php?id=' . $profilesId
    . '&forcetab=PluginCadastroativosProfile$1'
);
