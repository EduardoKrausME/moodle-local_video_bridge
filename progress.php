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
 * Lightweight progress endpoint used by fetch() and sendBeacon().
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_video_bridge\progress\manager;

header('Content-Type: application/json; charset=utf-8');

try {
    require_sesskey();

    if (!isloggedin() || isguestuser()) {
        throw new moodle_exception('requireloginerror', 'error');
    }

    $contextid = required_param('contextid', PARAM_INT);
    $component = required_param('component', PARAM_COMPONENT);
    $itemid = required_param('itemid', PARAM_INT);
    $source = required_param('source', PARAM_PLUGIN);
    $mediahash = required_param('mediahash', PARAM_ALPHANUM);
    $currenttime = required_param('currenttime', PARAM_INT);
    $duration = required_param('duration', PARAM_INT);
    $rawbuckets = optional_param('buckets', '[]', PARAM_RAW);

    if (!preg_match('/^[a-f0-9]{64}$/', $mediahash)) {
        throw new invalid_parameter_exception('Invalid media hash.');
    }

    $context = context::instance_by_id($contextid, MUST_EXIST);
    if (!$context instanceof context_module) {
        throw new invalid_parameter_exception('Progress requires a module context.');
    }

    $cm = get_coursemodule_from_id(null, $context->instanceid, 0, false, MUST_EXIST);
    require_login($cm->course, false, $cm);

    $expectedcomponent = 'mod_' . $cm->modname;
    if ($component !== $expectedcomponent || $itemid !== (int)$cm->instance) {
        throw new invalid_parameter_exception('Progress consumer does not match the module context.');
    }

    $buckets = json_decode($rawbuckets, true);
    if (!is_array($buckets) || count($buckets) > manager::MAX_BUCKETS) {
        throw new invalid_parameter_exception('Invalid progress bucket list.');
    }

    $progress = manager::save(
        $context->id,
        $component,
        $itemid,
        $source,
        $mediahash,
        (int)$USER->id,
        $currenttime,
        $duration,
        $buckets
    );

    echo json_encode([
        'success' => true,
        'currenttime' => (int)$progress->currenttime,
        'duration' => (int)$progress->duration,
        'percent' => (int)$progress->percent,
        'map' => json_decode($progress->map, true) ?: [],
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
    ]);
}
