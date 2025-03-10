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

use kergomard\SEB\Config\ObjectSpecificKeys;
use kergomard\SEB\Config\Configuration;
use kergomard\SEB\Config\Repository;

class KeysChecker
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly Repository $repository
    ) {
    }

    public function checkGlobalKey(
        string $key_from_browser,
        string $url,
        bool $insecure_unhashed_check_needed
    ): bool {
        if ($insecure_unhashed_check_needed) {
            return $this->insecurelyCheckKeysAgainstUnhashedUrls(
                $key_from_browser,
                $this->configuration->getGlobalKeys()
            );
        }
        return $this->checkKeys(
            $key_from_browser,
            $this->configuration->getGlobalKeys(),
            $url
        );
    }

    public function checkObjectKey(
        string $key,
        string $url,
        ?int $ref_id,
        bool $insecure_unhashed_check_needed
    ): bool {
        if ($ref_id === null || !$this->configuration->getObjectKeysEnabled()) {
            return false;
        }

        $keys = $this->repository->getObjectSpecificKeysFor($ref_id);
        if ($keys->getMergedKeysArray() === []) {
            return false;
        }

        if ($insecure_unhashed_check_needed) {
            return $this->insecurelyCheckKeysAgainstUnhashedUrls($key, $keys->getMergedKeysArray());
        }

        return $this->checkKeys($key, $keys->getMergedKeysArray(), $url);
    }

    public function checkKeyAgainstAllObjectKeys(
        string $key,
        string $url,
        bool $insecure_unhashed_check_needed
    ): bool {
        if (!$this->configuration->getObjectKeysEnabled()) {
            return false;
        }

        $keys = array_reduce(
            $this->repository->getAllObjectSpecificKeys(),
            fn (array $c, ObjectSpecificKeys $v): array => array_merge(
                $c,
                $v->getMergedKeysArray()
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

    private function checkKeys(
        string $key_from_browser,
        array $keys_from_config,
        string $request_url
    ): bool {
        foreach ($keys_from_config as $key_from_config) {
            if ($key_from_browser === hash('sha256', $request_url . $key_from_config)) {
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
}
