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
 * Session analytics tests.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge;

use advanced_testcase;
use context_module;
use local_video_bridge\analytics\metrics;

/**
 * Tests compact session persistence and server-owned facts.
 *
 * @covers \local_video_bridge\analytics
 */
final class analytics_session_test extends advanced_testcase {
    /**
     * Explicit session facts are persisted without trusting browser percentages.
     */
    public function test_explicit_session_metrics_are_persisted(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $record = analytics::save_session(
            $context,
            'mod_page',
            (int)$page->id,
            'html5',
            hash('sha256', 'test-media'),
            (int)$user->id,
            [
                'level' => analytics::LEVEL_DETAILED,
                'sessionid' => 'session-test-1',
                'duration' => 600,
                'sessionseconds' => 80,
                'watchtime' => 60,
                'pausedtime' => 20,
                'startposition' => 10,
                'endposition' => 90,
                '_serverpercentstart' => 12,
                '_serverpercentend' => 34,
                'events' => [
                    ['type' => 'play', 'position' => 10],
                    ['type' => 'playbackrate', 'position' => 40, 'from' => 1, 'to' => 1.5],
                    ['type' => 'ended', 'position' => 90],
                ],
            ]
        );

        $stored = $DB->get_record('local_video_bridge_session', ['id' => $record->id], '*', MUST_EXIST);
        $this->assertSame(80, (int)$stored->sessionduration);
        $this->assertSame(20, (int)$stored->pausedtime);
        $this->assertSame(10, (int)$stored->startposition);
        $this->assertSame(90, (int)$stored->endposition);
        $this->assertSame(12, (int)$stored->percentstart);
        $this->assertSame(34, (int)$stored->percentend);
        $this->assertSame(1, (int)$stored->ratechanges);
        $this->assertSame(1, (int)$stored->receivedended);
        $this->assertSame('ended', (string)$stored->endreason);
    }

    /**
     * Legacy camelCase metrics reads remain compatible.
     *
     * @return void
     */
    public function test_metrics_legacy_property_aliases(): void {
        $metrics = metrics::from_facts([
            'unique_watch_time' => 42,
            'playback_time' => 60,
            'real_session_time' => 90,
            'pause_count' => 3,
        ]);

        $this->assertSame(42, $metrics->uniqueWatchTime);
        $this->assertSame(60, $metrics->playbackTime);
        $this->assertSame(90, $metrics->sessionTime);
        $this->assertSame(3, $metrics->pauseCount);
        $this->assertTrue(isset($metrics->uniqueWatchTime));
        $this->assertSame(42, $metrics->to_array()['uniqueWatchTime']);
    }

}
