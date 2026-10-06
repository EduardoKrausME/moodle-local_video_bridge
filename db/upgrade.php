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
 * Upgrade steps for Video Bridge.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executes Video Bridge upgrade steps.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_local_video_bridge_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026100508) {
        // Move uploads created while the source layer still belonged to mod_videoprogress.
        $legacyfiles = $DB->get_records('files', [
            'component' => 'mod_videoprogress',
            'filearea' => 'video',
        ]);

        foreach ($legacyfiles as $legacyfile) {
            $existing = $DB->get_record('files', [
                'contextid' => $legacyfile->contextid,
                'component' => 'local_video_bridge',
                'filearea' => 'video',
                'itemid' => $legacyfile->itemid,
                'filepath' => $legacyfile->filepath,
                'filename' => $legacyfile->filename,
            ]);

            if ($existing) {
                $DB->delete_records('files', ['id' => $legacyfile->id]);
            } else {
                $DB->set_field('files', 'component', 'local_video_bridge', ['id' => $legacyfile->id]);
            }
        }

        upgrade_plugin_savepoint(true, 2026100508, 'local', 'video_bridge');
    }

    if ($oldversion < 2026100600) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_video_bridge_progress');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('source', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('mediahash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('currenttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('percent', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('map', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('context_fk', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
        $table->add_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index(
            'usage_user',
            XMLDB_INDEX_UNIQUE,
            ['contextid', 'component', 'itemid', 'mediahash', 'userid']
        );
        $table->add_index('contextid', XMLDB_INDEX_NOTUNIQUE, ['contextid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('component_item', XMLDB_INDEX_NOTUNIQUE, ['component', 'itemid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100600, 'local', 'video_bridge');
    }


    if ($oldversion < 2026100601) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_video_bridge_session');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('source', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('mediahash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('sessionid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('level', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'basic');
        $table->add_field('startedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('watchtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('plays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('pauses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('seeks', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('replays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('skips', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('dropoff', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxposition', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('speedavg', XMLDB_TYPE_NUMBER, '10,4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('ranges', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('pausepoints', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('skippoints', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('replaypoints', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('rates', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('context_fk', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
        $table->add_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('session_unique', XMLDB_INDEX_UNIQUE,
            ['contextid', 'component', 'itemid', 'mediahash', 'userid', 'sessionid']);
        $table->add_index('component_item_media', XMLDB_INDEX_NOTUNIQUE, ['component', 'itemid', 'mediahash']);
        $table->add_index('modified', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100601, 'local', 'video_bridge');
    }


    if ($oldversion < 2026100602) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_video_bridge_session');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('source', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('mediahash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('sessionid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('level', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'basic');
        $table->add_field('startedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('watchtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('plays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('pauses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('seeks', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('replays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('skips', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('dropoff', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxposition', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('speedavg', XMLDB_TYPE_NUMBER, '10, 4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('ranges', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('pausepoints', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('skippoints', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('replaypoints', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('rates', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('continuousblocks', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('inactivitygaps', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('context_fk', XMLDB_KEY_FOREIGN, ['contextid'], 'context', ['id']);
        $table->add_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('context_user_session', XMLDB_INDEX_UNIQUE, ['contextid', 'userid', 'sessionid']);
        $table->add_index('component_item', XMLDB_INDEX_NOTUNIQUE, ['component', 'itemid']);
        $table->add_index('media_user', XMLDB_INDEX_NOTUNIQUE, ['mediahash', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100602, 'local', 'video_bridge');
    }


    if ($oldversion < 2026100606) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_video_bridge_session');

        if ($dbman->table_exists($table)) {
            $levelfield = new xmldb_field(
                'level',
                XMLDB_TYPE_CHAR,
                '16',
                null,
                XMLDB_NOTNULL,
                null,
                'basic',
                'sessionid'
            );
            if ($dbman->field_exists($table, $levelfield)) {
                $dbman->change_field_precision($table, $levelfield);
            }

            $continuousfield = new xmldb_field(
                'continuousblocks',
                XMLDB_TYPE_TEXT,
                null,
                null,
                null,
                null,
                null,
                'rates'
            );
            if (!$dbman->field_exists($table, $continuousfield)) {
                $dbman->add_field($table, $continuousfield);
            }

            $inactivityfield = new xmldb_field(
                'inactivitygaps',
                XMLDB_TYPE_TEXT,
                null,
                null,
                null,
                null,
                null,
                'continuousblocks'
            );
            if (!$dbman->field_exists($table, $inactivityfield)) {
                $dbman->add_field($table, $inactivityfield);
            }
        }

        upgrade_plugin_savepoint(true, 2026100606, 'local', 'video_bridge');
    }

    if ($oldversion < 2026100610) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_video_bridge_session');
        $field = new xmldb_field(
            'events',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'inactivitygaps'
        );
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026100610, 'local', 'video_bridge');
    }

    return true;
}
