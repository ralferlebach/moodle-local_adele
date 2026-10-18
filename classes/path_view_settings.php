<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_adele;

/**
 * Site-wide settings of the open learning path view: how it refreshes
 * (mod_adele #26) and where it focuses after a change (mod_adele #27).
 *
 * Handed to the Vue app once, when the page is rendered - polling never asks
 * for them again.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_view_settings {
    /** @var int No automatic refresh after the first load. */
    const POLLING_OFF = 0;
    /** @var int Refresh when the page gets the focus back. */
    const POLLING_FOCUS = 1;
    /** @var int Focus refresh, and polling while the page is visible and focused. */
    const POLLING_ACTIVE = 2;
    /** @var int Focus refresh, and polling in the background too. */
    const POLLING_ALWAYS = 3;

    /** @var int No auto-focus. */
    const FOCUS_NONE = 0;
    /** @var int The node completed or changed last. */
    const FOCUS_LASTCHANGE = 1;
    /** @var int The next open, not yet completed nodes. */
    const FOCUS_NEXTOPEN = 2;

    /** @var array Defaults and bounds of the numbers. */
    const BOUNDS = [
        'pathpollinginterval' => [3000, 1000, 300000],
        'pathpollingdebounce' => [300, 0, 10000],
    ];

    /**
     * The settings, checked: an invalid stored value counts as its default.
     *
     * @return array
     */
    public static function get(): array {
        $config = get_config('local_adele');
        $whole = static fn($value): bool => is_scalar($value) && preg_match('/^-?\d+$/', trim((string) $value)) === 1;
        $choice = static function ($value, array $allowed, int $default) use ($whole): int {
            return ($whole($value) && in_array((int) $value, $allowed, true)) ? (int) $value : $default;
        };
        $number = static function (string $name) use ($config, $whole): int {
            [$default, $min, $max] = self::BOUNDS[$name];
            $value = $config->$name ?? null;
            return ($whole($value) && (int) $value >= $min && (int) $value <= $max) ? (int) $value : $default;
        };
        return [
            'pollingmode' => $choice($config->pathpollingmode ?? null, [0, 1, 2, 3], self::POLLING_ACTIVE),
            'pollinginterval' => $number('pathpollinginterval'),
            'pollingdebounce' => $number('pathpollingdebounce'),
            'autofocusenabled' => $choice($config->pathautofocusenabled ?? null, [0, 1], 1),
            'autofocusteachermode' => $choice($config->pathautofocusteachermode ?? null, [0, 1, 2], self::FOCUS_LASTCHANGE),
            'autofocuslearnermode' => $choice($config->pathautofocuslearnermode ?? null, [0, 1, 2], self::FOCUS_NEXTOPEN),
        ];
    }

    /**
     * The settings as the attribute of the app element.
     *
     * @return string JSON.
     */
    public static function for_template(): string {
        return json_encode(self::get());
    }
}
