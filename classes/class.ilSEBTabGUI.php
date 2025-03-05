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

use kergomard\SEB\Config\Configuration;

use ILIAS\Refinery\Factory as Refinery;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;

abstract class ilSEBTabGUI
{
    protected ilTabsGUI $tabs;
    protected ilLocatorGUI $locator;
    protected ilObjectDefinition $obj_def;

    protected ilGlobalTemplateInterface $tpl;
    protected ilCtrl $ctrl;
    protected UIFactory $ui_factory;
    protected UIRenderer $ui_renderer;
    protected ilObjUser $user;
    protected ilDBInterface $db;
    protected ilRbacSystem $rbac_system;
    protected HTTPServices $http;
    protected Refinery $refinery;
    protected ilLanguage $lang;

    protected int $ref_id;
    protected ilObject $object;
    protected ilSEBPlugin $pl;
    protected Configuration $configuration;

    public function __construct()
    {
        /** @var ILIAS\DI\Container $DIC */
        global $DIC;

        $this->tabs = $DIC['ilTabs'];
        $this->locator = $DIC['ilLocator'];
        $this->obj_def = $DIC['objDefinition'];

        $this->tpl = $DIC['tpl'];
        $this->ctrl = $DIC['ilCtrl'];
        $this->ui_factory = $DIC['ui.factory'];
        $this->ui_renderer = $DIC['ui.renderer'];
        $this->user = $DIC['ilUser'];
        $this->db = $DIC['ilDB'];
        $this->rbac_system = $DIC['rbacsystem'];
        $this->http = $DIC['http'];
        $this->refinery = $DIC['refinery'];
        $this->lang = $DIC['lng'];

        $ref_id = $this->http->wrapper()->query()->retrieve(
            'ref_id',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if ($ref_id === null) {
            return;
        }
        $this->ref_id = $ref_id;

        $this->object = ilObjectFactory::getInstanceByRefId($this->ref_id);

        $this->pl = new ilSEBPlugin(
            $this->db,
            $DIC['component.repository'],
            'seb'
        );

        $this->ctrl->setParameter($this, 'ref_id', $this->ref_id);
    }

    protected function setupUI(): void
    {
        $this->locator->addRepositoryItems($this->ref_id);
        $this->locator->addItem(
            $this->object->getTitle(),
            $this->ctrl->getLinkTargetByClass(
                [
                        'ilRepositoryGUI',
                        'ilObj' . $this->obj_def->getClassName($this->object->getType()) . 'GUI'
                ],
                ''
            )
        );
        $this->tpl->setLocator();
        $this->tpl->setTitle($this->object->getTitle());
        $this->tpl->setDescription($this->object->getDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon($this->object->getId(), 'big', $this->object->getType()));

        $this->tabs->setBackTarget(
            $this->lang->txt('back'),
            (new ilRepositoryExplorer((string) $this->ref_id))
                ->buildLinkTarget($this->ref_id, $this->object->getType())
        );
    }
}
