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

use ILIAS\UI\Component\Input\Container\Form\Form;

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
                $this->initConfigurationForm()
            )
        );
        $this->tpl->printToStdout();
    }

    private function save(): void
    {
        $form = $this->initConfigurationForm()
            ->withRequest($this->http->request());

        $data = $form->getData();
        if ($data === null) {
            $this->tpl->setContent($this->ui_renderer->render($form));
            $this->tpl->printToStdOut();
        }

        $this->config->setOnScreenMessage(
            $this->tpl,
            $this->pl,
            $this->config->saveObjectKeys(
                $this->ref_id,
                $data['container']['seb_key_win'],
                $data['container']['seb_key_macos']
            )
        );
        $this->showSettings();
    }

    private function initConfigurationForm(): Form
    {
        $ff = $this->ui_factory->input()->field();
        $keys = $this->config->getObjectKeys($this->ref_id);
        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormActionByClass('ilSEBSettingsTabGUI', 'save'),
            [
                'container' => $ff->section(
                    [
                        'seb_key_win' => $ff->text(
                            $this->pl->txt('key_windows'),
                            $this->pl->txt('key_windows_info')
                        )->withMaxLength(Config::MAX_KEYS_LENGTH)
                        ->withValue($keys['seb_key_win']),
                        'seb_key_macos' => $ff->text(
                            $this->pl->txt('key_macos'),
                            $this->pl->txt('key_macos_info')
                        )->withMaxLength(Config::MAX_KEYS_LENGTH)
                        ->withValue($keys['seb_key_macos']),
                    ],
                    $this->pl->txt('title_settings_form'),
                    $this->pl->txt('description_settings_form')
                )
            ]
        );
    }
}
