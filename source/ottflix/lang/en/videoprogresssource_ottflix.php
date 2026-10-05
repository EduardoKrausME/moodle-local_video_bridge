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

$string['baseurl'] = 'OTTFlix URL';
$string['baseurl_desc'] = 'Base URL of the OTTFlix installation used to request protected players.';
$string['configurationmissing'] = 'Configure the OTTFlix URL and API token before using this source.';
$string['invalidurl'] = 'Enter a valid OTTFlix asset identifier or link.';
$string['ottflixurl'] = 'OTTFlix link or identifier';
$string['playererror'] = 'OTTFlix could not return the player for this asset.';
$string['pluginname'] = 'OTTFlix';
$string['privacy:metadata:ottflix'] = 'OTTFlix receives user information required to issue the protected player.';
$string['privacy:metadata:ottflix:enrollment'] = 'The course-module identifier used as the enrollment reference.';
$string['privacy:metadata:ottflix:student_email'] = 'The learner email sent to the protected player service.';
$string['privacy:metadata:ottflix:student_name'] = 'The learner full name sent to the protected player service.';
$string['privacy:metadata:ottflix:userid'] = 'The Moodle user identifier used as a player safety reference.';
$string['token'] = 'OTTFlix API token';
$string['token_desc'] = 'Token sent in the Authorization header when Video Bridge requests the player.';
