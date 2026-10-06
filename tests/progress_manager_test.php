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
 * Progress manager tests.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge;

use advanced_testcase;
use local_video_bridge\progress\manager;

/**
 * Tests normalized viewing-map bucket calculations.
 *
 * @covers \local_video_bridge\progress\manager
 */
final class progress_manager_test extends advanced_testcase {
    public function test_short_video_uses_one_bucket_per_second(): void {
        $this->assertSame(40, manager::progress_length(40));
        $this->assertSame(1, manager::bucket_for_position(1, 40));
        $this->assertSame(40, manager::bucket_for_position(40, 40));
    }

    public function test_long_video_uses_one_hundred_buckets(): void {
        $this->assertSame(100, manager::progress_length(824));
        $this->assertSame(50, manager::bucket_for_position(412, 824));
        $this->assertSame(100, manager::bucket_for_position(824, 824));
    }

    public function test_progress_batch_empty_input_returns_empty_array(): void {
        $this->resetAfterTest();
        $hash = str_repeat('a', 64);

        $this->assertSame([], manager::get_progress_batch(1, 'mod_example', 1, $hash, []));
        $this->assertSame([], manager::get_latest_session_times(1, 'mod_example', 1, $hash, []));
    }

    public function test_invalid_duration_has_no_bucket(): void {
        $this->assertSame(0, manager::progress_length(0));
        $this->assertSame(0, manager::bucket_for_position(10, 0));
    }
}
