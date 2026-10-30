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
 * MCP discovery of Video Bridge subplugins.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\mcp\read;

use context;
use context_course;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;
use local_video_bridge\caption\manager as caption_manager;
use local_video_bridge\captiontool\manager as captiontool_manager;
use local_video_bridge\source\manager as source_manager;

/**
 * List video, caption and AI processing providers without disclosing their configurations.
 */
final class list_providers extends base_tool {
    /**
     * Return the provider discovery tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'video_bridge_list_providers';
    }

    /**
     * Return the video provider discovery tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return 'Video Bridge: list providers';
    }

    /**
     * Describe the available provider discovery operation.
     *
     * @return string
     */
    public function get_description(): string {
        return 'List installed video sources, caption sources and available AI caption tools for a course.';
    }

    /**
     * Return the capability required to discover providers.
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'moodle/course:view';
    }

    /**
     * Define the course identifier used to list available providers.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1],
        ], ['courseid']);
    }

    /**
     * Resolve the course context used for provider discovery.
     *
     * @param array $arguments
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * List available video sources, caption sources and processing tools.
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $sources = [];
        foreach ((new source_manager())->get_plugins() as $key => $plugin) {
            $sources[] = [
                'id' => $key,
                'name' => $plugin->get_name(),
                'capabilities' => $plugin->get_capabilities(),
            ];
        }

        $captions = [];
        foreach ((new caption_manager())->get_plugins() as $key => $plugin) {
            $captions[] = ['id' => $key, 'name' => $plugin->get_name()];
        }

        $tools = [];
        foreach ((new captiontool_manager())->describe() as $key => $tool) {
            $tools[] = [
                'id' => $key,
                'name' => $tool['name'],
                'description' => $tool['description'],
                'inputformat' => $tool['inputformat'],
                'outputformat' => $tool['outputformat'],
                'available' => $tool['available'],
            ];
        }

        return [
            'courseid' => (int)$arguments['courseid'],
            'video_sources' => $sources,
            'caption_sources' => $captions,
            'caption_tools' => $tools,
            'ai_bridge_available' => class_exists('\\local_ai_bridge\\api'),
        ];
    }
}
