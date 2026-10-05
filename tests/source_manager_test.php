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
 * Tests for the shared Video Bridge source layer.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge;

use local_video_bridge\source\manager;
use local_video_bridge\source\plugin_base;

/**
 * Verifies source discovery and normalization independently from consumer activities.
 */
final class source_manager_test extends \advanced_testcase {
    /**
     * Confirms that all bundled sources are owned and discovered by Video Bridge.
     *
     * @return void
     */
    public function test_discovers_bundled_sources(): void {
        $plugins = (new manager())->get_plugins();

        foreach ([
            'upload',
            'url',
            'youtube',
            'vimeo',
            'nextcloud',
            'drive',
            'pandavideo',
            'ottflix',
            'embed',
        ] as $source) {
            self::assertArrayHasKey($source, $plugins);
            self::assertInstanceOf(plugin_base::class, $plugins[$source]);
        }

        self::assertSame('upload', (new manager())->get_default_source());
    }

    /**
     * Confirms that consumers can use their own database field names.
     *
     * @return void
     */
    public function test_manager_supports_consumer_field_aliases(): void {
        $record = (object)[
            'provider' => 'youtube',
            'providerconfig' => '',
            'legacyurl' => '',
            'youtubeurl' => 'https://youtu.be/AbCdEf12345',
        ];

        $manager = new manager('provider', 'providerconfig', 'legacyurl');
        $config = $manager->normalise_record($record);

        self::assertSame(['id' => 'AbCdEf12345'], $config);
        self::assertSame(['id' => 'AbCdEf12345'], json_decode($record->providerconfig, true));
        self::assertSame('AbCdEf12345', $record->legacyurl);
    }

    /**
     * Confirms that a consumer can require capabilities without knowing provider implementations.
     *
     * @return void
     */
    public function test_filters_sources_by_capabilities(): void {
        $manager = new manager();
        $tracking = $manager->get_options(['tracking']);

        self::assertArrayHasKey('upload', $tracking);
        self::assertArrayHasKey('youtube', $tracking);
        self::assertArrayNotHasKey('drive', $tracking);
    }

    /**
     * Confirms direct URL normalization remains inside the source provider.
     *
     * @return void
     */
    public function test_direct_url_source_normalizes_hls(): void {
        $plugin = new \videoprogresssource_url\plugin();

        self::assertSame(
            [
                'url' => 'https://example.test/live/stream.m3u8?token=example',
                'hls' => true,
            ],
            $plugin->build_config((object)[
                'videourl' => 'https://example.test/live/stream.m3u8?token=example',
            ])
        );
    }

    /**
     * Confirms YouTube normalization remains independent from any activity plugin.
     *
     * @return void
     */
    public function test_youtube_source_normalizes_supported_url(): void {
        $plugin = new \videoprogresssource_youtube\plugin();

        self::assertSame(
            ['id' => 'AbCdEf12345'],
            $plugin->build_config((object)[
                'youtubeurl' => 'https://www.youtube.com/watch?v=AbCdEf12345',
            ])
        );
    }
}
