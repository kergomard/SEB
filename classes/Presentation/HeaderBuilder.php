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

namespace kergomard\SEB\Presentation;

class HeaderBuilder
{
    private \ilSEBPlugin $plugin;
    private \ilObjUser $user;
    private ?\ilObject $object = null;
    private string $short_inst_name;

    public function __construct(
        \ilSEBPlugin $plugin,
        \ilObjUser $user,
        string $short_inst_name
    ) {
        $this->plugin = $plugin;
        $this->user = $user;
        $this->short_inst_name = $short_inst_name;
    }

    public function withObject(\ilObject $object): self
    {
        $clone = clone $this;
        $clone->object = $object;
        return $clone;
    }

    public function getParsedTitleString(): string
    {
        $template = new \ilTemplate('tpl.seb_kiosk_head.html', true, true, $this->plugin->getDirectory());

        $template->setVariable('HEADER_BG_COLOR', $this->plugin->getHeaderBackgroundColor());
        $template->setVariable('HEADER_COLOR', $this->plugin->getHeaderColor());
        if ($this->user->getId() > 0) {
            $template->setVariable('PARTICIPANT_NAME', $this->user->getFullname());

            $matriculation = null;
            if ($this->plugin->isShowParticipantMatriculation() && $this->user->getMatriculation() !== '') {
                $matriculation = $this->user->getMatriculation();
            }

            $username = null;
            if ($this->plugin->isShowParticipantUsername()) {
                $username = $this->user->getLogin();
            }

            $additional_info = '';
            if (!is_null($username) && !is_null($matriculation)) {
                $additional_info = "({$username}, {$matriculation})";
            } elseif (!is_null($username) || !is_null($matriculation)) {
                $additional_info = '(' . ($username ?? $matriculation) . ')';
            }
            $template->setVariable('ADDITIONAL_INFO', $additional_info);
        }

        if (is_null($this->object)) {
            return $template->get();
        }

        $template->setVariable(
            'TITLE',
            $this->object->getType() === 'tst'
            ? $this->object->getTitle()
            : $this->short_inst_name
        );

        if ($this->object->getType() === 'tst'
            && $this->object->isShowExamIdInTestPassEnabled()) {
            $testSession = new \ilTestSession();
            $testSession->loadTestSession(
                $this->object->getTestId(),
                $this->user->getId()
            );
            $exam_id = \ilObjTest::buildExamId(
                $testSession->getActiveId(),
                $testSession->getPass(),
                $this->plugin->getCurrentRefId()
            );
            $template->setVariable('EXAM_ID', "({$this->plugin->txt('exam_id')}: {$exam_id})");
        }

        return $template->get();
    }
}
