<?php
namespace local_video_bridge\event;

defined('MOODLE_INTERNAL') || die;

/**
 * Fired after normalized progress/session analytics are stored.
 *
 * Consumer plugins can observe this event to invalidate or rebuild their own
 * derived caches without Video Bridge knowing about those consumers.
 *
 * @package local_video_bridge
 */
final class analytics_updated extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_video_bridge_progress';
    }

    public static function get_name(): string {
        return get_string('eventanalyticsupdated', 'local_video_bridge');
    }

    public function get_description(): string {
        $component = $this->other['component'] ?? '';
        $itemid = $this->other['itemid'] ?? 0;
        return "Video analytics were updated for user {$this->relateduserid}, consumer {$component}, item {$itemid}.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }

    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['component'], $this->other['itemid'], $this->other['mediahash'])) {
            throw new \coding_exception('analytics_updated requires component, itemid and mediahash.');
        }
    }
}
