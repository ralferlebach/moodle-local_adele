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

/**
 * Report - and on request repair - learning paths that store a connection
 * twice (local_adele #603). Without --execute nothing is written.
 *
 * php local/adele/cli/repair_path_edges.php [--execute]
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(['execute' => false, 'help' => false], ['e' => 'execute', 'h' => 'help']);

if ($options['help'] || $unrecognized) {
    cli_writeln("Learning paths with a connection stored twice (local_adele #603).

Without --execute: report only, nothing is written.
  -e, --execute  Repair: remove copies of a connection, give an edge whose id
                 another connection holds a free id; in the paths of people
                 only copies are removed.
  -h, --help     This help.");
    exit($unrecognized ? 1 : 0);
}

\local_adele\path_edges::repair_stored(!$options['execute'], function (string $line): void {
    cli_writeln($line);
});
