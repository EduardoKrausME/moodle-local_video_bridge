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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Public telemetry facade for Video Bridge consumers.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\telemetry;

use context_module;
use local_video_bridge\analytics;
use local_video_bridge\analytics\manager as analytics_manager;
use stdClass;

/**
 * Stable provider-independent telemetry API.
 */
final class manager {
    /** Telemetry disabled. */
    public const LEVEL_OFF = analytics::LEVEL_OFF;
    /** Compact session totals. */
    public const LEVEL_BASIC = analytics::LEVEL_BASIC;
    /** Compact positional and ordered session evidence. */
    public const LEVEL_DETAILED = analytics::LEVEL_DETAILED;

    /**
     * Stores or updates one compact session.
     *
     * @param context_module $context Module context.
     * @param string $component Consumer component.
     * @param int $itemid Consumer item id.
     * @param string $source Source provider.
     * @param string $mediahash Stable media hash.
     * @param int $userid User id.
     * @param array $payload Normalized compact telemetry payload.
     * @return stdClass|null
     */
    public static function save_session(
        context_module $context,
        string $component,
        int $itemid,
        string $source,
        string $mediahash,
        int $userid,
        array $payload
    ): ?stdClass {
        return analytics::save_session($context, $component, $itemid, $source, $mediahash, $userid, $payload);
    }

    /**
     * Returns compact sessions.
     *
     * @param int $contextid Module context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer item id.
     * @param string $mediahash Stable media hash.
     * @param int|null $userid Optional user id.
     * @param array $filters Optional filters.
     * @return array
     */
    public static function get_sessions(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        ?int $userid = null,
        array $filters = []
    ): array {
        return analytics::get_session_metrics($contextid, $component, $itemid, $mediahash, $userid, $filters);
    }

    /**
     * Returns the stable metrics object.
     *
     * @param int $contextid Module context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer item id.
     * @param string $mediahash Stable media hash.
     * @param int $userid User id.
     * @param array $filters Optional filters.
     * @return \local_video_bridge\analytics\metrics
     */
    public static function get_metrics(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        int $userid,
        array $filters = []
    ): \local_video_bridge\analytics\metrics {
        return analytics_manager::get_user_metrics($contextid, $component, $itemid, $mediahash, $userid, $filters);
    }

    /**
     * Returns provider-independent analytics facts.
     *
     * @param int $contextid Module context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer item id.
     * @param string $mediahash Stable media hash.
     * @param int $userid User id.
     * @param array $filters Optional filters.
     * @return array
     */
    public static function get_facts(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        int $userid,
        array $filters = []
    ): array {
        return analytics_manager::get_facts($contextid, $component, $itemid, $mediahash, $userid, $filters);
    }
}
