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
 * @package   videoprogresssource_embed
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videoprogresssource_embed;

use context_module;
use local_video_bridge\source\plugin_base;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Generic HTTP(S) iframe source.
 */
class plugin extends plugin_base {
    /**
     * Returns the source name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'videoprogresssource_embed');
    }

    /**
     * Adds the iframe URL field.
     *
     * @param MoodleQuickForm $mform Activity form.
     * @param string $sourcefield Source selector field.
     * @return void
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void {
        $mform->addElement('url', 'embedurl', get_string('embedurl', 'videoprogresssource_embed'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('embedurl', PARAM_URL);
        $mform->hideIf('embedurl', $sourcefield, 'neq', 'embed');
    }

    /**
     * Validates an iframe URL.
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
            return ['embedurl' => $exception->getMessage()];
        }
    }

    /**
     * Normalizes the iframe URL and trusted origin.
     *
     * @param stdClass $data Submitted activity data.
     * @return array
     */
    public function build_config(stdClass $data): array {
        return self::normalise(trim((string)($data->embedurl ?? '')));
    }

    /**
     * Returns the legacy iframe URL.
     *
     * @param array $config Normalized configuration.
     * @return string
     */
    public function get_legacy_value(array $config): string {
        return (string)($config['url'] ?? '');
    }

    /**
     * Restores the iframe URL.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Activity context.
     * @return void
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $config = $this->decode_config((object)$defaultvalues);
        $defaultvalues['embedurl'] = (string)($config['url'] ?? '');
    }

    /**
     * Returns iframe configuration.
     *
     * @param stdClass $activity Activity record.
     * @param context_module $context Activity context.
     * @return array
     */
    public function get_player_config(stdClass $activity, context_module $context): array {
        return $this->decode_config($activity);
    }

    /**
     * Generic embeds do not guarantee a remote player protocol.
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
     * The embedded provider owns its poster.
     *
     * @return bool
     */
    public function supports_poster(): bool {
        return false;
    }

    /**
     * The embedded provider owns its captions.
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
        return 'videoprogresssource_embed/player';
    }

    /**
     * Returns the player adapter.
     *
     * @return string
     */
    public function get_amd_module(): string {
        return 'videoprogresssource_embed/player';
    }

    /**
     * Converts an older URL into normalized configuration.
     *
     * @param string $legacyvalue Legacy URL.
     * @return array
     */
    protected function get_legacy_config(string $legacyvalue): array {
        return self::normalise($legacyvalue);
    }

    /**
     * Validates and normalizes an HTTP(S) iframe URL.
     *
     * @param string $url Iframe URL.
     * @return array
     * @throws moodle_exception
     */
    private static function normalise(string $url): array {
        if (!filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_embed');
        }

        $parts = parse_url($url);
        $origin = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . (int)$parts['port'];
        }
        return ['url' => $url, 'origin' => $origin];
    }
}
