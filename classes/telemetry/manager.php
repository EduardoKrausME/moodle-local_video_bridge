<?php
// This file is part of Moodle - http://moodle.org/.
/**
 * Public telemetry facade for Video Bridge consumers.
 *
 * @package local_video_bridge
 */
namespace local_video_bridge\telemetry;

use context_module;
use local_video_bridge\analytics;
use local_video_bridge\analytics\manager as analytics_manager;
use stdClass;

/**
 * Stable provider-independent telemetry API.
 *
 * Storage remains owned by Video Bridge. This facade deliberately exposes facts
 * and compact sessions instead of leaking internal table names to consumers.
 */
final class manager {
    public const LEVEL_OFF = analytics::LEVEL_OFF;
    public const LEVEL_BASIC = analytics::LEVEL_BASIC;
    public const LEVEL_DETAILED = analytics::LEVEL_DETAILED;

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
