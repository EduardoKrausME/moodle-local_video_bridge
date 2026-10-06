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
 * Library callbacks for Video Bridge.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves protected uploaded videos and captions owned by Video Bridge.
 *
 * @package local_video_bridge
 * @param stdClass $course Course record.
 * @param stdClass|null $cm Course-module record.
 * @param context $context File context.
 * @param string $filearea File area.
 * @param array $args Remaining pluginfile path arguments.
 * @param bool $forcedownload Whether the file should be downloaded.
 * @param array $options Additional file serving options.
 * @return bool
 */
function local_video_bridge_pluginfile($course, $cm, context $context, string $filearea, array $args,
        bool $forcedownload, array $options = []): bool {
    if (!$context instanceof context_module || !$cm || !in_array($filearea, ['video', 'caption'], true)) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    $itemid = (int)array_shift($args);
    if ($itemid !== 0 || !$args) {
        send_file_not_found();
    }

    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'local_video_bridge',
        'video',
        0,
        $filepath,
        $filename
    );

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}
