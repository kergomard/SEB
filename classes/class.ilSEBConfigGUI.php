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

use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\UI\Component\Input\Container\Form\Form;

/**
 * @ilCtrl_isCalledBy ilSEBConfigGUI: ilObjComponentSettingsGUI
 */
class ilSEBConfigGUI extends ilPluginConfigGUI
{
    private const CMD_CONFIGURE = 'configure';
    private const CMD_SAVE = 'save';

    private ilSEBPlugin $pl;
    private Config $config;
    private ilGlobalTemplateInterface $tpl;
    private UIFactory $ui_factory;
    private UIRenderer $ui_renderer;
    private ilLanguage $lang;
    private ilCtrl $ctrl;
    private ilRbacReview $rbac_review;
    private HTTPServices $http;

    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case self::CMD_CONFIGURE:
            case self::CMD_SAVE:
                /** @var ILIAS\DI\Container $DIC */
                global $DIC;
                $this->pl = $this->getPluginObject();
                $this->config = new Config($DIC->database());
                $this->tpl = $DIC['tpl'];
                $this->ui_factory = $DIC['ui.factory'];
                $this->ui_renderer = $DIC['ui.renderer'];
                $this->lang = $DIC['lng'];
                $this->ctrl = $DIC['ilCtrl'];
                $this->rbac_review = $DIC['rbacreview'];
                $this->http = $DIC['http'];
                $this->$cmd();
                break;

        }
    }

    public function configure(): void
    {
        $this->tpl->setContent(
            $this->ui_renderer->render(
                $this->initConfigurationForm()
            )
        );
    }

    public function save(): void
    {
        $form = $this->initConfigurationForm()
            ->withRequest($this->http->request());
        $data = $form->getData();
        if ($data === null) {
            $this->tpl->setContent($this->ui_renderer->render($form));
            return;
        }

        $this->config->setOnScreenMessage(
            $this->tpl,
            $this->pl,
            $this->config->saveSEBConf($data['configuration'])
        );

        $this->configure();
    }

    private function initConfigurationForm(): Form
    {
        $ff = $this->ui_factory->input()->field();

        $global_roles_options = $this->buildGlobalRolesSelectionArray();
        $root_uri = $this->config->getIliasRootUri();
        if ($root_uri === '') {
            $uri = $this->http->request()->getUri();
            $root_uri = $uri->getScheme() . '://' . $uri->getHost() . $uri->getPort();
        }

        $session_control_enabled = ilSecuritySettings::_getInstance()
            ->isPreventionOfSimultaneousLoginsEnabled();

        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormActionByClass(
                [
                    ilAdministrationGUI::class,
                    ilObjComponentSettingsGUI::class,
                    self::class
                ],
                self::CMD_SAVE
            ),
            ['configuration' => $ff->section(
                [
                    'allow_object_keys' => $ff->checkbox(
                        $this->pl->txt('allow_object_keys'),
                        $this->pl->txt('allow_object_keys_info')
                    )->withValue($this->config->getAllowObjectKeys()),
                    'seb_keys' => $ff->text(
                        $this->pl->txt('seb_keys'),
                        $this->pl->txt('seb_keys_info')
                    )->withMaxLength(Config::MAX_KEYS_LENGTH)
                    ->withValue($this->config->getSEBKeysString()),
                    'role_deny' => $ff->select(
                        $this->pl->txt('role_deny'),
                        $global_roles_options
                    )->withRequired(true)
                    ->withByline($this->pl->txt('role_deny_info'))
                    ->withValue($this->config->getRoleDeny()),
                    'role_kiosk' => $ff->select(
                        $this->pl->txt('role_kiosk'),
                        $global_roles_options
                    )->withRequired(true)
                    ->withByline($this->pl->txt('role_kiosk_info'))
                    ->withValue($this->config->getRoleKiosk()),
                    'activate_session_control' => $ff->checkbox(
                        $this->pl->txt('activate_session_control'),
                        $session_control_enabled
                            ? $this->pl->txt('activate_session_control_info')
                            : $this->pl->txt('activate_session_control_info_disabled')
                    )->withValue($this->config->getActivateSessionControl())
                    ->withDisabled(!$session_control_enabled),
                    'show_pax_pic' => $ff->checkbox(
                        $this->pl->txt('show_pax_pic'),
                        $this->pl->txt('show_pax_pic_info')
                    )->withValue($this->config->getShowPaxPic()),
                    'show_pax_matriculation' => $ff->checkbox(
                        $this->pl->txt('show_pax_matriculation'),
                        $this->pl->txt('show_pax_matriculation_info')
                    )->withValue($this->config->getShowPaxMatriculation()),
                    'show_pax_username' => $ff->checkbox(
                        $this->pl->txt('show_pax_username'),
                        $this->pl->txt('show_pax_username_info')
                    )->withValue($this->config->getShowPaxUsername()),
                    'ilias_root_uri' => $ff->text(
                        $this->pl->txt('ilias_root_uri'),
                        $this->pl->txt('ilias_root_uri_info')
                    )->withMaxLength(Config::MAX_URI_LENGTH)
                    ->withValue($root_uri)
                ],
                $this->pl->txt('config')
            )]
        );
    }

    private function buildGlobalRolesSelectionArray(): array
    {
        $roles = [
            0 => $this->pl->txt('role_none'),
            1 => $this->pl->txt('role_all_except_admin')
        ];
        return array_reduce(
            $this->rbac_review->getGlobalRoles(),
            static function (array $c, int $v): array {
                $c[$v] = ilObject::_lookupTitle($v);
                return $c;
            },
            $roles
        );
    }
}
