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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Analytics updated event.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\event;

/**
 * Fired after normalized progress/session analytics are stored.
 */
final class analytics_updated extends \core\event\base {
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
        return get_string('eventanalyticsupdated', 'local_video_bridge');
    }

    /**
     * Returns the event description.
     *
     * @return string
     */
    public function get_description(): string {
        $component = $this->other['component'] ?? '';
        $itemid = $this->other['itemid'] ?? 0;
        return "Video analytics were updated for user {$this->relateduserid}, consumer {$component}, item {$itemid}.";
    }

    /**
     * Returns a course URL for the event.
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
        if (!isset($this->other['component'], $this->other['itemid'], $this->other['mediahash'])) {
            throw new \coding_exception('analytics_updated requires component, itemid and mediahash.');
        }
    }
}
