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

$string['aibridgemissing'] = 'local_ai_bridge is required to run AI caption tools.';
$string['browservideonotsupported'] = 'Your browser does not support HTML5 video playback.';
$string['captionpluginmissing'] = 'The caption source "{$a}" is not installed or is unavailable.';
$string['captionsources'] = 'Caption sources';
$string['captionsources_desc'] = 'Shared caption source adapters used by Moodle video plugins.';
$string['captiontoolinputtoolarge'] = 'The caption tool input is too large.';
$string['captiontoolmissing'] = 'The caption tool "{$a}" is not installed or is unavailable.';
$string['captiontools'] = 'Caption tools';
$string['captiontools_desc'] = 'Shared caption-processing tools used by Moodle video plugins.';
$string['emptycaptiontoolinput'] = 'Caption tool input cannot be empty.';
$string['invalidcaptionplugin'] = 'The caption source "{$a}" is invalid and was ignored.';
$string['invalidcaptiontool'] = 'The caption tool "{$a}" is invalid and was ignored.';
$string['invalidmindmap'] = 'The AI response is not a valid Mermaid mindmap.';
$string['invalidsourceplugin'] = 'The video source "{$a}" is invalid and was ignored.';
$string['invalidsubplugintype'] = 'Invalid Video Bridge subplugin type.';
$string['invalidtoolwebvtt'] = 'The AI response is not valid WebVTT.';
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
$string['privacy:metadata:progress'] = 'Video Bridge stores each learner\'s video playback progress so every consumer video plugin can reuse the same viewing map.';
$string['privacy:metadata:progress:component'] = 'The Moodle component using the video.';
$string['privacy:metadata:progress:contextid'] = 'The module context in which the video was shown.';
$string['privacy:metadata:progress:currenttime'] = 'The last known playback position.';
$string['privacy:metadata:progress:duration'] = 'The known video duration.';
$string['privacy:metadata:progress:itemid'] = 'The consumer activity instance id.';
$string['privacy:metadata:progress:map'] = 'The normalized list of video buckets watched by the learner.';
$string['privacy:metadata:progress:mediahash'] = 'A non-reversible hash identifying the configured media inside the activity.';
$string['privacy:metadata:progress:percent'] = 'The percentage of distinct video buckets watched.';
$string['privacy:metadata:progress:source'] = 'The Video Bridge source used to play the media.';
$string['privacy:metadata:progress:timecreated'] = 'When the progress record was created.';
$string['privacy:metadata:progress:timemodified'] = 'When the progress record was last updated.';
$string['privacy:metadata:progress:userid'] = 'The user whose playback progress is stored.';

$string['privacy:metadata:session'] = 'Video Bridge stores compact playback session telemetry for analytics consumers.';
$string['privacy:metadata:session:component'] = 'The Moodle component using the video.';
$string['privacy:metadata:session:contextid'] = 'The module context in which the video was shown.';
$string['privacy:metadata:session:endedat'] = 'When the playback session ended.';
$string['privacy:metadata:session:itemid'] = 'The consumer activity instance id.';
$string['privacy:metadata:session:mediahash'] = 'The non-reversible media identifier.';
$string['privacy:metadata:session:ranges'] = 'Compact watched ranges when detailed telemetry is enabled.';
$string['privacy:metadata:session:sessionid'] = 'A random identifier for the playback session.';
$string['privacy:metadata:session:startedat'] = 'When the playback session started.';
$string['privacy:metadata:session:timemodified'] = 'When the compact session snapshot was last updated.';
$string['privacy:metadata:session:userid'] = 'The user whose playback session is stored.';
$string['privacy:metadata:session:watchtime'] = 'Estimated real playback time in the session.';
$string['privacy:sessions'] = 'Video playback sessions';

$string['privacy:progress'] = 'Video playback progress';
$string['progressmap'] = 'Your viewing map';
$string['progresssaveerror'] = 'Video progress could not be saved.';
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

$string['eventanalyticsupdated'] = 'Video analytics updated';
