<?php
// This file is part of Moodle - http://moodle.org/.
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
 * Base contract for shared caption sources.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\caption;

use context_module;
use MoodleQuickForm;
use stdClass;

/**
 * Defines the contract implemented by every shared caption source.
 */
abstract class plugin_base {
    public function get_sort_order(): int {
        return 100;
    }

    abstract public function get_name(): string;

    abstract public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void;

    abstract public function validation(array $data, array $files): array;

    abstract public function build_config(stdClass $data): array;

    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
    }

    public function save_files(stdClass $data, context_module $context): void {
    }

    public function delete_files(context_module $context): void {
    }

    abstract public function get_tracks(stdClass $activity, context_module $context): array;

    final protected function decode_config(stdClass $activity): array {
        $raw = trim((string)($activity->captionconfig ?? ''));
        if ($raw === '') {
            return [];
        }

        $config = json_decode($raw, true);
        return is_array($config) ? $config : [];
    }
}
