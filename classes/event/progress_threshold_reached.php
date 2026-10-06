<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_video_bridge\event;

/**
 * Fired only when a registered watched-percentage threshold is crossed.
 *
 * @package local_video_bridge
 */
final class progress_threshold_reached extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_video_bridge_progress';
    }

    public static function get_name(): string {
        return get_string('eventprogressthresholdreached', 'local_video_bridge');
    }

    public function get_description(): string {
        return "Video progress crossed the {$this->other['threshold']}% threshold for user {$this->relateduserid}.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }

    protected function validate_data(): void {
        parent::validate_data();
        foreach (['component', 'itemid', 'mediahash', 'threshold', 'percent'] as $field) {
            if (!array_key_exists($field, $this->other)) {
                throw new \coding_exception("progress_threshold_reached requires {$field}.");
            }
        }
    }
}
