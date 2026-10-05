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
 * Shared video source.
 *
 * @package   videoprogresssource_drive
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videoprogresssource_drive;

use context_module;
use local_video_bridge\source\plugin_base;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Google Drive preview source.
 */
class plugin extends plugin_base {
    /**
     * Returns the source name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'videoprogresssource_drive');
    }

    /**
     * Adds the Drive URL or identifier field.
     *
     * @param MoodleQuickForm $mform Activity form.
     * @param string $sourcefield Source selector field.
     * @return void
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void {
        $mform->addElement('text', 'driveurl', get_string('driveurl', 'videoprogresssource_drive'), ['size' => 80]);
        $mform->setType('driveurl', PARAM_TEXT);
        $mform->hideIf('driveurl', $sourcefield, 'neq', 'drive');
    }

    /**
     * Validates a Drive identifier or URL.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation(array $data, array $files): array {
        try {
            $this->build_config((object)$data);
            return [];
        } catch (moodle_exception $exception) {
            return ['driveurl' => $exception->getMessage()];
        }
    }

    /**
     * Normalizes the Drive file identifier.
     *
     * @param stdClass $data Submitted activity data.
     * @return array
     */
    public function build_config(stdClass $data): array {
        return ['id' => self::extract_id(trim((string)($data->driveurl ?? '')))];
    }

    /**
     * Returns the legacy Drive value.
     *
     * @param array $config Normalized configuration.
     * @return string
     */
    public function get_legacy_value(array $config): string {
        return (string)($config['id'] ?? '');
    }

    /**
     * Restores the Drive URL in the edit form.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Activity context.
     * @return void
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $config = $this->decode_config((object)$defaultvalues);
        $defaultvalues['driveurl'] = !empty($config['id'])
            ? 'https://drive.google.com/file/d/' . $config['id'] . '/view'
            : '';
    }

    /**
     * Returns the Google Drive preview URL.
     *
     * @param stdClass $activity Activity record.
     * @param context_module $context Activity context.
     * @return array
     */
    public function get_player_config(stdClass $activity, context_module $context): array {
        $config = $this->decode_config($activity);
        $id = (string)($config['id'] ?? '');
        return [
            'url' => 'https://drive.google.com/file/d/' . rawurlencode($id) . '/preview',
        ];
    }

    /**
     * Returns capabilities guaranteed by Drive preview.
     *
     * @return array
     */
    public function get_capabilities(): array {
        return [
            'tracking' => false,
            'seeking' => false,
            'playbackcontrol' => false,
            'playbackrate' => false,
        ];
    }

    /**
     * Drive owns its visual presentation.
     *
     * @return bool
     */
    public function supports_poster(): bool {
        return false;
    }

    /**
     * Drive manages captions remotely.
     *
     * @return bool
     */
    public function supports_uploaded_captions(): bool {
        return false;
    }

    /**
     * Returns the player template.
     *
     * @return string
     */
    public function get_player_template(): string {
        return 'videoprogresssource_drive/player';
    }

    /**
     * Returns the player adapter.
     *
     * @return string
     */
    public function get_amd_module(): string {
        return 'videoprogresssource_drive/player';
    }

    /**
     * Converts a legacy value to normalized configuration.
     *
     * @param string $legacyvalue Legacy value.
     * @return array
     */
    protected function get_legacy_config(string $legacyvalue): array {
        return ['id' => self::extract_id($legacyvalue)];
    }

    /**
     * Extracts a Drive file id.
     *
     * @param string $value Drive URL or identifier.
     * @return string
     * @throws moodle_exception
     */
    private static function extract_id(string $value): string {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9_-]{10,}$/', $value)) {
            return $value;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_drive');
        }

        $host = strtolower((string)$parts['host']);
        if (!in_array($host, ['drive.google.com', 'www.drive.google.com', 'docs.google.com'], true)) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_drive');
        }

        if (preg_match('~/file/d/([A-Za-z0-9_-]{10,})~', (string)($parts['path'] ?? ''), $matches)) {
            return $matches[1];
        }

        parse_str((string)($parts['query'] ?? ''), $query);
        if (!empty($query['id']) && preg_match('/^[A-Za-z0-9_-]{10,}$/', (string)$query['id'])) {
            return (string)$query['id'];
        }

        throw new moodle_exception('invalidurl', 'videoprogresssource_drive');
    }
}
