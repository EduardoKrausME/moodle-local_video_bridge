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
 * @package   videoprogresssource_ottflix
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videoprogresssource_ottflix;

use context_module;
use curl;
use local_video_bridge\source\plugin_base;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * OTTFlix video source.
 */
class plugin extends plugin_base {
    /**
     * Returns the source name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'videoprogresssource_ottflix');
    }

    /**
     * Adds the OTTFlix link or identifier field.
     *
     * @param MoodleQuickForm $mform Activity form.
     * @param string $sourcefield Source selector field.
     * @return void
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void {
        $mform->addElement('text', 'ottflixurl', get_string('ottflixurl', 'videoprogresssource_ottflix'),
            ['size' => 80]);
        $mform->setType('ottflixurl', PARAM_TEXT);
        $mform->hideIf('ottflixurl', $sourcefield, 'neq', 'ottflix');
    }

    /**
     * Validates an OTTFlix identifier or link.
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
            return ['ottflixurl' => $exception->getMessage()];
        }
    }

    /**
     * Stores only the stable OTTFlix asset identifier.
     *
     * @param stdClass $data Submitted activity data.
     * @return array
     */
    public function build_config(stdClass $data): array {
        return ['id' => self::extract_id(trim((string)($data->ottflixurl ?? '')))];
    }

    /**
     * Returns the legacy OTTFlix identifier.
     *
     * @param array $config Normalized configuration.
     * @return string
     */
    public function get_legacy_value(array $config): string {
        return (string)($config['id'] ?? '');
    }

    /**
     * Restores the editable OTTFlix identifier.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Activity context.
     * @return void
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $config = $this->decode_config((object)$defaultvalues);
        $defaultvalues['ottflixurl'] = (string)($config['id'] ?? '');
    }

    /**
     * Requests trusted player HTML from the configured OTTFlix installation.
     *
     * @param stdClass $activity Activity record.
     * @param context_module $context Activity context.
     * @return array
     * @throws moodle_exception
     */
    public function get_player_config(stdClass $activity, context_module $context): array {
        global $CFG, $USER;

        $sourceconfig = $this->decode_config($activity);
        $identifier = (string)($sourceconfig['id'] ?? '');
        $config = get_config('videoprogresssource_ottflix');

        if (empty($config->baseurl) || empty($config->token)) {
            throw new moodle_exception('configurationmissing', 'videoprogresssource_ottflix');
        }

        $payload = [
            'enrollment' => $context->instanceid,
            'student_name' => fullname($USER),
            'student_email' => $USER->email,
            'safetyplayer' => $USER->id,
        ];

        $baseurl = rtrim((string)$config->baseurl, '/') . '/';
        $endpoint = $baseurl . 'api/v1/assets/' . rawurlencode($identifier) . '/player/?' .
            http_build_query($payload, '', '&');

        $curl = new curl();
        $version = moodle_major_version();
        $curl->setopt([
            'CURLOPT_HTTPHEADER' => ['authorization:' . $config->token],
            'CURLOPT_USERAGENT' => "Mozilla/5.0 (Moodle; {$version}) (+{$CFG->wwwroot})",
        ]);
        $html = $curl->get($endpoint);
        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);

        if ($status < 200 || $status >= 300 || !is_string($html) || trim($html) === '') {
            throw new moodle_exception('playererror', 'videoprogresssource_ottflix');
        }

        $origins = [];
        if (preg_match_all('/\bsrc=["\'](https?:\/\/[^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $parts = parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
                if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
                    continue;
                }
                $origin = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
                if (!empty($parts['port'])) {
                    $origin .= ':' . (int)$parts['port'];
                }
                $origins[$origin] = $origin;
            }
        }

        return [
            'html' => $html,
            'identifier' => $identifier,
            'origins' => array_values($origins),
        ];
    }

    /**
     * Returns OTTFlix capabilities.
     *
     * @return array
     */
    public function get_capabilities(): array {
        return [
            'tracking' => true,
            'seeking' => false,
            'playbackcontrol' => false,
            'playbackrate' => false,
        ];
    }

    /**
     * OTTFlix owns its visual presentation.
     *
     * @return bool
     */
    public function supports_poster(): bool {
        return false;
    }

    /**
     * OTTFlix manages captions remotely.
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
        return 'videoprogresssource_ottflix/player';
    }

    /**
     * Returns the player adapter.
     *
     * @return string
     */
    public function get_amd_module(): string {
        return 'videoprogresssource_ottflix/player';
    }

    /**
     * Converts an older link or identifier.
     *
     * @param string $legacyvalue Legacy value.
     * @return array
     */
    protected function get_legacy_config(string $legacyvalue): array {
        return ['id' => self::extract_id($legacyvalue)];
    }

    /**
     * Extracts an OTTFlix asset identifier from a link or raw identifier.
     *
     * @param string $value Link or asset identifier.
     * @return string
     * @throws moodle_exception
     */
    private static function extract_id(string $value): string {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9_-]{3,255}$/', $value)) {
            return $value;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_ottflix');
        }

        $segments = array_values(array_filter(explode('/', trim((string)($parts['path'] ?? ''), '/'))));
        for ($index = count($segments) - 1; $index >= 0; $index--) {
            $segment = $segments[$index];
            if (in_array(strtolower($segment), ['share', 'assets', 'assetsh5p', 'video', 'player'], true)) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_-]{3,255}$/', $segment)) {
                return $segment;
            }
        }

        throw new moodle_exception('invalidurl', 'videoprogresssource_ottflix');
    }
}
