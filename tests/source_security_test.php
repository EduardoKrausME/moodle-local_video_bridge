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
 * Tests security-sensitive video source URL canonicalization.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge;

/**
 * Covers untrusted embed URLs and unlisted Vimeo media configuration.
 *
 * @covers \videoprogresssource_bunnystream\plugin
 * @covers \videoprogresssource_vimeo\plugin
 */
final class source_security_test extends \advanced_testcase {
    /**
     * Unsafe URL parameters cannot survive normalization of a Bunny Stream embed.
     */
    public function test_bunny_query_strips_arbitrary_attributes(): void {
        $plugin = new \videoprogresssource_bunnystream\plugin();
        $config = $plugin->build_config((object)[
            "bunnyurl" => "https://iframe.mediadelivery.net/embed/1234/abcdefghij?" .
                "autoplay=1&token=abc123&evil=onload%3Dalert%281%29",
        ]);
        $this->assertSame(
            "https://iframe.mediadelivery.net/embed/1234/abcdefghij?autoplay=1&token=abc123",
            $plugin->get_legacy_value($config)
        );
        $this->assertSame(
            "https://iframe.mediadelivery.net/embed/1234/abcdefghij",
            $plugin->get_legacy_value([
                "libraryid" => "1234",
                "videoid" => "abcdefghij",
                "query" => "evil=%22%20onload%3Dalert%281%29",
            ])
        );
    }

    /**
     * The hash in a Vimeo unlisted URL remains part of provider configuration.
     */
    public function test_vimeo_unlisted_url_is_preserved(): void {
        $plugin = new \videoprogresssource_vimeo\plugin();
        $this->assertSame(
            ["id" => "123456789", "hash" => "AbCd1234"],
            $plugin->build_config((object)[
                "vimeourl" => "https://vimeo.com/123456789/AbCd1234",
            ])
        );
    }
}
