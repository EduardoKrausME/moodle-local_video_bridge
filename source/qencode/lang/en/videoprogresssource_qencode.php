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
 * Language strings for Qencode.
 *
 * @package   videoprogresssource_qencode
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['invalidextension'] = 'Enter a Qencode playback URL ending in M3U8, MP4, WebM, OGV, M4V or MOV.';
$string['invalidurl'] = 'Enter a valid HTTPS Qencode playback URL.';
$string['pluginname'] = 'Qencode';
$string['privacy:metadata'] = 'The Qencode source stores no personal data independently of the consumer activity.';
$string['qencodeurl'] = 'Qencode playback URL';
$string['qencodeurl_help'] = 'Paste the playback URL returned by Qencode. HLS (.m3u8) and browser-compatible video files are supported. Qencode custom playback domains and signed query parameters are preserved.';
