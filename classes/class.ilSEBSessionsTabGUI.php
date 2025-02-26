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

/**
 * @ilCtrl_isCalledBy ilSEBSessionsTabGUI: ilRouterGUI, ilUIPluginRouterGUI
 */
class ilSEBSessionsTabGUI extends ilSEBTabGUI
{
    public function executeCommand(): void
    {
        switch ($this->ctrl->getCmd()) {
            case 'showSessions':
            case 'applyFilter':
                $this->showSessions('show');
                break;
            case 'resetFilter':
                $this->showSessions('reset');
                break;
            case 'confirmDeleteSessions':
                $this->confirmDeleteSessions();
                break;
            case 'deleteSessions':
                $this->deleteSessions();
                break;
            default:
                $this->ctrl->returnToParent($this);
        }
    }

    /**
     * @param string $mode One of 'show' indicating that the list of sessions
     * should simply be shown applying all filters or 'reset' if the filters
     * need to be reset
     */
    private function showSessions(string $mode): void
    {
        $this->setupUI();

        $users = $this->filterUsers(
            $this->addUsersInfoToSessionsArray(
                $this->getSessionsArrayOfTestParticipants()
            )
        );

        $this->initSessionTable($users, 'confirmDeleteSessions');
    }

    private function confirmDeleteSessions(): void
    {
        $this->setupUI();

        if ($this->retrieveIdsFromPost() === []) {
            $this->tpl->setOnScreenMessage('failure', $this->pl->txt('no_sessions_selected'));
            $this->showSessions('show');
            return;
        }
            $sessions = $this->db->fetchAll(
                $this->db->query(
                    'SELECT session_id, user_id FROM usr_session WHERE '
                    . $this->db->in(
                        'session_id',
                        $ids,
                        false,
                        ilDBConstants::T_INTEGER
                    ) . ' AND expires > ' . time()
                )
            );

            $this->initSessionTable(
                $this->addUsersInfoToSessionsArray($sessions),
                'deleteSessions'
            );
    }

    private function deleteSessions(): void
    {
        foreach ($this->retrieveIdsFromPost() as $session) {
            ilSession::_destroy($session);
        }

        $this->tpl->setOnScreenMessage('success', $this->pl->txt('sessions_deleted'));
        $this->showSessions('show');
    }

    private function initSessionTable(array $users, string $action): void
    {
        $sessions_table = new ilSEBSessionsTableGUI($this, $action, $this->pl, $this->lang);
        $sessions_table->setData($users);
        $sessions_table->setFilter(
            $this->http->wrapper()->post()->retrieve(
                'user',
                $this->refinery->byTrying([
                    $this->refinery->kindlyTo()->string(),
                    $this->refinery->always('')
                ])
            )
        );

        if ($this->http->wrapper()->query()->has('_table_nav')) {
            $ordering = $this->http->wrapper->query()->retrieve(
                '_table_nav',
                $this->refinery->byTrying([
                    $this->refinery->custom()->transformation(
                        fn (string $v): array => explode(':', $v)
                    ),
                    $this->refinery->always([])
                ])
            );

            if (in_array($ordering[0], ['login', 'first_name', 'last_name'])
                && in_array($ordering[1], ['asc', 'desc'])) {
                $sessions_table->setOrderColumn($ordering[0]);
                $sessions_table->setOrderDirection($ordering[1]);
            }
        }

        $this->tpl->setContent($sessions_table->getHTML());
        $this->tpl->printToStdOut();
    }

    private function getSessionsArrayOfTestParticipants(): array
    {
        $pax_data = new ilTestParticipantData($this->db, $this->lang);
        $pax_data->load((new ilObjTest($this->ref_id))->getTestId());

        return $this->db->fetchAll(
            $this->db->query(
                'SELECT session_id, user_id FROM usr_session WHERE '
                . $this->db->in(
                    'user_id',
                    $pax_data->getUserIds(),
                    false,
                    ilDBConstants::T_INTEGER
                ) . 'AND expires > ' . time()
            )
        );
    }

    private function filterUsers(array $users): array
    {
        if ($users === []) {
            return [];
        }
        $user = $this->http->wrapper()->post()->retrieve(
            'user',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always(null)
            ])
        );
        if ($user === null) {
            return $users;
        }
        return array_filter(
            $users,
            fn (array $v): bool => mb_stristr($v['login'], $user)
                || mb_stristr($v['first_name'], $user)
                || mb_stristr($v['last_name'], $user)
        );
    }

    private function addUsersInfoToSessionsArray(array $sessions): array
    {
        return array_reduce(
            $sessions,
            function (array $c, array $v): array {
                $user_obj = new ilObjUser(
                    $this->refinery->kindlyTo()->int()->transform($v['user_id'])
                );

                if ($user_obj->getId() === $this->user->getId()) {
                    return $c;
                }
                $c[$v['session_id']]['session_id'] = $v['session_id'];
                $c[$v['session_id']]['login'] = $user_obj->getLogin();
                $c[$v['session_id']]['first_name'] = $user_obj->getFirstname();
                return $c;
            },
            []
        );
    }

    private function retrieveIdsFromPost(): array
    {
        return $this->http->wrapper()->post()->retrieve(
            'id',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->listOf(
                    $this->refinery->int()
                ),
                $this->refinery->always([])
            ])
        );
    }
}
