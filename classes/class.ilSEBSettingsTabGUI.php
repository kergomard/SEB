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

use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;

/**
 * @ilCtrl_isCalledBy ilSEBSettingsTabGUI: ilRouterGUI, ilUIPluginRouterGUI
 */
class ilSEBSettingsTabGUI extends ilSEBTabGUI
{
    public function executeCommand(): void
    {
        if (!$this->rbac_system->checkAccess('write', $this->ref_id)) {
            $this->ctrl->returnToParent($this);
        }

        switch ($this->ctrl->getCmd()) {
            case 'seb_settings':
                $this->showSettings();
                break;
            case 'save':
                $this->save();
                break;
            default:
                $this->ctrl->returnToParent($this);
        }
    }

    private function showSettings(): void
    {
        $this->setupUI();

        $this->tpl->setContent(
            $this->ui_renderer->render(
                $this->buildObjectKeysForm()
            )
        );
        $this->tpl->printToStdout();
    }

    private function save(): void
    {
        $form = $this->buildObjectKeysForm()
            ->withRequest($this->http->request());

        $data = $form->getData();
        if ($data === null) {
            $this->tpl->setContent($this->ui_renderer->render($form));
            $this->tpl->printToStdOut();
        }

        $this->pl->getConfigurationRepository()->saveObjectKeys(
            $data['container']
        );
        $this->tpl->setOnScreenMessage('success', $this->pl->txt('save_success'));
        $this->showSettings();
    }

    private function buildObjectKeysForm(): StandardForm
    {
        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormActionByClass(self::class, 'save'),
            $this->pl->getConfigurationRepository()
                ->getObjectSpecificKeysFor($this->ref_id)
                ->toForm($this->ui_factory, $this->refinery, $this->pl)
        );
    }
}
