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
 * Uploaded caption source.
 *
 * @package   videocaptionsource_upload
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videocaptionsource_upload;

use context_module;
use context_user;
use local_video_bridge\caption\plugin_base;
use moodle_exception;
use moodle_url;
use MoodleQuickForm;
use stdClass;

/**
 * Stores VTT/SRT caption files in Moodle and exposes normalized WebVTT tracks.
 */
class plugin extends plugin_base {
    /** @var int Maximum size of each uploaded caption file. */
    private const MAX_BYTES = 5242880;

    /**
     * Returns the plugin sort order.
     * @return int Return value.
     */
    public function get_sort_order(): int {
        return 10;
    }

    /**
     * Returns the display name.
     * @return string Return value.
     */
    public function get_name(): string {
        return get_string('pluginname', 'videocaptionsource_upload');
    }

    /**
     * Adds plugin-specific form elements.
     * @param MoodleQuickForm $mform mform.
     * @param string $sourcefield sourcefield.
     * @return void Return value.
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void {
        $mform->addElement('filemanager', 'captionfiles',
            get_string('captionfiles', 'videocaptionsource_upload'), null, [
                'subdirs' => 0,
                'maxfiles' => 20,
                'maxbytes' => self::MAX_BYTES,
                'accepted_types' => ['.vtt', '.srt'],
            ]);
        $mform->addHelpButton('captionfiles', 'captionfiles', 'videocaptionsource_upload');
        $mform->hideIf('captionfiles', $sourcefield, 'neq', 'upload');
    }

    /**
     * Validates submitted form data.
     * @param array $data data.
     * @param array $files files.
     * @return array Return value.
     */
    public function validation(array $data, array $files): array {
        global $USER;

        $draftitemid = (int)($data['captionfiles'] ?? 0);
        if ($draftitemid < 1) {
            return ['captionfiles' => get_string('required')];
        }

        $draftfiles = get_file_storage()->get_area_files(
            context_user::instance((int)$USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'filename',
            false
        );
        if (!$draftfiles) {
            return ['captionfiles' => get_string('required')];
        }

        try {
            foreach ($draftfiles as $file) {
                if ($file->get_filesize() > self::MAX_BYTES) {
                    throw new moodle_exception('filetoolarge', 'videocaptionsource_upload');
                }

                $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                if (!in_array($extension, ['vtt', 'srt'], true)) {
                    throw new moodle_exception('invalidextension', 'videocaptionsource_upload');
                }

                $content = $file->get_content();
                if ($extension === 'srt') {
                    $content = self::convert_srt_to_vtt($content);
                }
                self::validate_webvtt($content);
            }
        } catch (moodle_exception $exception) {
            return ['captionfiles' => $exception->getMessage()];
        }

        return [];
    }

    /**
     * Builds normalized plugin configuration.
     * @param stdClass $data data.
     * @return array Return value.
     */
    public function build_config(stdClass $data): array {
        return ['storage' => 'moodle'];
    }

    /**
     * Prepares values used by the edit form.
     * @param array $defaultvalues defaultvalues.
     * @param context_module $context context.
     * @return void Return value.
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $draftitemid = file_get_submitted_draft_itemid('captionfiles');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'local_video_bridge',
            'caption',
            0,
            [
                'subdirs' => 0,
                'maxfiles' => 20,
                'maxbytes' => self::MAX_BYTES,
                'accepted_types' => ['.vtt', '.srt'],
            ]
        );
        $defaultvalues['captionfiles'] = $draftitemid;
    }

    /**
     * Saves files owned by this caption source.
     * @param stdClass $data data.
     * @param context_module $context context.
     * @return void Return value.
     */
    public function save_files(stdClass $data, context_module $context): void {
        global $USER;

        if (empty($data->captionfiles)) {
            return;
        }

        $fs = get_file_storage();
        $draftfiles = $fs->get_area_files(
            context_user::instance((int)$USER->id)->id,
            'user',
            'draft',
            (int)$data->captionfiles,
            'filename',
            false
        );

        if (!$draftfiles) {
            return;
        }

        $prepared = [];
        foreach ($draftfiles as $file) {
            if ($file->get_filesize() > self::MAX_BYTES) {
                throw new moodle_exception('filetoolarge', 'videocaptionsource_upload');
            }

            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!in_array($extension, ['vtt', 'srt'], true)) {
                throw new moodle_exception('invalidextension', 'videocaptionsource_upload');
            }

            $content = $file->get_content();
            if ($extension === 'srt') {
                $content = self::convert_srt_to_vtt($content);
            }
            self::validate_webvtt($content);

            $basename = pathinfo($file->get_filename(), PATHINFO_FILENAME);
            $filename = clean_filename($basename . '.vtt');
            $prepared[$filename] = $content;
        }

        $fs->delete_area_files($context->id, 'local_video_bridge', 'caption', 0);
        foreach ($prepared as $filename => $content) {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'local_video_bridge',
                'filearea' => 'caption',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
            ], $content);
        }
    }

    /**
     * Deletes files owned by this caption source.
     * @param context_module $context context.
     * @return void Return value.
     */
    public function delete_files(context_module $context): void {
        get_file_storage()->delete_area_files($context->id, 'local_video_bridge', 'caption', 0);
    }

    /**
     * Returns browser-ready caption tracks.
     * @param stdClass $activity activity.
     * @param context_module $context context.
     * @return array Return value.
     */
    public function get_tracks(stdClass $activity, context_module $context): array {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_video_bridge',
            'caption',
            0,
            'filename',
            false
        );

        $tracks = [];
        $explicitdefault = false;
        foreach ($files as $file) {
            $metadata = self::metadata_from_filename($file->get_filename());
            if ($metadata['isdefault'] && $explicitdefault) {
                $metadata['isdefault'] = false;
            } else if ($metadata['isdefault']) {
                $explicitdefault = true;
            }

            $metadata['url'] = moodle_url::make_pluginfile_url(
                $context->id,
                'local_video_bridge',
                'caption',
                0,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $tracks[] = $metadata;
        }

        if ($tracks && !$explicitdefault) {
            $tracks[0]['isdefault'] = true;
        }

        return $tracks;
    }

    /**
     * Prepares one media item's uploaded captions.
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
        $draftitemid = file_get_submitted_draft_itemid('captionfiles');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'local_video_bridge',
            'caption',
            $mediaid,
            [
                'subdirs' => 0,
                'maxfiles' => 20,
                'maxbytes' => self::MAX_BYTES,
                'accepted_types' => ['.vtt', '.srt'],
            ]
        );
        $defaultvalues['captionfiles'] = $draftitemid;
    }

    /**
     * Saves one media item's uploaded captions.
     *
     * @param stdClass $data Form data.
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return void
     */
    public function save_media_files(stdClass $data, context_module $context, int $mediaid): void {
        global $USER;

        if (empty($data->captionfiles)) {
            return;
        }

        $fs = get_file_storage();
        $draftfiles = $fs->get_area_files(
            context_user::instance((int)$USER->id)->id,
            'user',
            'draft',
            (int)$data->captionfiles,
            'filename',
            false
        );
        if (!$draftfiles) {
            return;
        }

        $prepared = [];
        foreach ($draftfiles as $file) {
            if ($file->get_filesize() > self::MAX_BYTES) {
                throw new moodle_exception('filetoolarge', 'videocaptionsource_upload');
            }
            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!in_array($extension, ['vtt', 'srt'], true)) {
                throw new moodle_exception('invalidextension', 'videocaptionsource_upload');
            }
            $content = $file->get_content();
            if ($extension === 'srt') {
                $content = self::convert_srt_to_vtt($content);
            }
            self::validate_webvtt($content);
            $basename = pathinfo($file->get_filename(), PATHINFO_FILENAME);
            $prepared[clean_filename($basename . '.vtt')] = $content;
        }

        $fs->delete_area_files($context->id, 'local_video_bridge', 'caption', $mediaid);
        foreach ($prepared as $filename => $content) {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'local_video_bridge',
                'filearea' => 'caption',
                'itemid' => $mediaid,
                'filepath' => '/',
                'filename' => $filename,
            ], $content);
        }
    }

    /**
     * Deletes one media item's uploaded captions.
     *
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return void
     */
    public function delete_media_files(context_module $context, int $mediaid): void {
        get_file_storage()->delete_area_files($context->id, 'local_video_bridge', 'caption', $mediaid);
    }

    /**
     * Returns uploaded caption tracks for one media item.
     *
     * @param stdClass $activity Provider-compatible media record.
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return array Browser-ready tracks.
     */
    public function get_tracks_for_media(
        stdClass $activity,
        context_module $context,
        int $mediaid
    ): array {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_video_bridge',
            'caption',
            $mediaid,
            'filename',
            false
        );

        $tracks = [];
        $explicitdefault = false;
        foreach ($files as $file) {
            $metadata = self::metadata_from_filename($file->get_filename());
            if ($metadata['isdefault'] && $explicitdefault) {
                $metadata['isdefault'] = false;
            } else if ($metadata['isdefault']) {
                $explicitdefault = true;
            }

            $metadata['url'] = moodle_url::make_pluginfile_url(
                $context->id,
                'local_video_bridge',
                'caption',
                $mediaid,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $tracks[] = $metadata;
        }

        if ($tracks && !$explicitdefault) {
            $tracks[0]['isdefault'] = true;
        }
        return $tracks;
    }

    /**
     * Extracts caption metadata from a filename.
     * @param string $filename filename.
     * @return array Return value.
     */
    private static function metadata_from_filename(string $filename): array {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $isdefault = false;

        if (preg_match('/(?:[._-]default)$/i', $stem)) {
            $isdefault = true;
            $stem = preg_replace('/(?:[._-]default)$/i', '', $stem);
        }

        $language = 'und';
        $label = trim($stem);

        if (preg_match('/^([A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*)(?:__(.+))?$/u', $stem, $matches)) {
            $language = str_replace('_', '-', $matches[1]);
            if (!empty($matches[2])) {
                $label = trim($matches[2]);
            } else {
                $label = $language;
            }
        }

        if ($label === '') {
            $label = $language === 'und' ? get_string('captionlabel', 'videocaptionsource_upload') : $language;
        }

        return [
            'language' => $language,
            'label' => $label,
            'isdefault' => $isdefault,
        ];
    }

    /**
     * Converts SRT caption content to WebVTT.
     * @param string $content content.
     * @return string Return value.
     */
    private static function convert_srt_to_vtt(string $content): string {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", trim($content));

        foreach ($lines as &$line) {
            if (str_contains($line, '-->')) {
                $line = preg_replace('/(\d{1,2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $line);
            }
        }
        unset($line);

        return "WEBVTT\n\n" . implode("\n", $lines) . "\n";
    }

    /**
     * Validates WebVTT caption content.
     * @param string $content content.
     * @return void Return value.
     */
    private static function validate_webvtt(string $content): void {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        if (!preg_match('/^WEBVTT(?:[ \t].*)?\n/i', $content)) {
            throw new moodle_exception('invalidvtt', 'videocaptionsource_upload');
        }

        if (!preg_match(
            '/(?:^|\n)(?:\d{2}:)?\d{2}:\d{2}\.\d{3}[ \t]+-->[ \t]+(?:\d{2}:)?\d{2}:\d{2}\.\d{3}/m',
            $content
        )) {
            throw new moodle_exception('invalidvtt', 'videocaptionsource_upload');
        }
    }
}
