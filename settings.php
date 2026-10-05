<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings->add(new admin_setting_heading(
        'local_video_bridge/sources',
        get_string('sources', 'local_video_bridge'),
        get_string('sources_desc', 'local_video_bridge')
    ));
}
