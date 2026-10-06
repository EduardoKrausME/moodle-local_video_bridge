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
 * Caption tool manager.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\captiontool;

use core_collator;
use core_component;
use moodle_exception;

/**
 * Discovers and runs caption processing subplugins.
 */
class manager {
    /** @var plugin_base[]|null */
    private ?array $plugins = null;

    /**
     * Returns discovered caption tool plugins.
     *
     * @return array Return value.
     */
    public function get_plugins(): array {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $plugins = [];
        foreach (array_keys(core_component::get_plugin_list('videocaptiontool')) as $name) {
            $class = '\\videocaptiontool_' . $name . '\\plugin';
            if (!class_exists($class) || !is_subclass_of($class, plugin_base::class)) {
                debugging(get_string('invalidcaptiontool', 'local_video_bridge', $name), DEBUG_DEVELOPER);
                continue;
            }
            $plugins[$name] = new $class();
        }

        $groups = [];
        foreach ($plugins as $name => $plugin) {
            $groups[$plugin->get_sort_order()][$name] = $plugin->get_name();
        }
        ksort($groups, SORT_NUMERIC);

        $this->plugins = [];
        foreach ($groups as $displaynames) {
            core_collator::asort($displaynames);
            foreach (array_keys($displaynames) as $name) {
                $this->plugins[$name] = $plugins[$name];
            }
        }
        return $this->plugins;
    }

    /**
     * Returns one caption tool plugin.
     *
     * @param string $name Tool short name.
     * @return plugin_base Return value.
     */
    public function get_plugin(string $name): plugin_base {
        $name = clean_param($name, PARAM_PLUGIN);
        $plugins = $this->get_plugins();
        if (!isset($plugins[$name])) {
            throw new moodle_exception('captiontoolmissing', 'local_video_bridge', '', $name);
        }
        return $plugins[$name];
    }

    /**
     * Returns caption tool options for forms.
     *
     * @return array Return value.
     */
    public function get_options(): array {
        $options = [];
        foreach ($this->get_plugins() as $name => $plugin) {
            $options[$name] = $plugin->get_name();
        }
        return $options;
    }

    /**
     * Returns metadata describing available caption tools.
     *
     * @return array Return value.
     */
    public function describe(): array {
        $tools = [];
        foreach ($this->get_plugins() as $name => $plugin) {
            $tools[$name] = [
                'name' => $plugin->get_name(),
                'description' => $plugin->get_description(),
                'inputformat' => $plugin->get_input_format(),
                'outputformat' => $plugin->get_output_format(),
                'purpose' => $plugin->get_purpose_idnumber(),
            ];
        }
        return $tools;
    }

    /**
     * Executes a caption processing tool.
     *
     * @param string $name Tool short name.
     * @param string $input Caption input.
     * @param array $options Tool execution options.
     * @param ?int $userid Optional user id.
     * @return result Return value.
     */
    public function execute(string $name, string $input, array $options = [], ?int $userid = null): result {
        return $this->get_plugin($name)->execute($input, $options, $userid);
    }
}
