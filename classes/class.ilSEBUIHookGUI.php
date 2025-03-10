<?php

/**
 * This file is part of the SEB-Plugin for ILIAS.
 *
 * SEB-Plugin for ILIAS is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * SEB-Plugin for ILIAS is distributed in the hope that
 * it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SEB-Plugin for ILIAS.  If not,
 * see <http://www.gnu.org/licenses/>.
 *
 * The SEB-Plugin for ILIAS is a refactoring of a previous Plugin by Stefan
 * Schneider that can be found on Github
 * <https://github.com/hrz-unimr/Ilias.SEBPlugin>
 */

declare(strict_types=1);

class ilSEBUIHookGUI extends ilUIHookPluginGUI
{
    private ilSecuritySettings $security;
    private ilRbacSystem $rbac_system;
    private ilCtrl $ctrl;
    private ?int $current_user_id = null;
    private ?int $ref_id = null;
    private ?string $obj_type = null;
    private bool $has_write_access = false;
    private string $cmd_class = '';
    private string $cmd = '';

    public function __construct()
    {
        /** @var ILIAS\DI\Container $DIC */
        global $DIC;
        $this->ctrl = $DIC['ilCtrl'];
        $this->ref_id = $DIC['http']->wrapper()->query()->retrieve(
            'ref_id',
            $DIC['refinery']->byTrying([
                $DIC['refinery']->kindlyTo()->int(),
                $DIC['refinery']->always(null)
            ])
        );
        $this->rbac_system = $DIC['rbacsystem'];
        $this->current_user_id = $DIC['ilUser']->getId();
    }

    public function modifyGUI(
        string $a_comp,
        string $a_part,
        array $a_par = []
    ): void {
        if ($a_part !== 'tabs') {
            return;
        }

        if (!\ilSession::has('checked')) {
            \ilSession::set('checked', '0');
        }

        if ($this->ref_id === null) {
            return;
        }

        $this->init();

        if ($this->obj_type === 'tst'
            && $this->has_write_access
            && !in_array(
                $this->cmd,
                $this->plugin_object->getConfiguration()->getCmdsWithoutSebKeyTab()
            ) && !in_array(
                $this->cmd_class,
                $this->plugin_object->getConfiguration()->getCmdClassesWithoutSebKeyTab()
            )) {

            /*
             * Add Sessioncontrol Tab for SEB
             **/
            if ($this->plugin_object->getConfiguration()->getSessionControlEnabled()
                && $this->security->isPreventionOfSimultaneousLoginsEnabled()) {
                $this->ctrl->setParameterByClass(ilSEBSessionsTabGUI::class, 'ref_id', $this->ref_id);
                $link = $this->ctrl->getLinkTargetByClass([
                        ilSEBPlugin::STANDARD_BASE_CLASS,
                        ilSEBSessionsTabGUI::class
                ], 'showSessions');
                $a_par['tabs']->addTab(
                    'sessions',
                    $this->getPluginObject()->txt('sessions_tab_title'),
                    $link
                );
            }

            /*
             * Add Settings Tab for SEB
             **/
            if ($this->plugin_object->getConfiguration()->getObjectKeysEnabled()) {
                $this->ctrl->setParameterByClass(ilSEBSettingsTabGUI::class, 'ref_id', $this->ref_id);
                $link = $this->ctrl->getLinkTargetByClass([
                    ilSEBPlugin::STANDARD_BASE_CLASS,
                    ilSEBSettingsTabGUI::class
                ], 'seb_settings');
                $a_par['tabs']->addTab(
                    'seb_settings',
                    $this->getPluginObject()->txt('settings_tab_title'),
                    $link
                );
            }
        }
    }

    private function init(): void
    {
        if ($this->plugin_object->getConfiguration()->getSessionControlEnabled()) {
            $this->security = ilSecuritySettings::_getInstance();
        }

        $this->obj_type = ilObject::_lookupType(
            ilObject::_lookupObjectId($this->ref_id)
        );
        $this->has_write_access = $this->rbac_system->checkAccessOfUser(
            $this->current_user_id,
            'write',
            $this->ref_id
        );
        $this->cmd_class = strtolower($this->ctrl->getCmdClass());
        $this->cmd = strtolower($this->ctrl->getCmd(''));
    }
}
