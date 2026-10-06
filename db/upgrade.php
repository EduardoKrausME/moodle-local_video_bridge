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

    return true;
}
