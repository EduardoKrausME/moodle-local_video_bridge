<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die;

/**
 * Serves protected uploaded videos owned by Video Bridge.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_video_bridge_pluginfile($course, $cm, context $context, string $filearea, array $args,
        bool $forcedownload, array $options = []): bool {
    if (!$context instanceof context_module || !$cm || $filearea !== 'video') {
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
