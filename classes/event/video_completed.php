<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_video_bridge\event;

/**
 * Fired when authoritative watched percentage first reaches 100%.
 *
 * @package local_video_bridge
 */
final class video_completed extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_video_bridge_progress';
    }

    public static function get_name(): string {
        return get_string('eventvideocompleted', 'local_video_bridge');
    }

    public function get_description(): string {
        return "Video reached 100% authoritative progress for user {$this->relateduserid}.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }

    protected function validate_data(): void {
        parent::validate_data();
        foreach (['component', 'itemid', 'mediahash', 'percent'] as $field) {
            if (!array_key_exists($field, $this->other)) {
                throw new \coding_exception("video_completed requires {$field}.");
            }
        }
    }
}
