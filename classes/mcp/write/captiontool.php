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
 * One MCP tool per installed Video Bridge captiontool subplugin.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\mcp\write;

use context;
use context_course;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;
use local_video_bridge\captiontool\plugin_base;

/**
 * Dynamically adapts any installed captiontool to a dedicated confirmable MCP operation.
 */
final class captiontool extends base_tool {
    /** @var string Subplugin short name. */
    private string $name;

    /** @var plugin_base Underlying caption processing plugin. */
    private plugin_base $plugin;

    /**
     * Initialise a dedicated MCP operation for the caption processing subplugin.
     *
     * @param string $name Subplugin short name.
     * @param plugin_base $plugin The discovered caption processing plugin.
     */
    public function __construct(string $name, plugin_base $plugin) {
        $this->name = $name;
        $this->plugin = $plugin;
    }

    /**
     * Return the unique MCP tool identifier for this caption processor.
     *
     * @return string
     */
    public function get_name(): string {
        return 'video_bridge_caption_' . $this->name;
    }

    /**
     * Return the caption processing tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return 'Video Bridge: ' . $this->plugin->get_name();
    }

    /**
     * Describe the caption processing tool and its AI usage requirements.
     *
     * @return string
     */
    public function get_description(): string {
        return $this->plugin->get_description() .
            ' Input: ' . $this->plugin->get_input_format() .
            '; output: ' . $this->plugin->get_output_format() .
            '. Uses AI Bridge and requires confirmation before incurring AI usage charges.';
    }

    /**
     * Return the capability required to process captions.
     *
     * @return string
     */
    public function get_required_capability(): string {
        return 'moodle/course:manageactivities';
    }

    /**
     * Expose tool-specific arguments for bundled tools; custom subplugins accept their own options.
     *
     * @return array
     */
    public function get_input_schema(): array {
        $properties = [];
        $requiredoptions = [];
        $requiresoptions = false;

        switch ($this->name) {
            case 'generate':
                $properties = [
                    'language' => ['type' => 'string', 'description' => 'Caption language, e.g. pt-BR.'],
                    'duration' => ['type' => 'number', 'minimum' => 0],
                ];
                break;
            case 'translate':
                $properties = [
                    'sourcelanguage' => ['type' => 'string'],
                    'targetlanguage' => ['type' => 'string', 'minLength' => 1],
                ];
                $requiredoptions = ['targetlanguage'];
                $requiresoptions = true;
                break;
            case 'analyze':
                $properties = [
                    'language' => ['type' => 'string', 'description' => 'Expected caption language.'],
                ];
                break;
            case 'mindmap':
                $properties = [
                    'title' => ['type' => 'string'],
                    'language' => ['type' => 'string'],
                ];
                break;
        }

        $options = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => !in_array($this->name, ['generate', 'translate', 'analyze', 'mindmap'], true),
        ];
        if ($requiredoptions) {
            $options['required'] = $requiredoptions;
        }

        $required = ['courseid', 'input'];
        if ($requiresoptions) {
            $required[] = 'options';
        }
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1],
            'input' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 300000],
            'options' => $options,
        ], $required);
    }

    /**
     * Resolve the course context associated with the requested operation.
     *
     * @param array $arguments
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * Reuse the generic tool's redacted preview; never echo captions to the confirmation UI.
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $preview = (new process_captions())->preview($this->forward_arguments($arguments), $identity);
        $preview['tool'] = $this->get_name();
        return $preview;
    }

    /**
     * Execute the selected caption processing subplugin with the supplied arguments.
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return (new process_captions())->execute($this->forward_arguments($arguments), $identity);
    }

    /**
     * The caller can never select another subplugin through this dedicated operation.
     *
     * @param array $arguments
     * @return array
     */
    private function forward_arguments(array $arguments): array {
        $arguments['tool'] = $this->name;
        return $arguments;
    }
}
