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
 * English language strings for Video Bridge.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['browservideonotsupported'] = 'Your browser does not support HTML5 video playback.';
$string['captionpluginmissing'] = 'The caption source "{$a}" is not installed or is unavailable.';
$string['captionsources'] = 'Caption sources';
$string['captionsources_desc'] = 'Shared caption source adapters used by Moodle video plugins.';
$string['captiontoolmissing'] = 'The caption tool "{$a}" is not installed or is unavailable.';
$string['captiontools'] = 'Caption tools';
$string['captiontools_desc'] = 'Shared caption-processing tools used by Moodle video plugins.';
$string['invalidcaptionplugin'] = 'The caption source "{$a}" is invalid and was ignored.';
$string['invalidcaptiontool'] = 'The caption tool "{$a}" is invalid and was ignored.';
$string['invalidsourceplugin'] = 'The video source "{$a}" is invalid and was ignored.';
$string['invalidsubplugintype'] = 'Invalid Video Bridge subplugin type.';
$string['nosubplugins'] = 'No subplugins installed';
$string['nosubpluginshelp'] = 'Install at least one compatible Video Bridge subplugin to make it available to video plugins.';
$string['pluginadministration'] = 'Video Bridge administration';
$string['plugincomponent'] = 'Component';
$string['pluginname'] = 'Video Bridge';
$string['pluginrelease'] = 'Release version';
$string['pluginstatus'] = 'Status';
$string['pluginstatusready'] = 'Installed and up to date';
$string['pluginstatusupgrade'] = 'Requires installation or upgrade';
$string['pluginversion'] = 'Internal version';
$string['privacy:metadata'] = 'Video Bridge does not store personal data by itself. Consumer plugins and source subplugins may store configuration or files in their own activity contexts.';
$string['sourcepluginmissing'] = 'The video source "{$a}" is not installed or is unavailable.';
$string['sources'] = 'Video sources';
$string['sources_desc'] = 'Shared video source adapters used by Moodle video plugins.';
$string['subplugininuse'] = 'In use or required';
$string['subplugintype_videocaptionsource'] = 'Caption source';
$string['subplugintype_videocaptionsource_plural'] = 'Caption sources';
$string['subplugintype_videocaptiontool'] = 'Caption tool';
$string['subplugintype_videocaptiontool_plural'] = 'Caption tools';
$string['subplugintype_videoprogresssource'] = 'Video source';
$string['subplugintype_videoprogresssource_plural'] = 'Video sources';
$string['unknown'] = 'Not provided';

$string['aibridgemissing'] = 'local_ai_bridge is required to run AI caption tools.';
$string['captiontoolinputtoolarge'] = 'The caption tool input is too large.';
$string['emptycaptiontoolinput'] = 'Caption tool input cannot be empty.';
$string['invalidmindmap'] = 'The AI response is not a valid Mermaid mindmap.';
$string['invalidtoolwebvtt'] = 'The AI response is not valid WebVTT.';
