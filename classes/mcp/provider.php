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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Optional MCP integration discovered by local_mcp when it is installed.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\mcp;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;
use local_video_bridge\mcp\read\get_my_progress;
use local_video_bridge\mcp\read\list_providers;
use local_video_bridge\captiontool\manager as captiontool_manager;
use local_video_bridge\mcp\write\captiontool;
use local_video_bridge\mcp\write\process_captions;

/**
 * Register provider-independent Video Bridge tools.
 *
 * This class is autoloaded only by local_mcp's optional provider discovery.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /**
     * Register safe read operations for all installed video sources and consumers.
     *
     * @return array
     */
    public function get_read_tools(): array {
        return [
            new list_providers(),
            new get_my_progress(),
        ];
    }

    /**
     * Register AI operations only when their optional backend is present.
     *
     * @return array
     */
    public function get_write_tools(): array {
        if (!class_exists('\\local_ai_bridge\\api')) {
            return [];
        }
        // Retain the generic tool for existing MCP clients, while publishing every
        // installed captiontool under its own discoverable MCP tool name.
        $tools = [new process_captions()];
        foreach ((new captiontool_manager())->get_plugins() as $name => $plugin) {
            if ($plugin->is_available()) {
                $tools[] = new captiontool($name, $plugin);
            }
        }
        return $tools;
    }
}
