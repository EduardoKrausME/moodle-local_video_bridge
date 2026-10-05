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
 * Plugin information for shared video source subplugins.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\plugininfo;

use admin_settingpage;
use core\plugininfo\base;
use moodle_url;
use part_of_admin_tree;

/**
 * Describes Video Bridge source subplugins to Moodle's plugin manager.
 */
class videoprogresssource extends base {
    /**
     * Prevents removal of providers distributed as part of Video Bridge.
     *
     * @return bool Whether Moodle may offer the uninstall action.
     */
    public function is_uninstall_allowed(): bool {
        $bundled = [
            'upload',
            'url',
            'youtube',
            'vimeo',
            'nextcloud',
            'drive',
            'pandavideo',
            'ottflix',
            'embed',
        ];
        return !in_array($this->name, $bundled, true);
    }

    /**
     * Returns the Video Bridge administration page.
     *
     * @return moodle_url Management URL.
     */
    public static function get_manage_url(): moodle_url {
        return new moodle_url('/local/video_bridge/admin_plugins.php', [
            'type' => 'videoprogresssource',
        ]);
    }

    /**
     * Returns the source-specific settings section name.
     *
     * @return string Settings section name.
     */
    public function get_settings_section_name(): string {
        return $this->type . '_' . $this->name;
    }

    /**
     * Loads an optional source settings.php into the administration tree.
     *
     * @param part_of_admin_tree $adminroot Administration tree.
     * @param string $parentnodename Parent node name.
     * @param bool $hassiteconfig Whether the user may configure the site.
     * @return void
     */
    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig): void {
        if (!$this->is_installed_and_upgraded() || !$hassiteconfig ||
                !file_exists($this->full_path('settings.php'))) {
            return;
        }

        $plugininfo = $this;
        $settings = new admin_settingpage(
            $this->get_settings_section_name(),
            $this->displayname,
            'moodle/site:config'
        );
        if ($adminroot->fulltree) {
            include($this->full_path('settings.php'));
        }
        $adminroot->add($parentnodename, $settings);
    }
}
