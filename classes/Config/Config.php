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

namespace kergomard\SEB\Config;

class Config
{
    /**
     * By setting this to true you can enable a check for a seb-key in the
     * user-agent header. There are good reasons, why this setting can not
     * be changed from the interface: It reduces the security guarantees
     * provided by the SEB and this plugin drastically. If you are sure you
     * know what you are doing, change the value to `true` to enable it.
     */
    private const ENABLE_INSECURE_USER_AGENT_KEY = false;

    public const MAX_CONFIG_VALUE_LENGTH = 2000;
    private const CMD_CLASSES_WITHOUT_SEB_KEY_TAB = [
        'ilsebsessionstabgui',
        'ilsebsettingstabgui',
        'iltestevaluationgui',
        'ilobjectactivationgui',
        'ilassquestionpreviewgui'
    ];
    private const VALID_CONF_KEYS = [
        'seb_keys',
        'allow_object_keys',
        'role_deny',
        'role_kiosk',
        'activate_session_control',
        'show_pax_pic',
        'show_pax_matriculation',
        'show_pax_username',
        'ilias_root_uri',
        'header_bg_color',
        'header_color'
    ];
    private const DEFAULT_HEADER_BG_COLOR = '#6EA03C';
    private const DEFAULT_HEADER_COLOR = '#FFF';

    private array $conf = [
        'seb_keys' => []
    ];
    private \ilDBInterface $db;

    public function __construct(\ilDBInterface $db)
    {
        $this->db = $db;
        if ($this->db->tableExists('ui_uihk_seb_conf')) {
            $this->readSEBConf();
        }
    }

    public function getCmdClassesWithoutSebKeyTab(): array
    {
        return self::CMD_CLASSES_WITHOUT_SEB_KEY_TAB;
    }

    public function checkSebKey(
        string $key_from_browser,
        string $request_url,
        bool $insecure_unhashed_check_needed
    ): bool {
        if ($insecure_unhashed_check_needed) {
            return $this->insecurelyCheckKeysAgainstUnhashedUrls(
                $key_from_browser,
                $this->conf['seb_keys']
            );
        }
        return $this->checkKeys(
            $key_from_browser,
            $this->conf['seb_keys'],
            $request_url
        );
    }

    public function getSebKeysString(): string
    {
        return implode(',', $this->conf['seb_keys']);
    }

    public function getRoleDeny(): int
    {
        return (int) $this->conf['role_deny'];
    }

    public function getRoleKiosk(): int
    {
        return (int) $this->conf['role_kiosk'];
    }

    public function getAllowObjectKeys(): bool
    {
        return (bool) $this->conf['allow_object_keys'];
    }

    public function getActivateSessionControl(): bool
    {
        return (bool) $this->conf['activate_session_control'];
    }

    public function getShowPaxPic(): bool
    {
        return (bool) $this->conf['show_pax_pic'];
    }

    public function getShowPaxMatriculation(): bool
    {
        return (bool) $this->conf['show_pax_matriculation'];
    }

    public function getShowPaxUsername(): bool
    {
        return (bool) $this->conf['show_pax_username'];
    }

    public function getIliasRootUri(): string
    {
            return $this->conf['ilias_root_uri'] ?? '';
    }

    public function getHeaderBackgroundColor(): string
    {
            return $this->conf['header_bg_color'] ?? self::DEFAULT_HEADER_BG_COLOR;
    }

    public function getHeaderColor(): string
    {
            return $this->conf['header_color'] ?? self::DEFAULT_HEADER_COLOR;
    }

    public function isInsecureUserAgentKeyEnabled(): bool
    {
        return self::ENABLE_INSECURE_USER_AGENT_KEY;
    }

    /**
     * @return string[]
     */
    public function getObjectKeys(int $ref_id): array
    {
        if (($keys = $this->db->fetchAssoc(
            $this->db->query(
                'SELECT * FROM ui_uihk_seb_keys where ref_id='
                    . $this->db->quote($ref_id, 'integer')
            )
        ))) {
            return $keys;
        }
        return [
            'seb_key_win' => '',
            'seb_key_macos' => ''
        ];
    }

    public function checkObjectKey(
        string $key,
        string $url,
        ?int $ref_id,
        bool $insecure_unhashed_check_needed
    ): bool {
        if ($ref_id === null || !$this->conf['allow_object_keys']) {
            return false;
        }

        $keys = $this->getObjectKeys($ref_id);
        if ($keys['seb_key_win'] === '' && $keys['seb_key_macos'] === '') {
            return false;
        }

        $merged_keys = array_merge(
            explode(',', $keys['seb_key_win']),
            explode(',', $keys['seb_key_macos'])
        );

        if ($insecure_unhashed_check_needed) {
            return $this->insecurelyCheckKeysAgainstUnhashedUrls($key, $merged_keys);
        }

        return $this->checkKeys($key, $merged_keys, $url);
    }

    public function checkKeyAgainstAllObjectKeys(
        string $key,
        string $url,
        bool $insecure_unhashed_check_needed
    ): bool {
        if (!$this->conf['allow_object_keys']) {
            return false;
        }

        $keys = array_reduce(
            $this->db->fetchAll(
                $this->db->query('SELECT seb_key_win, seb_key_macos FROM ui_uihk_seb_keys')
            ),
            static fn (array $c, array $v): array => array_merge(
                $c,
                explode(',', $v['seb_key_win']),
                explode(',', $v['seb_key_macos'])
            ),
            []
        );

        if ($keys === []) {
            return false;
        }

        if ($insecure_unhashed_check_needed) {
            return $this->insecurelyCheckKeysAgainstUnhashedUrls($key, $keys);
        }
        return $this->checkKeys($key, $keys, $url);
    }

    public function saveSEBConf(array $conf): int
    {
        $r = 0;
        foreach ($conf as $name => $value) {
            if (!in_array($name, self::VALID_CONF_KEYS)) {
                return -1;
            }

            if ($this->db->replace(
                'ui_uihk_seb_conf',
                [
                    'name' => ['text', $name]
                ],
                [
                    'value' => ['text', $value]
                ]
            ) > 0) {
                $r += 1;
            }
        }
        $this->readSEBConf();
        return $r;
    }

    public function saveObjectKeys(
        int $ref_id,
        string $seb_key_win,
        string $seb_key_macos
    ): int {
        return $this->db->replace(
            'ui_uihk_seb_keys',
            [
                'ref_id' => ['integer', $ref_id]
            ],
            [
                'seb_key_win' => ['text', $seb_key_win],
                'seb_key_macos' => ['text', $seb_key_macos]
            ]
        );
    }

    private function readSEBConf(): void
    {
        $query = $this->db->query('SELECT * FROM ui_uihk_seb_conf');
        while (($row = $this->db->fetchAssoc($query)) !== null) {
            if ($row['name'] === 'seb_keys') {
                $this->conf['seb_keys'] = $this->buildSEBKeysFromConfigString($row['value']);
                continue;
            }
            $this->conf[$row['name']] = $row['value'];
        }
    }

    private function buildSEBKeysFromConfigString(string $value): array
    {
        return array_map(
            fn (string $v): string => trim($v),
            explode(',', $value)
        );
    }

    private function checkKeys(
        string $key_from_browser,
        array $keys_from_config,
        string $request_url
    ): bool {
        foreach ($keys_from_config as $key_from_config) {
            if ($key_from_browser === hash('sha256', $request_url . trim($key_from_config))) {
                return true;
            }
        }

        return false;
    }

    private function insecurelyCheckKeysAgainstUnhashedUrls(
        string $key_from_browser,
        array $keys_from_config
    ): bool {
        foreach ($keys_from_config as $key_from_config) {
            if ($key_from_browser === trim($key_from_config)) {
                return true;
            }
        }
    }

    public function setOnScreenMessage(
        \ilGlobalTemplateInterface $tpl,
        \ilSEBPlugin $pl,
        int $lines_changed
    ): void {
        if ($lines_changed < 0) {
            $tpl->setOnScreenMessage('failure', $pl->txt('save_failure'), true);
            return;
        }
        if ($lines_changed === 0) {
            $tpl->setOnScreenMessage('failure', $pl->txt('nothing_changed'), true);
            return;
        }
        $tpl->setOnScreenMessage('success', $pl->txt('save_success'), true);
    }
}
