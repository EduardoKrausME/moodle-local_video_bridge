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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests scoped privacy discovery and selective erasure.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge;

use context_module;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use local_video_bridge\privacy\provider;

/**
 * Covers GDPR user discovery and selected-user erasure without cross-context deletion.
 *
 * @covers \local_video_bridge\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /**
     * Only approved users and rows in the approved module context may be erased.
     */
    public function test_userlist_scopes_both_storage_tables(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $first = $this->getDataGenerator()->create_module("page", ["course" => $course->id]);
        $second = $this->getDataGenerator()->create_module("page", ["course" => $course->id]);
        $cm1 = get_coursemodule_from_instance("page", $first->id, $course->id, false, MUST_EXIST);
        $cm2 = get_coursemodule_from_instance("page", $second->id, $course->id, false, MUST_EXIST);
        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $hash = hash("sha256", "privacy-test-media");

        foreach ([[$context1, $first, $user1], [$context1, $first, $user2],
                     [$context2, $second, $user1]] as $entry) {
            [$context, $page, $user] = $entry;
            \local_video_bridge\progress\manager::save(
                $context->id, "mod_page", (int)$page->id, "html5", $hash,
                (int)$user->id, 10, 100, [1]
            );
            analytics::save_session(
                $context, "mod_page", (int)$page->id, "html5", $hash,
                (int)$user->id, [
                    "sessionid" => "privacy-session-" . $user->id,
                    "level" => analytics::LEVEL_BASIC,
                ]
            );
        }

        $users = new userlist($context1, "local_video_bridge");
        provider::get_users_in_context($users);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $users->get_userids());

        provider::delete_data_for_users(new approved_userlist(
            $context1, "local_video_bridge", [$user1->id]
        ));

        foreach (["local_video_bridge_progress", "local_video_bridge_session"] as $table) {
            $this->assertSame(0, $DB->count_records($table, [
                "contextid" => $context1->id, "userid" => $user1->id,
            ]));
            $this->assertSame(1, $DB->count_records($table, [
                "contextid" => $context1->id, "userid" => $user2->id,
            ]));
            $this->assertSame(1, $DB->count_records($table, [
                "contextid" => $context2->id, "userid" => $user1->id,
            ]));
        }
    }
}
