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

    if ($oldversion < 2026100507) {
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

        upgrade_plugin_savepoint(true, 2026100507, 'local', 'video_bridge');
    }

    return true;
}
