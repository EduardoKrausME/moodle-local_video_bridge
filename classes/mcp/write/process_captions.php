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
 * Confirmable AI caption processing through the existing caption tool API.
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
use local_video_bridge\captiontool\manager as captiontool_manager;
use moodle_exception;

/**
 * AI processing is a WRITE action because it uses a potentially billable external service.
 */
final class process_captions extends base_tool {
    /** @return string */
    public function get_name(): string {
        return 'video_bridge_process_captions';
    }

    /** @return string */
    public function get_title(): string {
        return 'Video Bridge: process captions with AI';
    }

    /** @return string */
    public function get_description(): string {
        return 'Generate, translate, analyze or create a mindmap from caption text using optional AI Bridge; requires confirmation and may incur AI usage charges.';
    }

    /** @return string */
    public function get_required_capability(): string {
        return 'moodle/course:manageactivities';
    }

    /** @return array */
    public function get_input_schema(): array {
        return $this->object_schema([
            'courseid' => ['type' => 'integer', 'minimum' => 1],
            'tool' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
            'input' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 300000],
            'options' => [
                'type' => 'object',
                'properties' => [
                    'language' => ['type' => 'string'],
                    'targetlanguage' => ['type' => 'string'],
                    'sourcelanguage' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'duration' => ['type' => 'number', 'minimum' => 0],
                ],
                'additionalProperties' => false,
            ],
        ], ['courseid', 'tool', 'input']);
    }

    /**
     * @param array $arguments
     * @return context
     */
    public function resolve_context(array $arguments): context {
        return context_course::instance((int)$arguments['courseid'], MUST_EXIST);
    }

    /**
     * Preview the exact operation without repeating private captions in the confirmation UI.
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $manager = new captiontool_manager();
        $tool = $manager->get_plugin((string)$arguments['tool']);
        if (!$tool->is_available()) {
            throw new moodle_exception('aibridgemissing', 'local_video_bridge');
        }
        return [
            'tool' => $this->get_name(),
            'courseid' => (int)$arguments['courseid'],
            'captiontool' => $tool->get_component(),
            'input_sha256' => hash('sha256', (string)$arguments['input']),
            'input_bytes' => strlen((string)$arguments['input']),
            'options' => $arguments['options'] ?? [],
            'external_ai_request' => true,
        ];
    }

    /**
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $result = (new captiontool_manager())->execute(
            (string)$arguments['tool'],
            (string)$arguments['input'],
            $arguments['options'] ?? [],
            $identity->userid
        );
        return [
            'format' => $result->format,
            'content' => $result->content,
            'metadata' => $result->metadata,
        ];
    }
}
