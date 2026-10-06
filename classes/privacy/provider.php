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
 * Privacy provider for Video Bridge.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Describes and manages the per-user playback progress stored by Video Bridge.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describes stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_video_bridge_progress', [
            'contextid' => 'privacy:metadata:progress:contextid',
            'component' => 'privacy:metadata:progress:component',
            'itemid' => 'privacy:metadata:progress:itemid',
            'source' => 'privacy:metadata:progress:source',
            'mediahash' => 'privacy:metadata:progress:mediahash',
            'userid' => 'privacy:metadata:progress:userid',
            'currenttime' => 'privacy:metadata:progress:currenttime',
            'duration' => 'privacy:metadata:progress:duration',
            'percent' => 'privacy:metadata:progress:percent',
            'map' => 'privacy:metadata:progress:map',
            'timecreated' => 'privacy:metadata:progress:timecreated',
            'timemodified' => 'privacy:metadata:progress:timemodified',
        ], 'privacy:metadata:progress');

        return $collection;
    }

    /**
     * Returns contexts containing progress for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
                  JOIN {local_video_bridge_progress} p ON p.contextid = c.id
                 WHERE p.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['userid' => $userid]);
        return $contextlist;
    }

    /**
     * Exports the user's progress in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $records = $DB->get_records(
                'local_video_bridge_progress',
                ['contextid' => $context->id, 'userid' => $userid],
                'id ASC'
            );
            if (!$records) {
                continue;
            }

            $export = [];
            foreach ($records as $record) {
                $export[] = (object)[
                    'component' => $record->component,
                    'itemid' => $record->itemid,
                    'source' => $record->source,
                    'currenttime' => $record->currenttime,
                    'duration' => $record->duration,
                    'percent' => $record->percent,
                    'map' => json_decode($record->map, true) ?: [],
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:progress', 'local_video_bridge')],
                (object)['progress' => $export]
            );
        }
    }

    /**
     * Deletes progress for all users in one context.
     *
     * @param context $context Context being deleted.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        $DB->delete_records('local_video_bridge_progress', ['contextid' => $context->id]);
    }

    /**
     * Deletes progress for one user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('local_video_bridge_progress', [
                'contextid' => $context->id,
                'userid' => $userid,
            ]);
        }
    }
}
