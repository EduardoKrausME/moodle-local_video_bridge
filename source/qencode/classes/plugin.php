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
 * Qencode playback source provider.
 *
 * @package   videoprogresssource_qencode
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videoprogresssource_qencode;

use context_module;
use local_video_bridge\source\plugin_base;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Plays Qencode HLS and browser-compatible playback URLs through the shared HTML5/HLS adapters.
 */
class plugin extends plugin_base {
    /**
     * Returns the display name.
     * @return string Return value.
     */
    public function get_name(): string {
        return get_string('pluginname', 'videoprogresssource_qencode');
    }

    /**
     * Adds plugin-specific form elements.
     * @param MoodleQuickForm $mform mform.
     * @param string $sourcefield sourcefield.
     * @return void Return value.
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void {
        $mform->addElement(
            'url',
            'qencodeurl',
            get_string('qencodeurl', 'videoprogresssource_qencode'),
            ['size' => 80],
            ['usefilepicker' => false]
        );
        $mform->setType('qencodeurl', PARAM_URL);
        $mform->addHelpButton('qencodeurl', 'qencodeurl', 'videoprogresssource_qencode');
        $mform->hideIf('qencodeurl', $sourcefield, 'neq', 'qencode');
    }

    /**
     * Validates submitted form data.
     * @param array $data data.
     * @param array $files files.
     * @return array Return value.
     */
    public function validation(array $data, array $files): array {
        try {
            $this->build_config((object)$data);
            return [];
        } catch (moodle_exception $exception) {
            return ['qencodeurl' => $exception->getMessage()];
        }
    }

    /**
     * Builds normalized plugin configuration.
     * @param stdClass $data data.
     * @return array Return value.
     */
    public function build_config(stdClass $data): array {
        return self::normalise(trim((string)($data->qencodeurl ?? '')));
    }

    /**
     * Returns the legacy configuration value.
     * @param array $config config.
     * @return string Return value.
     */
    public function get_legacy_value(array $config): string {
        return (string)($config['url'] ?? '');
    }

    /**
     * Prepares values used by the edit form.
     * @param array $defaultvalues defaultvalues.
     * @param context_module $context context.
     * @return void Return value.
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $config = $this->decode_config((object)$defaultvalues);
        $defaultvalues['qencodeurl'] = (string)($config['url'] ?? '');
    }

    /**
     * Returns browser-safe player configuration.
     * @param stdClass $activity activity.
     * @param context_module $context context.
     * @return array Return value.
     */
    public function get_player_config(stdClass $activity, context_module $context): array {
        global $CFG;

        $config = $this->decode_config($activity);
        return [
            'url' => (string)($config['url'] ?? ''),
            'hls' => !empty($config['hls']),
            'hlsjsurl' => $CFG->wwwroot . '/local/video_bridge/vendor/hls/hls.min.js',
        ];
    }

    /**
     * Returns the player template name.
     * @return string Return value.
     */
    public function get_player_template(): string {
        return 'videoprogresssource_qencode/player';
    }

    /**
     * Returns the AMD player adapter module.
     * @return string Return value.
     */
    public function get_amd_module(): string {
        return 'videoprogresssource_qencode/player';
    }

    /**
     * Converts a legacy value into normalized configuration.
     * @param string $legacyvalue legacyvalue.
     * @return array Return value.
     */
    protected function get_legacy_config(string $legacyvalue): array {
        return self::normalise($legacyvalue);
    }

    /**
     * Normalizes and validates the source value.
     * @param string $url url.
     * @return array Return value.
     */
    private static function normalise(string $url): array {
        if (!filter_var($url, FILTER_VALIDATE_URL) ||
                strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new moodle_exception('invalidurl', 'videoprogresssource_qencode');
        }

        $extension = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($extension, ['m3u8', 'mp4', 'webm', 'ogv', 'm4v', 'mov'], true)) {
            throw new moodle_exception('invalidextension', 'videoprogresssource_qencode');
        }

        return [
            'url' => $url,
            'hls' => $extension === 'm3u8',
        ];
    }
}
