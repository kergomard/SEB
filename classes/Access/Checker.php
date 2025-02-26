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

use kergomard\SEB\Config\Config;

use ILIAS\Filesystem\Stream\Streams;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as Refinery;

class Checker
{
    private \ilCtrl $ctrl;
    private \ilObjUser $user;
    private \ilAuthSession $auth;
    private \ilRbacReview $rbacreview;
    private Refinery $refinery;
    private HTTPServices $http;
    private Config $config;
    private array $data;
    private ?int $ref_id = null;
    private int $mode;

    private bool $current_user_allowed;
    private bool $switch_to_seb_skin_needed;

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
        \ilCtrl $ctrl,
        \ilObjUser $user,
        \ilAuthSession $auth,
        \ilRbacReview $rbacreview,
        Refinery $refinery,
        HTTPServices $http,
        Config $conf
    ) {
        $this->ctrl = $ctrl;
        $this->user = $user;
        $this->auth = $auth;
        $this->rbacreview = $rbacreview;
        $this->refinery = $refinery;
        $this->http = $http;
        $this->config = $conf;

        $this->mode = $this->sebKeyInHeaderPostCookieOrNone();
        $this->data = $this->retrieveSEBData($this->mode);
        $this->ref_id = $ref_id;

        $is_logged_in = $this->user->getId() && $this->user->getId() !== ANONYMOUS_USER_ID;
        $is_root = $this->rbacreview->isAssigned($this->user->getId(), SYSTEM_ROLE_ID);
        $this->switch_to_seb_skin_needed = $this->detectSwitchToSEBSkinNeeded($is_logged_in, $is_root);
        $this->current_user_allowed = $this->detectCurrentUserAllowed($is_logged_in, $is_root);

        \ilSession::set('last_uri', $this->retrieveFullUri());
    }

    public function isKeyCheckPossibleOrUnavoidable(): bool
    {
        if ($this->mode === \ilSEBPlugin::SEB_DATA_MODE['none']
            && $this->data['uri'] === ''
            && $this->data['last_uri'] !== '') {
            return false;
        }

        return true;
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
        if ($this->http->cookieJar()->has('examKey')
            && $this->http->cookieJar()->has('uri')) {
            return \ilSEBPlugin::SEB_DATA_MODE['cookie'];
        }
        return \ilSEBPlugin::SEB_DATA_MODE['none'];
    }

    private function detectCurrentUserAllowed(
        bool $is_logged_in,
        bool $is_root
    ): bool {
        $role_deny = $this->config->getRoleDeny();
        $allow_without_seb = true;

        if ($is_logged_in && $role_deny > 0 && !$is_root) {
            $allow_without_seb = $role_deny !== 1
                && !$this->rbacreview->isAssigned($this->user->getId(), $role_deny);
        }

        if ($allow_without_seb
            || $this->detectSeb($this->ref_id) >= \ilSEBPlugin::SEB_REQUEST_TYPES['seb_request']
            || $this->anySEBKeyIsEnough() && $this->detectSeb()) {
            return true;
        }

        return false;
    }

    private function anySEBKeyIsEnough(): bool
    {
        $cmd = $this->ctrl->getCmd();
        $cmdclass = $this->ctrl->getCmdClass();
        $path = $this->http->request()->getUri()->getPath();

        if (in_array(\ilContext::getType(), \ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['context_check'])
            || in_array($cmd, \ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_check'])
            || array_key_exists($cmdclass, \ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_and_cmdclass_check'])
                && in_array($cmd, \ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['cmd_and_cmdclass_check'][$cmdclass])) {
            return true;
        }

        foreach (\ilSEBPlugin::REQUESTS_THAT_DONT_NEED_OBJECT_SPECIFIC_KEYS['path_check'] as $exempted_path) {
            if (mb_strpos($path, $exempted_path)) {
                return true;
            }
        }

        return false;
    }

    private function detectSwitchToSEBSkinNeeded(
        bool $is_logged_in,
        bool $is_root
    ): bool {
        $is_kiosk_user = (
            $this->config->getRoleKiosk() === 1
            || $this->rbacreview->isAssigned($this->user->getId(), $this->config->getRoleKiosk())
        ) && !$is_root;

        if ($is_logged_in && $is_kiosk_user) {
            return true;
        }

        return false;
    }

    private function detectSeb(?int $ref_id = null): int
    {
        $exam_key = $this->data['exam_key'];

        if ($exam_key === "") {
            return \ilSebPlugin::SEB_REQUEST_TYPES['not_a_seb_request'];
        }

        if ($this->config->checkSebKey($exam_key, $this->data['uri'])) {
            return \ilSebPlugin::SEB_REQUEST_TYPES['seb_request'];
        }
        if ($this->config->checkObjectKey($exam_key, $this->data['uri'], $ref_id)) {
            return \ilSebPlugin::SEB_REQUEST_TYPES['seb_request_object_keys'];
        }

        if (!$ref_id && $this->config->checkKeyAgainstAllObjectKeys($exam_key, $this->data['uri'])) {
            return \ilSebPlugin::SEB_REQUEST_TYPES['seb_request_object_keys_unspecific'];
        }

        return \ilSebPlugin::SEB_REQUEST_TYPES['seb_request_invalid'];
    }

    private function retrieveSEBData(int $mode): array
    {
        $data = [
            'uri' => $this->retrieveFullUri(),
            'last_uri' => \ilSession::get('last_uri')
        ];

        switch ($mode) {
            case \ilSEBPlugin::SEB_DATA_MODE['header']:
                $data['exam_key'] = $this->http->request()->getHeader(\ilSEBPlugin::REQ_HEADER)[0];
                break;
            case \ilSEBPlugin::SEB_DATA_MODE['cookie']:
                $data['exam_key'] = $this->http->cookieJar()->get('examKey');
                break;
            default:
                $data['exam_key'] = '';
                break;
        }

        return $data;
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
}
