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

defined('MOODLE_INTERNAL') || die;

$settings->add(new admin_setting_configtext(
    'videoprogresssource_ottflix/baseurl',
    get_string('baseurl', 'videoprogresssource_ottflix'),
    get_string('baseurl_desc', 'videoprogresssource_ottflix'),
    'https://app.ottflix.com.br/',
    PARAM_URL
));

$settings->add(new admin_setting_configpasswordunmask(
    'videoprogresssource_ottflix/token',
    get_string('token', 'videoprogresssource_ottflix'),
    get_string('token_desc', 'videoprogresssource_ottflix'),
    ''
));
