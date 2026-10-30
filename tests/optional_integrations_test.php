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
 * Optional AI Bridge and MCP integration regression tests.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge;

use advanced_testcase;
use local_video_bridge\captiontool\manager as captiontool_manager;
use local_video_bridge\mcp\provider;

/**
 * Optional integrations must not prevent normal Video Bridge operation.
 *
 * @covers \local_video_bridge\captiontool\manager
 * @covers \local_video_bridge\captiontool\plugin_base
 */
final class optional_integrations_test extends advanced_testcase {
    /**
     * AI tools report their availability and cannot be selected when AI Bridge is absent.
     */
    public function test_caption_tool_selection_respects_optional_ai_bridge(): void {
        $manager = new captiontool_manager();
        $available = class_exists('\\local_ai_bridge\\api');
        $options = $manager->get_options();

        foreach ($manager->describe() as $name => $description) {
            $this->assertSame($available, $description['available']);
            $this->assertSame($available, isset($options[$name]));
        }
    }

    /**
     * An installed MCP discovers the bridge without a declared dependency.
     */
    public function test_optional_mcp_provider_registers_expected_tools(): void {
        if (!interface_exists('\\local_mcp\\extension\\read_provider_interface')) {
            $this->markTestSkipped('The optional local_mcp integration is not installed.');
        }

        $provider = new provider();
        $readtools = array_map(
            static fn($tool): string => $tool->get_name(),
            $provider->get_read_tools()
        );
        $this->assertContains('video_bridge_list_providers', $readtools);
        $this->assertContains('video_bridge_get_my_progress', $readtools);

        $writetools = $provider->get_write_tools();
        $writenames = array_map(
            static fn($tool): string => $tool->get_name(),
            $writetools
        );
        if (!class_exists('\\local_ai_bridge\\api')) {
            $this->assertSame([], $writenames);
            return;
        }

        // Every installed captiontool has its own MCP action, including future subplugins.
        $this->assertContains('video_bridge_process_captions', $writenames);
        $plugins = (new captiontool_manager())->get_plugins();
        foreach ($plugins as $name => $plugin) {
            if (!$plugin->is_available()) {
                continue;
            }
            $expected = 'video_bridge_caption_' . $name;
            $this->assertContains($expected, $writenames);
        }
        $this->assertCount(count($plugins) + 1, $writenames);
        $this->assertCount(count(array_unique($writenames)), $writenames);

        foreach (['generate', 'translate', 'analyze', 'mindmap'] as $name) {
            if (!isset($plugins[$name])) {
                continue;
            }
            $this->assertContains('video_bridge_caption_' . $name, $writenames);
        }
        // Confirm that the visible preview never echoes submitted caption text.
        $identity = new \local_mcp\security\authenticated_identity(2, 'test', []);
        foreach ($writetools as $tool) {
            if ($tool->get_name() !== 'video_bridge_caption_generate') {
                continue;
            }
            $preview = $tool->preview([
                'courseid' => SITEID,
                'input' => 'Private caption content for preview test.',
            ], $identity);
            $this->assertSame('video_bridge_caption_generate', $preview['tool']);
            $this->assertSame(
                hash('sha256', 'Private caption content for preview test.'),
                $preview['input_sha256']
            );
            $this->assertStringNotContainsString(
                'Private caption content for preview test.',
                json_encode($preview)
            );
        }

        if (isset($plugins['translate'])) {
            foreach ($writetools as $tool) {
                if ($tool->get_name() !== 'video_bridge_caption_translate') {
                    continue;
                }
                $schema = $tool->get_input_schema();
                $this->assertContains('options', $schema['required']);
                $this->assertContains('targetlanguage', $schema['properties']['options']['required']);
                $this->assertArrayNotHasKey('tool', $schema['properties']);
            }
        }
    }
}
