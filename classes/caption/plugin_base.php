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
 * Base contract for shared caption sources.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\caption;

use context_module;
use MoodleQuickForm;
use stdClass;

/**
 * Defines the contract implemented by every shared caption source.
 */
abstract class plugin_base {
    /**
     * Returns the plugin sort order.
     *
     * @return int Return value.
     */
    public function get_sort_order(): int {
        return 100;
    }

    /**
     * Returns the display name.
     *
     * @return string Return value.
     */
    abstract public function get_name(): string;

    /**
     * Adds caption source form elements.
     *
     * @param MoodleQuickForm $mform Moodle form instance.
     * @param string $sourcefield Caption source field name.
     * @return void Return value.
     */
    abstract public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void;

    /**
     * Validates caption source form data.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Return value.
     */
    abstract public function validation(array $data, array $files): array;

    /**
     * Builds normalized caption source configuration.
     *
     * @param stdClass $data Submitted form data.
     * @return array Return value.
     */
    abstract public function build_config(stdClass $data): array;

    /**
     * Prepares caption values for the edit form.
     *
     * @param array $defaultvalues Form default values.
     * @param context_module $context Module context.
     * @return void Return value.
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
    }

    /**
     * Saves files owned by the selected caption source.
     *
     * @param stdClass $data Submitted form data.
     * @param context_module $context Module context.
     * @return void Return value.
     */
    public function save_files(stdClass $data, context_module $context): void {
    }

    /**
     * Deletes files owned by caption sources.
     *
     * @param context_module $context Module context.
     * @return void Return value.
     */
    public function delete_files(context_module $context): void {
    }

    /**
     * Prepares caption state for one media item.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return void
     */
    public function prepare_media_form_data(
        array &$defaultvalues,
        context_module $context,
        int $mediaid
    ): void {
        $this->prepare_form_data($defaultvalues, $context);
    }

    /**
     * Saves caption files for one media item.
     *
     * @param stdClass $data Form data.
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return void
     */
    public function save_media_files(stdClass $data, context_module $context, int $mediaid): void {
        $this->save_files($data, $context);
    }

    /**
     * Deletes caption files for one media item.
     *
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return void
     */
    public function delete_media_files(context_module $context, int $mediaid): void {
        $this->delete_files($context);
    }

    /**
     * Returns normalized tracks for one media item.
     *
     * @param stdClass $activity Provider-compatible media record.
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return array
     */
    public function get_tracks_for_media(
        stdClass $activity,
        context_module $context,
        int $mediaid
    ): array {
        return $this->get_tracks($activity, $context);
    }

    /**
     * Returns normalized browser-ready caption tracks.
     *
     * @param stdClass $activity Activity record.
     * @param context_module $context Module context.
     * @return array Return value.
     */
    abstract public function get_tracks(stdClass $activity, context_module $context): array;

    /**
     * Decodes normalized caption source configuration.
     *
     * @param stdClass $activity Activity record.
     * @return array Return value.
     */
    final protected function decode_config(stdClass $activity): array {
        $raw = trim((string)($activity->captionconfig ?? ''));
        if ($raw === '') {
            return [];
        }

        $config = json_decode($raw, true);
        return is_array($config) ? $config : [];
    }
}
