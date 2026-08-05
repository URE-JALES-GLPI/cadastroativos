<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Gerencia as permissoes do plugin Cadastro de Ativos na tela de Perfis.
 * Arquivo em inc/ (nao escaneado pelo Symfony DI) para evitar conflito
 * com o autoload dos Controllers em src/Controller/.
 *
 * Segue o padrao do plugin assetmgrstatus que funciona no mesmo ambiente.
 */
class PluginCadastroativosProfile extends CommonDBTM
{
    public static $rightname = 'profile';

    public const RIGHT_USE = 'plugin_cadastroativos_use';

    // ------------------------------------------------------------------
    // Direitos registrados pelo plugin
    // ------------------------------------------------------------------

    public static function getAllRights(): array
    {
        return [
            ['field' => self::RIGHT_USE, 'default' => 0],
        ];
    }

    // ------------------------------------------------------------------
    // Instalacao / Desinstalacao
    // ------------------------------------------------------------------

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
            if (!countElementsInTable(
                ProfileRight::getTable(),
                ['profiles_id' => $profiles_id, 'name' => $right['field']]
            )) {
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
        if ($pid <= 0) {
            return;
        }

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

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

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

    // ------------------------------------------------------------------
    // Aba na tela Administracao > Perfis
    // ------------------------------------------------------------------

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            return "<span class='d-inline-flex align-items-center gap-1'>"
                . "<i class='ti ti-clipboard-list'></i>"
                . "<span>Cadastro de Ativos</span>"
                . "</span>";
        }
        return '';
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        global $CFG_GLPI;

        if (!($item instanceof Profile)) {
            return false;
        }
        if (!$item->canView()) {
            return false;
        }

        $pid = (int) $item->getID();
        self::addDefaultProfileInfos($pid);

        $rUse    = self::getRightValue($pid, self::RIGHT_USE);
        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);

        echo "<form name='cadastroativos_profile_form' method='post'"
            . " action='" . $CFG_GLPI['root_doc'] . "/plugins/cadastroativos/front/profile.form.php'>";
        echo "<div class='spaced'><table class='tab_cadre_fixehov'>";
        echo "<tr class='headerRow'><th colspan='2'>Permissoes — Cadastro de Ativos</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td width='65%'>";
        echo "<strong>Acesso ao Cadastro de Ativos</strong><br>";
        echo "<small style='color:#6b7280;'>Permite acessar o menu Ferramentas &gt; Cadastro de Ativos "
            . "e registrar novos ativos (Celular, Notebook, Tablet, Desktop)</small>";
        echo "</td><td>";

        if ($canedit) {
            Dropdown::showFromArray(
                'rights_use',
                [
                    0    => '— Sem acesso —',
                    READ => 'Permitir acesso',
                ],
                ['value' => ($rUse & READ) ? READ : 0]
            );
        } else {
            echo ($rUse & READ) ? 'Permitido' : 'Sem acesso';
        }

        echo "</td></tr>";

        if ($canedit) {
            echo "<tr class='tab_bg_2'><td colspan='2' class='center' style='padding:12px;'>";
            echo Html::hidden('profiles_id', ['value' => $pid]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<button type='submit' name='update' value='1' class='btn btn-primary'>"
                . "<i class='ti ti-device-floppy'></i> Salvar Permissoes"
                . "</button>";
            echo "</td></tr>";
        }

        echo "</table></div>";
        Html::closeForm();

        return true;
    }
}
