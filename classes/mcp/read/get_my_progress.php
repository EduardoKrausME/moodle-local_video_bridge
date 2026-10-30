<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Authenticated learner's own Video Bridge progress.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\mcp\read;

use context;
use context_module;
use local_mcp\exception\api_exception;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;

/**
 * Read provider-independent progress in a visible course activity.
 */
final class get_my_progress extends base_tool {
    /**
     * Return the learner progress tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'video_bridge_get_my_progress';
    }

    /** @return string */
    public function get_title(): string {
        return 'Video Bridge: my viewing progress';
    }

    /** @return string */
    public function get_description(): string {
        return 'Read only the authenticated learner\'s own video progress in a visible activity, across all video sources.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'moodle/course:view';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema([
            'cmid' => ['type' => 'integer', 'minimum' => 1],
        ], ['cmid']);
    }

    /**
     * @param array $arguments
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_module::instance((int)$arguments['cmid'], MUST_EXIST);
    }

    /**
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;

        $cmid = (int)$arguments['cmid'];
        $cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
        $modinfo = get_fast_modinfo($cm->course, $identity->userid);
        if (!$modinfo->get_cm($cmid)->uservisible) {
            throw new api_exception('permission_denied', 403);
        }

        $context = context_module::instance($cmid);
        $records = $DB->get_records(
            'local_video_bridge_progress',
            ['contextid' => $context->id, 'userid' => $identity->userid],
            'timemodified DESC, id DESC',
            'id, component, itemid, mediahash, percent, duration, currenttime, timemodified',
            0,
            100
        );
        $progress = [];
        foreach ($records as $record) {
            $progress[] = [
                'component' => (string)$record->component,
                'itemid' => (int)$record->itemid,
                'mediahash' => (string)$record->mediahash,
                'percent' => (float)$record->percent,
                'duration' => (float)$record->duration,
                'currenttime' => (float)$record->currenttime,
                'timemodified' => (int)$record->timemodified,
            ];
        }
        return ['cmid' => $cmid, 'userid' => $identity->userid, 'progress' => $progress];
    }
}
