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
 * Bunny Stream source provider.
 *
 * @package   videoprogresssource_bunnystream
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videoprogresssource_bunnystream;

use context_module;
use local_video_bridge\source\plugin_base;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Implements Bunny Stream embed playback through the Player.js API supported by Bunny Stream.
 */
class plugin extends plugin_base {
    public function get_name(): string {
        return get_string('pluginname', 'videoprogresssource_bunnystream');
    }

    public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void {
        $mform->addElement(
            'text',
            'bunnyurl',
            get_string('bunnyurl', 'videoprogresssource_bunnystream'),
            ['size' => 80]
        );
        $mform->setType('bunnyurl', PARAM_TEXT);
        $mform->addHelpButton('bunnyurl', 'bunnyurl', 'videoprogresssource_bunnystream');
        $mform->hideIf('bunnyurl', $sourcefield, 'neq', 'bunnystream');
    }

    public function validation(array $data, array $files): array {
        try {
            $this->build_config((object)$data);
            return [];
        } catch (moodle_exception $exception) {
            return ['bunnyurl' => $exception->getMessage()];
        }
    }

    public function build_config(stdClass $data): array {
        return self::normalise(trim((string)($data->bunnyurl ?? '')));
    }

    public function get_legacy_value(array $config): string {
        return self::build_embed_url($config);
    }

    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $config = $this->decode_config((object)$defaultvalues);
        $defaultvalues['bunnyurl'] = self::build_embed_url($config);
    }

    public function get_player_config(stdClass $activity, context_module $context): array {
        $config = $this->decode_config($activity);
        return [
            'url' => self::build_embed_url($config),
            'origin' => 'https://iframe.mediadelivery.net',
            'playerjsurl' => 'https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js',
        ];
    }

    public function get_capabilities(): array {
        return [
            'tracking' => true,
            'seeking' => true,
            'playbackcontrol' => true,
            'playbackrate' => false,
        ];
    }

    public function supports_poster(): bool {
        return false;
    }

    public function supports_uploaded_captions(): bool {
        return false;
    }

    public function get_player_template(): string {
        return 'videoprogresssource_bunnystream/player';
    }

    public function get_amd_module(): string {
        return 'videoprogresssource_bunnystream/player';
    }

    protected function get_legacy_config(string $legacyvalue): array {
        return self::normalise($legacyvalue);
    }

    private static function normalise(string $value): array {
        $value = trim($value);

        if (preg_match('~^(\d{1,20})[/:]([A-Za-z0-9_-]{8,128})$~', $value, $matches)) {
            return [
                'libraryid' => $matches[1],
                'videoid' => $matches[2],
                'query' => '',
            ];
        }

        if (!filter_var($value, FILTER_VALIDATE_URL) ||
                strtolower((string)parse_url($value, PHP_URL_SCHEME)) !== 'https') {
            throw new moodle_exception('invalidurl', 'videoprogresssource_bunnystream');
        }

        $parts = parse_url($value);
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host !== 'iframe.mediadelivery.net') {
            throw new moodle_exception('invalidurl', 'videoprogresssource_bunnystream');
        }

        $path = (string)($parts['path'] ?? '');
        if (!preg_match('~^/embed/(\d{1,20})/([A-Za-z0-9_-]{8,128})/?$~', $path, $matches)) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_bunnystream');
        }

        return [
            'libraryid' => $matches[1],
            'videoid' => $matches[2],
            'query' => (string)($parts['query'] ?? ''),
        ];
    }

    private static function build_embed_url(array $config): string {
        $libraryid = (string)($config['libraryid'] ?? '');
        $videoid = (string)($config['videoid'] ?? '');
        if ($libraryid === '' || $videoid === '') {
            return '';
        }

        $url = 'https://iframe.mediadelivery.net/embed/' .
            rawurlencode($libraryid) . '/' . rawurlencode($videoid);
        $query = trim((string)($config['query'] ?? ''));
        if ($query !== '') {
            $url .= '?' . $query;
        }
        return $url;
    }
}
