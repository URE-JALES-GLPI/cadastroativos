<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCadastroativosProfile extends CommonDBTM
{
    public static $rightname = 'profile';

    public const RIGHT_USE    = 'plugin_cadastroativos_use';
    public const RIGHT_INFRA  = 'plugin_cadastroativos_infra';
    public const RIGHT_AV     = 'plugin_cadastroativos_av';
    public const RIGHT_IMPORT = 'plugin_cadastroativos_import';

    public static function getAllRights(): array
    {
        return [
            ['field' => self::RIGHT_USE,    'default' => 0],
            ['field' => self::RIGHT_INFRA,  'default' => 0],
            ['field' => self::RIGHT_AV,     'default' => 0],
            ['field' => self::RIGHT_IMPORT, 'default' => 0],
        ];
    }

    public static function install(): bool
    {
        global $DB;
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']) as $profile) {
            self::addDefaultProfileInfos((int) $profile['id']);
        }
        self::changeProfile();
        return true;
    }

    public static function uninstall(): bool
    {
        $pr = new ProfileRight();
        foreach (self::getAllRights() as $right) {
            $pr->deleteByCriteria(['name' => $right['field']]);
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
        }
        return true;
    }

    public static function addDefaultProfileInfos(int $profiles_id): void
    {
        $pr = new ProfileRight();
        foreach (self::getAllRights() as $right) {
            if (!countElementsInTable(ProfileRight::getTable(), [
                'profiles_id' => $profiles_id,
                'name'        => $right['field'],
            ])) {
                $pr->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $right['field'],
                    'rights'      => $right['default'],
                ]);
            }
        }
    }

    public static function changeProfile(): void
    {
        global $DB;
        $pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($pid <= 0) return;
        foreach (self::getAllRights() as $r) {
            unset($_SESSION['glpiactiveprofile'][$r['field']]);
        }
        $iter = $DB->request([
            'SELECT' => ['name', 'rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => [
                'profiles_id' => $pid,
                'name'        => array_column(self::getAllRights(), 'field'),
            ],
        ]);
        foreach ($iter as $row) {
            $_SESSION['glpiactiveprofile'][$row['name']] = (int) $row['rights'];
        }
    }

    private static function getRightValue(int $profiles_id, string $field): int
    {
        global $DB;
        $row = $DB->request([
            'SELECT' => ['rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => $field],
        ])->current();
        return (is_array($row) && isset($row['rights'])) ? (int) $row['rights'] : 0;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            return "<span class='d-inline-flex align-items-center gap-1'>"
                . "<i class='ti ti-clipboard-list'></i><span>Cadastro de Ativos</span></span>";
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $CFG_GLPI;
        if (!($item instanceof Profile) || !$item->canView()) return false;

        $pid     = (int) $item->getID();
        self::addDefaultProfileInfos($pid);

        $rUse   = self::getRightValue($pid, self::RIGHT_USE);
        $rInfra = self::getRightValue($pid, self::RIGHT_INFRA);
        $rAV    = self::getRightValue($pid, self::RIGHT_AV);
        $rImport = self::getRightValue($pid, self::RIGHT_IMPORT);
        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);

        echo "<form name='cadastroativos_profile_form' method='post'"
            . " action='" . $CFG_GLPI['root_doc'] . "/plugins/cadastroativos/front/profile.form.php'>";
        echo "<div class='spaced'><table class='tab_cadre_fixehov'>";
        echo "<tr class='headerRow'><th colspan='2'>Permissoes — Cadastro de Ativos</th></tr>";

        // Linha 1: Acesso basico (Celular, Notebook, Tablet, Desktop)
        echo "<tr class='tab_bg_1'><td width='65%'>"
            . "<strong>Ativos Basicos</strong><br>"
            . "<small style='color:#6b7280;'>Celular, Notebook, Tablet, Desktop</small>"
            . "</td><td>";
        if ($canedit) {
            Dropdown::showFromArray('rights_use', [0 => '— Sem acesso —', READ => 'Permitir acesso'], ['value' => ($rUse & READ) ? READ : 0]);
        } else {
            echo ($rUse & READ) ? 'Permitido' : 'Sem acesso';
        }
        echo "</td></tr>";

        // Linha 2: Infraestrutura (Switch, Firewall, Rack de Rede)
        echo "<tr class='tab_bg_1'><td>"
            . "<strong>Infraestrutura</strong><br>"
            . "<small style='color:#6b7280;'>Switch, Firewall, Rack de Rede</small>"
            . "</td><td>";
        if ($canedit) {
            Dropdown::showFromArray('rights_infra', [0 => '— Sem acesso —', READ => 'Permitir acesso'], ['value' => ($rInfra & READ) ? READ : 0]);
        } else {
            echo ($rInfra & READ) ? 'Permitido' : 'Sem acesso';
        }
        echo "</td></tr>";

        // Linha 3: AV (Televisão, Plataforma de Recarga)
        echo "<tr class='tab_bg_1'><td>"
            . "<strong>AV / Recarga</strong><br>"
            . "<small style='color:#6b7280;'>Televisao, Plataforma de Recarga</small>"
            . "</td><td>";
        if ($canedit) {
            Dropdown::showFromArray('rights_av', [0 => '— Sem acesso —', READ => 'Permitir acesso'], ['value' => ($rAV & READ) ? READ : 0]);
        } else {
            echo ($rAV & READ) ? 'Permitido' : 'Sem acesso';
        }
        echo "</td></tr>";

        // Linha 4: Importacao em massa (XLSX)
        echo "<tr class='tab_bg_1'><td>"
            . "<strong>Importacao em massa (XLSX)</strong><br>"
            . "<small style='color:#6b7280;'>Baixar modelo e cadastrar varios ativos via planilha</small>"
            . "</td><td>";
        if ($canedit) {
            Dropdown::showFromArray('rights_import', [0 => '— Sem acesso —', READ => 'Permitir acesso'], ['value' => ($rImport & READ) ? READ : 0]);
        } else {
            echo ($rImport & READ) ? 'Permitido' : 'Sem acesso';
        }
        echo "</td></tr>";

        if ($canedit) {
            echo "<tr class='tab_bg_2'><td colspan='2' class='center' style='padding:12px;'>";
            echo Html::hidden('profiles_id', ['value' => $pid]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<button type='submit' name='update' value='1' class='btn btn-primary'>"
                . "<i class='ti ti-device-floppy'></i> Salvar Permissoes</button>";
            echo "</td></tr>";
        }

        echo "</table></div>";
        Html::closeForm();
        return true;
    }
}
