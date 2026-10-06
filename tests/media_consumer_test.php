<?php
// This file is part of Moodle - https://moodle.org/
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
 * Tests for multi-media Video Bridge consumers.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge;

use advanced_testcase;
use local_video_bridge\media\config as media_config;
use local_video_bridge\progress\manager as progress_manager;

/**
 * Verifies per-item media identity and bulk progress loading.
 */
final class media_consumer_test extends advanced_testcase {
    /**
     * The same provider configuration remains independent between media items.
     *
     * @return void
     */
    public function test_media_hash_is_scoped_by_media_id(): void {
        $first = new media_config('youtube', '{"id":"abc123xyz"}', 10);
        $second = new media_config('youtube', '{"id":"abc123xyz"}', 11);

        $this->assertNotSame($first->get_mediahash(), $second->get_mediahash());
        $this->assertSame(
            analytics::media_hash('youtube', '{"id":"abc123xyz"}'),
            (new media_config('youtube', '{"id":"abc123xyz"}', 0))->get_mediahash()
        );
    }

    /**
     * Bulk progress returns a user x media matrix.
     *
     * @return void
     */
    public function test_get_progress_bulk(): void {
        global $DB;

        $this->resetAfterTest();
        $contextid = \context_system::instance()->id;
        $now = time();
        $hash1 = hash('sha256', 'one');
        $hash2 = hash('sha256', 'two');

        foreach ([
            [101, $hash1, 25],
            [101, $hash2, 80],
            [202, $hash1, 100],
        ] as [$userid, $hash, $percent]) {
            $DB->insert_record('local_video_bridge_progress', (object)[
                'contextid' => $contextid,
                'component' => 'mod_example',
                'itemid' => 99,
                'source' => 'youtube',
                'mediahash' => $hash,
                'userid' => $userid,
                'currenttime' => 0,
                'duration' => 100,
                'percent' => $percent,
                'map' => '[]',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        $matrix = progress_manager::get_progress_bulk(
            $contextid,
            'mod_example',
            99,
            [$hash1, $hash2],
            [101, 202]
        );

        $this->assertSame(25, (int)$matrix[101][$hash1]->percent);
        $this->assertSame(80, (int)$matrix[101][$hash2]->percent);
        $this->assertSame(100, (int)$matrix[202][$hash1]->percent);
        $this->assertArrayNotHasKey($hash2, $matrix[202]);
    }

    /**
     * Per-media cleanup does not remove sibling media progress.
     *
     * @return void
     */
    public function test_delete_consumer_media(): void {
        global $DB;

        $this->resetAfterTest();
        $contextid = \context_system::instance()->id;
        $now = time();
        $hash1 = hash('sha256', 'delete-one');
        $hash2 = hash('sha256', 'keep-two');

        foreach ([$hash1, $hash2] as $hash) {
            $DB->insert_record('local_video_bridge_progress', (object)[
                'contextid' => $contextid,
                'component' => 'mod_example',
                'itemid' => 77,
                'source' => 'youtube',
                'mediahash' => $hash,
                'userid' => 101,
                'currenttime' => 0,
                'duration' => 100,
                'percent' => 10,
                'map' => '[]',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        progress_manager::delete_consumer_media(
            $contextid,
            'mod_example',
            77,
            $hash1
        );

        $this->assertFalse($DB->record_exists('local_video_bridge_progress', ['mediahash' => $hash1]));
        $this->assertTrue($DB->record_exists('local_video_bridge_progress', ['mediahash' => $hash2]));
    }
}
