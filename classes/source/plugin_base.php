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
 * Base contract for shared video sources.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\source;

use coding_exception;
use context_module;
use moodle_url;
use MoodleQuickForm;
use stdClass;
use stored_file;

/**
 * Defines the contract implemented by every shared video source.
 */
abstract class plugin_base {
    /**
     * Returns the source order used in selectors.
     *
     * @return int Lower values are displayed first.
     */
    public function get_sort_order(): int {
        return 100;
    }

    /**
     * Returns the localized source name.
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Adds source-specific form fields.
     *
     * @param MoodleQuickForm $mform Activity form.
     * @param string $sourcefield Source selector field.
     * @return void
     */
    abstract public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void;

    /**
     * Validates source-specific form data.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    abstract public function validation(array $data, array $files): array;

    /**
     * Builds normalized source configuration.
     *
     * @param stdClass $data Submitted activity data.
     * @return array
     */
    abstract public function build_config(stdClass $data): array;

    /**
     * Returns the legacy value kept by consumers that still have one.
     *
     * @param array $config Normalized source configuration.
     * @return string
     */
    abstract public function get_legacy_value(array $config): string;

    /**
     * Returns browser-safe player configuration.
     *
     * @param stdClass $activity Provider-compatible activity data.
     * @param context_module $context Activity module context.
     * @return array
     */
    abstract public function get_player_config(stdClass $activity, context_module $context): array;

    /**
     * Returns the source Mustache template identifier.
     *
     * @return string
     */
    abstract public function get_player_template(): string;

    /**
     * Returns the source AMD adapter identifier.
     *
     * @return string
     */
    abstract public function get_amd_module(): string;

    /**
     * Prepares stored values and files for an edit form.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Activity module context.
     * @return void
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
    }

    /**
     * Persists source-owned draft files.
     *
     * @param stdClass $data Saved activity data.
     * @param context_module $context Activity module context.
     * @return void
     */
    public function save_files(stdClass $data, context_module $context): void {
    }

    /**
     * Removes source-owned files.
     *
     * @param context_module $context Activity module context.
     * @return void
     */
    public function delete_files(context_module $context): void {
    }

    /**
     * Reports whether the consumer may offer a custom poster.
     *
     * @return bool
     */
    public function supports_poster(): bool {
        return true;
    }

    /**
     * Reports whether the consumer may offer uploaded captions.
     *
     * @return bool
     */
    public function supports_uploaded_captions(): bool {
        return true;
    }

    /**
     * Returns a server-readable file for transcription when available.
     *
     * @param context_module $context Activity module context.
     * @return stored_file|null
     */
    public function get_transcription_file(context_module $context): stored_file|null {
        return null;
    }

    /**
     * Decodes normalized configuration and falls back to the legacy provider value.
     *
     * @param stdClass $activity Provider-compatible activity data.
     * @return array
     */
    final protected function decode_config(stdClass $activity): array {
        $raw = trim((string)($activity->sourceconfig ?? ''));
        if ($raw !== '') {
            $config = json_decode($raw, true);
            if (is_array($config)) {
                return $config;
            }
        }
        return $this->get_legacy_config((string)($activity->videourl ?? ''));
    }

    /**
     * Converts a legacy source value to normalized configuration.
     *
     * @param string $legacyvalue Historical source value.
     * @return array
     */
    abstract protected function get_legacy_config(string $legacyvalue): array;

    /**
     * Returns a protected URL for the first file in a File API area.
     *
     * @param context_module $context Activity module context.
     * @param string $component File component.
     * @param string $filearea File area.
     * @param int $itemid File item id.
     * @return string Protected pluginfile URL or an empty string.
     * @throws coding_exception
     */
    final protected function first_file_url(context_module $context, string $component, string $filearea,
            int $itemid = 0): string {
        $files = get_file_storage()->get_area_files(
            $context->id,
            $component,
            $filearea,
            $itemid,
            'filename',
            false
        );
        if (!$files) {
            return '';
        }

        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $context->id,
            $component,
            $filearea,
            $itemid,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }
}
