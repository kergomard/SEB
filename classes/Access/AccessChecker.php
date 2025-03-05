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

namespace kergomard\SEB\Access;

use kergomard\SEB\Config\Configuration;

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as Refinery;

class AccessChecker
{
    private const CMDS_WITHOUT_URI_UPDATE = [
        'autosave',
        'checkKey',
        'getOSDNotifications'
    ];
    private KeysChecker $keys_checker;
    private \ilCtrl $ctrl;
    private \ilObjUser $user;
    private \ilAuthSession $auth;
    private \ilRbacReview $rbacreview;
    private Refinery $refinery;
    private HTTPServices $http;
    private Configuration $config;
    private ?Data $data = null;
    private ?int $ref_id = null;

    private bool $current_user_allowed = true;
    private bool $switch_to_seb_skin_needed = false;
    private bool $key_check_possible_or_unavoidable = false;

    public function isCurrentUserAllowed(): bool
    {
        return $this->current_user_allowed;
    }

    public function isSwitchToSebSkinNeeded(): bool
    {
        return $this->switch_to_seb_skin_needed;
    }

    public function __construct(
        ?int $ref_id,
        KeysChecker $keys_checker,
        \ilCtrl $ctrl,
        \ilObjUser $user,
        \ilAuthSession $auth,
        \ilRbacReview $rbacreview,
        Refinery $refinery,
        HTTPServices $http,
        Configuration $conf
    ) {
        $this->keys_checker = $keys_checker;
        $this->ctrl = $ctrl;
        $this->user = $user;
        $this->auth = $auth;
        $this->rbacreview = $rbacreview;
        $this->refinery = $refinery;
        $this->http = $http;
        $this->config = $conf;

        if (!$this->user->getId()
            || $this->user->getId() === ANONYMOUS_USER_ID
            || $this->rbacreview->isAssigned($this->user->getId(), SYSTEM_ROLE_ID)) {
            return;
        }

        $cookie_key = $this->http->wrapper()->cookie()->retrieve(
            'examKey',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always('')
            ])
        );

        $this->data = $this->retrieveSEBData(
            $this->sebKeyInHeaderPostCookieOrNone(),
            $cookie_key
        );
        $this->ref_id = $ref_id;

        $this->switch_to_seb_skin_needed = $this->detectSwitchToSEBSkinNeeded();
        $this->current_user_allowed = $this->detectCurrentUserAllowed();
        $this->key_check_possible_or_unavoidable = $this->detectKeyCheckPossibleOrForced();

        $this->updateControlURIInSession();
    }

    public function isKeyCheckPossibleOrUnavoidable(): bool
    {
        return $this->key_check_possible_or_unavoidable;
    }

    public function exitIlias(\ilSEBPlugin $pl): void
    {
        \ilSession::setClosingContext(\ilSession::SESSION_CLOSE_LOGIN);
        if ($this->auth->isValid()) {
            $this->auth->logout();
        }
        session_unset();
        session_destroy();

        $tpl = $pl->getTemplate('default/tpl.seb_forbidden.html');
        $tpl->setCurrentBlock('seb_forbidden_message');
        $tpl->setVariable('SEB_FORBIDDEN_HEADER', $pl->txt('forbidden_header'));
        $tpl->setVariable('SEB_FORBIDDEN_MESSAGE', $pl->txt('forbidden_message'));
        $tpl->setVariable(
            'SEB_LOGIN_LINK',
            'login.php?' . $this->buildTargetString()
            . 'client_id=' . rawurlencode(CLIENT_ID)
            . '&cmd=force_login&lang=' . $this->user->getCurrentLanguage()
        );
        $tpl->setVariable('SEB_LOGIN_LINK_TEXT', $pl->txt('forbidden_login'));
        $tpl->parseCurrentBlock();
        $this->http->saveResponse(
            $this->http->response()
                ->withStatus(403, 'Forbidden')
                ->withBody(Streams::ofString($tpl->get()))
        );
        $this->http->sendResponse();
        exit;
    }

    private function buildTargetString(): string
    {
        if ($this->ref_id) {
            return 'target=' . \ilObject::_lookupType($this->ref_id, true) . '_' . $this->ref_id . '&';
        }

        if ($this->http->wrapper()->query()->has('target')) {
            return 'target='
                . $this->http->wrapper()->query()->retrieve(
                    'target',
                    $this->refinery->kindlyTo()->string()
                ) . '&';
        }

        return '';
    }

    private function sebKeyInHeaderPostCookieOrNone(): int
    {
        if ($this->http->request()->hasHeader(\ilSEBPlugin::REQ_HEADER)) {
            return \ilSEBPlugin::SEB_DATA_MODE['header'];
        }
        if ($this->http->wrapper()->cookie()->has('examKey')) {
            return \ilSEBPlugin::SEB_DATA_MODE['cookie'];
        }
        if ($this->config->isInsecureUserAgentKeyEnabled()
            && $this->http->request()->hasHeader('User-Agent')
            && preg_match('/SEBKEY=(.*)/', $this->http->request()->getHeader('User-Agent')[0])) {
            return \ilSEBPlugin::SEB_DATA_MODE['user_agent'];
        }
        return \ilSEBPlugin::SEB_DATA_MODE['none'];
    }

    private function isInsecureUnhashedMode(): bool
    {
        return $this->data->getMode() === \ilSEBPlugin::SEB_DATA_MODE['user_agent'];
    }

    private function detectCurrentUserAllowed(): bool {
        $role_deny = $this->config->getRoleDeny();
        if ($role_deny === 0
            || $role_deny !== 1 && !$this->rbacreview->isAssigned($this->user->getId(), $role_deny)
            || $this->detectSeb($this->ref_id) >= \ilSEBPlugin::SEB_REQUEST_TYPES['seb_request']
            || $this->anySEBKeyIsEnough() && $this->detectSeb()) {
            return true;
        }
        return false;
    }

    private function anySEBKeyIsEnough(): bool
    {
        $cmd = $this->ctrl->getCmd();
        if ($this->config->doesContextAllowAnyObjectKey(\ilContext::getType())
            || $this->config->doesCommandAllowAnyObjectKey($cmd)
            || $this->config->doClassAndCommandAllowAnyObjectKey($this->ctrl->getCmdClass(), $cmd)) {
            return true;
        }

        return $this->config->doesPathAllowAnyObjectKey(
            $this->http->request()->getUri()->getPath()
        );
    }

    private function detectSwitchToSEBSkinNeeded(): bool {
        $role_kiosk = $this->config->getRoleKiosk();
        if ($role_kiosk === 0
            || $role_kiosk !== 1 && !$this->rbacreview->isAssigned($this->user->getId(), $role_kiosk)) {
            return false;
        }
        return true;
    }

    private function detectKeyCheckPossibleOrForced(): bool
    {
        $check_forced = \ilSession::get('check_forced') ?? 0;
        if ($this->data->getMode() === \ilSEBPlugin::SEB_DATA_MODE['cookie']
            && ($this->data->getExamKey() === '' || $check_forced === 0)) {
            \ilSession::set('check_forced', ++$check_forced);
            return false;
        }
        \ilSession::clear('check_forced');
        return true;
    }

    private function detectSeb(?int $ref_id = null): int
    {
        \ilSession::clear('url_to_check');
        $exam_key = $this->data->getExamKey();
        if ($exam_key === '') {
            \ilSession::set('cookie_ui', $this->retrieveFullUri());
            return \ilSebPlugin::SEB_REQUEST_TYPES['not_a_seb_request'];
        }

        $uri = $this->data->getMode() === \ilSEBPlugin::SEB_DATA_MODE['cookie']
                ? $this->data->getCookieUri()
                : $this->data->getRequestUri();

        if ($this->keys_checker->checkGlobalKey(
                $exam_key,
                $uri,
                $this->isInsecureUnhashedMode()
            )) {
            return \ilSebPlugin::SEB_REQUEST_TYPES['seb_request'];
        }
        if ($this->keys_checker->checkObjectKey(
                $exam_key,
                $uri,
                $ref_id,
                $this->isInsecureUnhashedMode()
            )) {
            return \ilSebPlugin::SEB_REQUEST_TYPES['seb_request_object_keys'];
        }
        if (!$ref_id && $this->keys_checker->checkKeyAgainstAllObjectKeys(
                $exam_key,
                $uri,
                $this->isInsecureUnhashedMode()
            )) {
            return \ilSebPlugin::SEB_REQUEST_TYPES['seb_request_object_keys_unspecific'];
        }
        return \ilSebPlugin::SEB_REQUEST_TYPES['seb_request_invalid'];
    }

    private function retrieveSEBData(int $mode, string $cookie_key): Data
    {
        $data = new Data(
            $mode,
            $this->retrieveFullUri(),
            \ilSession::get('cookie_uri')
        );

        switch ($mode) {
            case \ilSEBPlugin::SEB_DATA_MODE['header']:
                return $data->withExamKey(
                    $this->http->request()->getHeader(\ilSEBPlugin::REQ_HEADER)[0]
                );
            case \ilSEBPlugin::SEB_DATA_MODE['cookie']:
                return $data->withExamKey($cookie_key);
            case \ilSEBPlugin::SEB_DATA_MODE['user_agent']:
                preg_match(
                    '/SEBKEY=([a-zA-Z0-9_]+)/',
                    $this->http->request()->getHeader('User-Agent')[0],
                    $matches
                );
                return $data->withExamKey(trim($matches[1]));
            default:
                return $data;
        }
    }

    private function retrieveFullUri(): string
    {
        $uri = $this->http->request()->getUri();
        $protocol = $uri->getScheme();
        $port = $uri->getPort();
        $host = $uri->getHost();
        $path = $uri->getPath();
        $query = $uri->getQuery();

        if ($query !== '') {
            $query = '?' . $query;
        }

        if ($this->config->getIliasRootUri() !== '') {
            $root_uri = new \ILIAS\Data\URI($this->config->getIliasRootUri());
            $protocol = $root_uri->getSchema();
            $port = $root_uri->getPort();
            $host = $root_uri->getHost();
        }

        return $protocol . "://" . $host . $port . $path . $query;
    }

    private function updateControlURIInSession(): void
    {
        if (!in_array($this->ctrl->getCmd(''), self::CMDS_WITHOUT_URI_UPDATE)
            && stristr($this->http->request()->getUri()->getPath(), 'goto.php') === false) {
            \ilSession::set('cookie_uri', $this->retrieveFullUri());
        }
    }
}
