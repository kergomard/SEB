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

use kergomard\SEB\Config\Repository;

use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;

/**
 * @ilCtrl_isCalledBy ilSEBConfigGUI: ilObjComponentSettingsGUI
 */
class ilSEBConfigGUI extends ilPluginConfigGUI
{
    private const CMD_CONFIGURE = 'configure';
    private const CMD_SAVE = 'save';

    private ilSEBPlugin $pl;
    private ilGlobalTemplateInterface $tpl;
    private UIFactory $ui_factory;
    private UIRenderer $ui_renderer;
    private Refinery $refinery;
    private ilLanguage $lng;
    private ilCtrl $ctrl;
    private ilRbacReview $rbac_review;
    private HTTPServices $http;

    public function performCommand(string $cmd): void
    {
        if ($cmd === self::CMD_CONFIGURE
            || $cmd === self::CMD_SAVE) {
            /** @var ILIAS\DI\Container $DIC */
            global $DIC;
            $this->pl = $this->getPluginObject();
            $this->tpl = $DIC['tpl'];
            $this->ui_factory = $DIC['ui.factory'];
            $this->ui_renderer = $DIC['ui.renderer'];
            $this->refinery = $DIC['refinery'];
            $this->lng = $DIC['lng'];
            $this->ctrl = $DIC['ilCtrl'];
            $this->rbac_review = $DIC['rbacreview'];
            $this->http = $DIC['http'];
            $this->$cmd();
        }
    }

    public function configure(): void
    {
        $this->tpl->setContent(
            $this->ui_renderer->render(
                $this->buildConfigurationForm()
            )
        );
    }

    public function save(): void
    {
        $form = $this->buildConfigurationForm()->withRequest($this->http->request());
        $data = $form->getData();
        if ($data === null) {
            $this->tpl->setContent($this->ui_renderer->render($form));
            return;
        }

        $this->setOnScreenMessage(
            $this->pl->getConfigurationRepository()->saveGlobalConfiguration(
                $data['configuration']
            )
        );
        $this->pl->reloadConfiguration();
        $this->configure();
    }

    private function buildConfigurationForm(): StandardForm
    {
        return $this->ui_factory->input()->container()->form()->standard(
                $this->ctrl->getFormActionByClass(
                [
                    ilAdministrationGUI::class,
                    ilObjComponentSettingsGUI::class,
                    self::class
                ],
                self::CMD_SAVE
            ),
            $this->pl->getConfiguration()->toForm(
                $this->ui_factory,
                $this->refinery,
                $this->rbac_review->getGlobalRoles(),
                $this->pl
            )
        );
    }

    public function setOnScreenMessage(int $lines_changed): void
    {
        if ($lines_changed < 0) {
            $this->tpl->setOnScreenMessage('failure', $this->pl->txt('save_failure'));
            return;
        }
        if ($lines_changed === 0) {
            $this->tpl->setOnScreenMessage('failure', $this->pl->txt('nothing_changed'));
            return;
        }
        $this->tpl->setOnScreenMessage('success', $this->pl->txt('save_success'));
    }
}
