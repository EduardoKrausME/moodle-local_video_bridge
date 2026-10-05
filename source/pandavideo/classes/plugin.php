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
 * @package   videoprogresssource_pandavideo
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videoprogresssource_pandavideo;

use context_module;
use curl;
use local_video_bridge\source\plugin_base;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Panda Video source backed by the public Panda oEmbed endpoint.
 */
class plugin extends plugin_base {
    /**
     * Returns the source name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'videoprogresssource_pandavideo');
    }

    /**
     * Adds the Panda identifier or dashboard URL field.
     *
     * @param MoodleQuickForm $mform Activity form.
     * @param string $sourcefield Source selector field.
     * @return void
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void {
        $mform->addElement('text', 'pandaurl', get_string('pandaurl', 'videoprogresssource_pandavideo'),
            ['size' => 80]);
        $mform->setType('pandaurl', PARAM_TEXT);
        $mform->hideIf('pandaurl', $sourcefield, 'neq', 'pandavideo');
    }

    /**
     * Validates a Panda identifier or dashboard URL.
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
            return ['pandaurl' => $exception->getMessage()];
        }
    }

    /**
     * Stores only the stable Panda video identifier.
     *
     * @param stdClass $data Submitted activity data.
     * @return array
     */
    public function build_config(stdClass $data): array {
        return ['id' => self::extract_id(trim((string)($data->pandaurl ?? '')))];
    }

    /**
     * Returns the legacy Panda identifier.
     *
     * @param array $config Normalized configuration.
     * @return string
     */
    public function get_legacy_value(array $config): string {
        return (string)($config['id'] ?? '');
    }

    /**
     * Restores an editable Panda dashboard URL.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Activity context.
     * @return void
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $config = $this->decode_config((object)$defaultvalues);
        $defaultvalues['pandaurl'] = !empty($config['id'])
            ? 'https://dashboard.pandavideo.com.br/videos/' . $config['id']
            : '';
    }

    /**
     * Resolves Panda oEmbed data into a validated player URL.
     *
     * @param stdClass $activity Activity record.
     * @param context_module $context Activity context.
     * @return array
     * @throws moodle_exception
     */
    public function get_player_config(stdClass $activity, context_module $context): array {
        $config = $this->decode_config($activity);
        $id = (string)($config['id'] ?? '');
        if ($id === '') {
            throw new moodle_exception('invalidurl', 'videoprogresssource_pandavideo');
        }

        $dashboard = 'https://dashboard.pandavideo.com.br/videos/' . rawurlencode($id);
        $endpoint = 'https://api-v2.pandavideo.com.br/oembed?url=' . rawurlencode($dashboard);

        $curl = new curl();
        $body = $curl->get($endpoint);
        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);

        if ($status !== 200 || !is_string($body) || $body === '') {
            throw new moodle_exception('playererror', 'videoprogresssource_pandavideo');
        }

        $data = json_decode($body);
        if (!is_object($data) || empty($data->html) ||
                !preg_match('/\bsrc=["\'](https?:\/\/[^"\']+)["\']/i', (string)$data->html, $matches)) {
            throw new moodle_exception('playererror', 'videoprogresssource_pandavideo');
        }

        $url = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new moodle_exception('playererror', 'videoprogresssource_pandavideo');
        }

        $parts = parse_url($url);
        $origin = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . (int)$parts['port'];
        }

        return [
            'id' => $id,
            'url' => $url,
            'origin' => $origin,
            'width' => (int)($data->width ?? 16),
            'height' => (int)($data->height ?? 9),
        ];
    }

    /**
     * Returns Panda capabilities.
     *
     * @return array
     */
    public function get_capabilities(): array {
        return [
            'tracking' => true,
            'seeking' => true,
            'playbackcontrol' => false,
            'playbackrate' => false,
        ];
    }

    /**
     * Panda owns its poster.
     *
     * @return bool
     */
    public function supports_poster(): bool {
        return false;
    }

    /**
     * Panda manages captions remotely.
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
        return 'videoprogresssource_pandavideo/player';
    }

    /**
     * Returns the player adapter.
     *
     * @return string
     */
    public function get_amd_module(): string {
        return 'videoprogresssource_pandavideo/player';
    }

    /**
     * Converts a legacy identifier or URL.
     *
     * @param string $legacyvalue Legacy value.
     * @return array
     */
    protected function get_legacy_config(string $legacyvalue): array {
        return ['id' => self::extract_id($legacyvalue)];
    }

    /**
     * Extracts a Panda video identifier.
     *
     * @param string $value Identifier or Panda dashboard URL.
     * @return string
     * @throws moodle_exception
     */
    private static function extract_id(string $value): string {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9_-]{3,255}$/', $value)) {
            return $value;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host']) ||
                strtolower((string)$parts['host']) !== 'dashboard.pandavideo.com.br') {
            throw new moodle_exception('invalidurl', 'videoprogresssource_pandavideo');
        }

        if (preg_match('~/videos/([A-Za-z0-9_-]{3,255})~', (string)($parts['path'] ?? ''), $matches)) {
            return $matches[1];
        }

        throw new moodle_exception('invalidurl', 'videoprogresssource_pandavideo');
    }
}
