<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Video completed event.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\event;

/**
 * Fired when authoritative watched percentage first reaches 100%.
 */
final class video_completed extends \core\event\base {
    /**
     * Initializes event metadata.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_video_bridge_progress';
    }

    /**
     * Returns the localized event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventvideocompleted', 'local_video_bridge');
    }

    /**
     * Returns the event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "Video reached 100% authoritative progress for user {$this->relateduserid}.";
    }

    /**
     * Returns the related course URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }

    /**
     * Validates required event data.
     *
     * @return void
     */
    protected function validate_data(): void {
        parent::validate_data();
        foreach (['component', 'itemid', 'mediahash', 'percent'] as $field) {
            if (!array_key_exists($field, $this->other)) {
                throw new \coding_exception("video_completed requires {$field}.");
            }
        }
    }
}
