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
 * Language strings for uploaded captions.
 *
 * @package   videocaptionsource_upload
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['captionfiles'] = 'Caption files';
$string['captionfiles_help'] = 'Upload one or more WebVTT (.vtt) or SubRip (.srt) files. SRT files are converted to WebVTT. Use names such as pt-BR.vtt, pt-BR__Portuguese.vtt or pt-BR__Portuguese.default.vtt to define track language, label and the default track.';
$string['captionlabel'] = 'Caption';
$string['filetoolarge'] = 'Each caption file must be 5 MB or smaller.';
$string['invalidextension'] = 'Only VTT and SRT caption files are accepted.';
$string['invalidvtt'] = 'The caption file does not contain valid WebVTT cues.';
$string['pluginname'] = 'Upload captions';
$string['privacy:metadata'] = 'The upload caption source stores caption files in the consumer activity context and does not store personal data independently.';
