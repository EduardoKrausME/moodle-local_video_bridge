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
 * Result returned by a caption tool.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\captiontool;

/**
 * Immutable caption tool result.
 */
class result {
    /** @var string Caption tool output content. */
    public readonly string $content;

    /** @var string Caption tool output format. */
    public readonly string $format;

    /** @var array Additional result metadata. */
    public readonly array $metadata;

    /**
     * Initializes the caption tool result.
     *
     * @param string $content Caption tool output content.
     * @param string $format Caption tool output format.
     * @param array $metadata Additional result metadata.
     */
    public function __construct(string $content, string $format, array $metadata = []) {
        $this->content = $content;
        $this->format = $format;
        $this->metadata = $metadata;
    }
}
