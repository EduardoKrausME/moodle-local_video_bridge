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
 * Base contract for caption tools.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\captiontool;

use moodle_exception;

/**
 * Base class implemented by caption processing tools.
 */
abstract class plugin_base {
    private const MAX_INPUT_LENGTH = 300000;

    public function get_sort_order(): int {
        return 100;
    }

    abstract public function get_component(): string;

    abstract public function get_name(): string;

    abstract public function get_description(): string;

    abstract public function get_input_format(): string;

    abstract public function get_output_format(): string;

    abstract protected function get_default_purpose_idnumber(): string;

    abstract public function execute(string $input, array $options = [], ?int $userid = null): result;

    final public function get_purpose_idnumber(): string {
        $config = get_config($this->get_component());
        $purpose = trim((string)($config->purposeidnumber ?? ''));
        return $purpose !== '' ? $purpose : $this->get_default_purpose_idnumber();
    }

    final protected function assert_input(string $input): string {
        $input = trim($input);
        if ($input === '') {
            throw new moodle_exception('emptycaptiontoolinput', 'local_video_bridge');
        }
        if (strlen($input) > self::MAX_INPUT_LENGTH) {
            throw new moodle_exception('captiontoolinputtoolarge', 'local_video_bridge');
        }
        return $input;
    }

    final protected function generate(array|string $messages, ?int $userid = null, array $options = []): object {
        if (!class_exists('\\local_ai_bridge\\api')) {
            throw new moodle_exception('aibridgemissing', 'local_video_bridge');
        }
        return \local_ai_bridge\api::generate(
            $this->get_purpose_idnumber(),
            $messages,
            $userid,
            $options
        );
    }

    final protected function result_metadata(object $response, array $extra = []): array {
        return $extra + [
            'purpose' => $this->get_purpose_idnumber(),
            'model' => (string)($response->model ?? ''),
            'inputtokens' => (int)($response->inputtokens ?? 0),
            'outputtokens' => (int)($response->outputtokens ?? 0),
            'totaltokens' => (int)($response->totaltokens ?? 0),
            'estimatedcost' => (float)($response->estimatedcost ?? 0),
        ];
    }
}
