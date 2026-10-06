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

        $collection->add_database_table('local_video_bridge_session', [
            'contextid' => 'privacy:metadata:session:contextid',
            'component' => 'privacy:metadata:session:component',
            'itemid' => 'privacy:metadata:session:itemid',
            'source' => 'privacy:metadata:session:source',
            'mediahash' => 'privacy:metadata:session:mediahash',
            'userid' => 'privacy:metadata:session:userid',
            'sessionid' => 'privacy:metadata:session:sessionid',
            'level' => 'privacy:metadata:session:level',
            'startedat' => 'privacy:metadata:session:startedat',
            'endedat' => 'privacy:metadata:session:endedat',
            'duration' => 'privacy:metadata:session:duration',
            'watchtime' => 'privacy:metadata:session:watchtime',
            'plays' => 'privacy:metadata:session:plays',
            'pauses' => 'privacy:metadata:session:pauses',
            'seeks' => 'privacy:metadata:session:seeks',
            'replays' => 'privacy:metadata:session:replays',
            'skips' => 'privacy:metadata:session:skips',
            'dropoff' => 'privacy:metadata:session:dropoff',
            'maxposition' => 'privacy:metadata:session:maxposition',
            'speedavg' => 'privacy:metadata:session:speedavg',
            'ranges' => 'privacy:metadata:session:ranges',
            'pausepoints' => 'privacy:metadata:session:pausepoints',
            'skippoints' => 'privacy:metadata:session:skippoints',
            'replaypoints' => 'privacy:metadata:session:replaypoints',
            'rates' => 'privacy:metadata:session:rates',
            'continuousblocks' => 'privacy:metadata:session:continuousblocks',
            'inactivitygaps' => 'privacy:metadata:session:inactivitygaps',
            'events' => 'privacy:metadata:session:events',
            'timecreated' => 'privacy:metadata:session:timecreated',
            'timemodified' => 'privacy:metadata:session:timemodified',
        ], 'privacy:metadata:session');

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
        $contextlist->add_from_sql(
            "SELECT DISTINCT c.id
               FROM {context} c
               JOIN {local_video_bridge_session} s ON s.contextid = c.id
              WHERE s.userid = :sessionuserid",
            ['sessionuserid' => $userid]
        );
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
            if ($records) {
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

            $sessions = $DB->get_records(
                'local_video_bridge_session',
                ['contextid' => $context->id, 'userid' => $userid],
                'id ASC'
            );
            if ($sessions) {
                $sessionexport = [];
                foreach ($sessions as $session) {
                    $sessionexport[] = (object)[
                        'component' => $session->component,
                        'itemid' => $session->itemid,
                        'source' => $session->source,
                        'mediahash' => $session->mediahash,
                        'sessionid' => $session->sessionid,
                        'level' => $session->level,
                        'startedat' => transform::datetime($session->startedat),
                        'endedat' => $session->endedat ? transform::datetime($session->endedat) : null,
                        'duration' => $session->duration,
                        'watchtime' => $session->watchtime,
                        'plays' => $session->plays,
                        'pauses' => $session->pauses,
                        'seeks' => $session->seeks,
                        'replays' => $session->replays,
                        'skips' => $session->skips,
                        'dropoff' => $session->dropoff,
                        'maxposition' => $session->maxposition,
                        'speedavg' => $session->speedavg,
                        'ranges' => json_decode($session->ranges, true) ?: [],
                        'pausepoints' => json_decode($session->pausepoints, true) ?: [],
                        'skippoints' => json_decode($session->skippoints, true) ?: [],
                        'replaypoints' => json_decode($session->replaypoints, true) ?: [],
                        'rates' => json_decode($session->rates, true) ?: [],
                        'continuousblocks' => json_decode((string)($session->continuousblocks ?? ''), true) ?: [],
                        'inactivitygaps' => json_decode((string)($session->inactivitygaps ?? ''), true) ?: [],
                        'events' => json_decode((string)($session->events ?? ''), true) ?: [],
                        'timecreated' => transform::datetime($session->timecreated),
                        'timemodified' => transform::datetime($session->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:sessions', 'local_video_bridge')],
                    (object)['sessions' => $sessionexport]
                );
            }
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
        $DB->delete_records('local_video_bridge_session', ['contextid' => $context->id]);
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
            $DB->delete_records('local_video_bridge_session', [
                'contextid' => $context->id,
                'userid' => $userid,
            ]);
        }
    }
}
