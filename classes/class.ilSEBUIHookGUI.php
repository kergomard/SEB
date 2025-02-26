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

use kergomard\SEB\Config\Config;

class ilSEBUIHookGUI extends ilUIHookPluginGUI
{
    private Config $config;
    private ilSecuritySettings $security;
    private ilCtrl $ctrl;
    private ?int $ref_id = null;
    private ?string $obj_type = null;
    private bool $has_write_access = false;
    private string $cmd_class = '';
    private string $cmd = '';

    public function __construct()
    {
        /** @var ILIAS\DI\Container $DIC */
        global $DIC;
        $this->config = new Config($DIC['ilDB']);
        $this->ctrl = $DIC['ilCtrl'];
        $this->ref_id = $DIC['http']->wrapper()->query()->retrieve(
            'ref_id',
            $DIC['refinery']->byTrying([
                $DIC['refinery']->kindlyTo()->int(),
                $DIC['refinery']->always(null)
            ])
        );
        if ($this->ref_id !== null) {
            $this->init(
                $DIC['rbacsystem'],
                $DIC['ilUser']->getId()
            );
        }
    }

    public function modifyGUI(
        string $a_comp,
        string $a_part,
        array $a_par = []
    ): void {
        if ($a_part !== 'tabs' || $this->ref_id === null) {
            return;
        }

        if ($this->obj_type === 'tst'
            && $this->has_write_access
            && $this->cmd !== 'showquestion'
            && $this->cmd !== 'outuserresultsoverview') {
            if (in_array($this->cmd_class, $this->config->getCmdClassesWithoutSebKeyTab())
                || $this->cmd_class === 'iltestcorrectionsgui' && $this->cmd !== 'showquestionlist'
                || $this->cmd_class === 'ilparticipantstestresultsgui' && $this->cmd !== 'showparticipants'
                || $this->cmd === 'editquestion'
                || $this->cmd_class === 'ilassquestionrelatednavigationbargui') {
                return;
            }
            /*
             * Add Sessioncontrol Tab for SEB
             **/
            if ($this->config->getActivateSessionControl()
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
            if ($this->config->getAllowObjectKeys()) {
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

    private function init(
        ilRbacSystem $rbac_system,
        int $current_user_id
    ): void
    {
        if ($this->config->getActivateSessionControl()) {
            $this->security = ilSecuritySettings::_getInstance();
        }

        $this->obj_type = ilObject::_lookupType(
            ilObject::_lookupObjectId($this->ref_id)
        );
        $this->has_write_access = $rbac_system->checkAccessOfUser(
            $current_user_id,
            'write',
            $this->ref_id
        );
        $this->cmd_class = strtolower($this->ctrl->getCmdClass());
        $this->cmd = strtolower($this->ctrl->getCmd(''));
    }
}
