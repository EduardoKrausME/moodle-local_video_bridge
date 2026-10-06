<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public analytics API for Video Bridge consumers.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge;

use context_module;
use invalid_parameter_exception;
use stdClass;

/**
 * Compact session telemetry and normalized analytics queries.
 *
 * Consumers should use this class instead of reading Video Bridge tables.
 */
class analytics {
    public const LEVEL_OFF = 'off';
    public const LEVEL_BASIC = 'basic';
    public const LEVEL_DETAILED = 'detailed';

    /** @return string[] */
    public static function levels(): array {
        return [self::LEVEL_OFF, self::LEVEL_BASIC, self::LEVEL_DETAILED];
    }

    public static function normalise_level(string $level): string {
        $level = strtolower(trim($level));
        if (!in_array($level, self::levels(), true)) {
            throw new invalid_parameter_exception('Invalid Video Bridge telemetry level.');
        }
        return $level;
    }

    /**
     * Stores or updates one compact playback session.
     *
     * @param context_module $context
     * @param string $component
     * @param int $itemid
     * @param string $source
     * @param string $mediahash
     * @param int $userid
     * @param array $payload
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
        global $DB;

        $level = self::normalise_level((string)($payload['level'] ?? self::LEVEL_BASIC));
        if ($level === self::LEVEL_OFF) {
            return null;
        }

        $sessionid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($payload['sessionid'] ?? ''));
        if ($sessionid === '' || strlen($sessionid) > 64) {
            throw new invalid_parameter_exception('Invalid Video Bridge analytics session id.');
        }

        $now = time();
        $params = [
            'contextid' => $context->id,
            'component' => clean_param($component, PARAM_COMPONENT),
            'itemid' => $itemid,
            'mediahash' => $mediahash,
            'userid' => $userid,
            'sessionid' => $sessionid,
        ];
        $record = $DB->get_record('local_video_bridge_session', $params);

        $ranges = $level === self::LEVEL_DETAILED
            ? self::normalise_ranges($payload['ranges'] ?? [])
            : [];
        $pausepoints = $level === self::LEVEL_DETAILED
            ? self::normalise_points($payload['pausepoints'] ?? [])
            : [];
        $skippoints = $level === self::LEVEL_DETAILED
            ? self::normalise_ranges($payload['skippoints'] ?? [], 1000)
            : [];
        $replaypoints = $level === self::LEVEL_DETAILED
            ? self::normalise_ranges($payload['replaypoints'] ?? [], 1000)
            : [];
        $rates = self::normalise_rates($payload['rates'] ?? []);

        $values = [
            'source' => clean_param($source, PARAM_PLUGIN),
            'level' => $level,
            'startedat' => max(0, (int)($payload['startedat'] ?? $now)),
            'endedat' => max(0, (int)($payload['endedat'] ?? 0)),
            'duration' => self::bound_int($payload['duration'] ?? 0, 0, 604800),
            'watchtime' => self::bound_int($payload['watchtime'] ?? 0, 0, 604800),
            'plays' => self::bound_int($payload['plays'] ?? 0, 0, 100000),
            'pauses' => self::bound_int($payload['pauses'] ?? 0, 0, 100000),
            'seeks' => self::bound_int($payload['seeks'] ?? 0, 0, 100000),
            'replays' => self::bound_int($payload['replays'] ?? 0, 0, 100000),
            'skips' => self::bound_int($payload['skips'] ?? 0, 0, 100000),
            'dropoff' => self::bound_int($payload['dropoff'] ?? 0, 0, 604800),
            'maxposition' => self::bound_int($payload['maxposition'] ?? 0, 0, 604800),
            'speedavg' => max(0.1, min(16.0, (float)($payload['speedavg'] ?? 1.0))),
            'ranges' => json_encode($ranges, JSON_THROW_ON_ERROR),
            'pausepoints' => json_encode($pausepoints, JSON_THROW_ON_ERROR),
            'skippoints' => json_encode($skippoints, JSON_THROW_ON_ERROR),
            'replaypoints' => json_encode($replaypoints, JSON_THROW_ON_ERROR),
            'rates' => json_encode($rates, JSON_THROW_ON_ERROR),
            'timemodified' => $now,
        ];

        if ($record) {
            foreach ($values as $field => $value) {
                $record->{$field} = $value;
            }
            $DB->update_record('local_video_bridge_session', $record);
            return $record;
        }

        $record = (object)($params + $values + ['timecreated' => $now]);
        try {
            $record->id = $DB->insert_record('local_video_bridge_session', $record);
        } catch (\dml_write_exception $exception) {
            $record = $DB->get_record('local_video_bridge_session', $params, '*', MUST_EXIST);
            foreach ($values as $field => $value) {
                $record->{$field} = $value;
            }
            $DB->update_record('local_video_bridge_session', $record);
        }
        return $record;
    }

    /**
     * Returns compact watched ranges without exposing storage details.
     */
    public static function get_watched_ranges(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        ?int $userid = null,
        array $filters = []
    ): array {
        $sessions = self::get_session_metrics(
            $contextid, $component, $itemid, $mediahash, $userid, $filters
        );
        $result = [];
        foreach ($sessions as $session) {
            $result[] = [
                'userid' => (int)$session->userid,
                'sessionid' => (string)$session->sessionid,
                'startedat' => (int)$session->startedat,
                'endedat' => (int)$session->endedat,
                'ranges' => json_decode((string)$session->ranges, true) ?: [],
            ];
        }
        return $result;
    }

    /**
     * Returns normalized session metrics for consumers.
     */
    public static function get_session_metrics(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        ?int $userid = null,
        array $filters = []
    ): array {
        global $DB;

        $where = [
            'contextid = :contextid',
            'component = :component',
            'itemid = :itemid',
            'mediahash = :mediahash',
        ];
        $params = [
            'contextid' => $contextid,
            'component' => clean_param($component, PARAM_COMPONENT),
            'itemid' => $itemid,
            'mediahash' => $mediahash,
        ];

        if ($userid !== null) {
            $where[] = 'userid = :userid';
            $params['userid'] = $userid;
        }
        if (!empty($filters['from'])) {
            $where[] = 'startedat >= :fromdate';
            $params['fromdate'] = (int)$filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'startedat <= :todate';
            $params['todate'] = (int)$filters['to'];
        }
        if (!empty($filters['modifiedfrom'])) {
            $where[] = 'timemodified >= :modifiedfrom';
            $params['modifiedfrom'] = (int)$filters['modifiedfrom'];
        }
        if (!empty($filters['modifiedto'])) {
            $where[] = 'timemodified <= :modifiedto';
            $params['modifiedto'] = (int)$filters['modifiedto'];
        }

        if (!empty($filters['userids']) && is_array($filters['userids'])) {
            $userids = array_values(array_unique(array_filter(array_map('intval', $filters['userids']))));
            if (!$userids) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
            $where[] = 'userid ' . $insql;
            $params += $inparams;
        }

        return array_values($DB->get_records_select(
            'local_video_bridge_session',
            implode(' AND ', $where),
            $params,
            'startedat ASC, id ASC'
        ));
    }

    /**
     * Builds collective coverage buckets from compact sessions.
     *
     * Returned values are normalized and provider-independent.
     */
    public static function get_activity_coverage(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        int $bucketcount = 100,
        array $filters = []
    ): array {
        $bucketcount = max(10, min(1000, $bucketcount));
        $sessions = self::get_session_metrics(
            $contextid, $component, $itemid, $mediahash, null, $filters
        );

        $duration = 0;
        foreach ($sessions as $session) {
            $duration = max($duration, (int)$session->duration);
        }
        if ($duration <= 0) {
            return ['duration' => 0, 'viewers' => 0, 'buckets' => []];
        }

        $viewers = [];
        $buckets = [];
        for ($i = 0; $i < $bucketcount; $i++) {
            $buckets[$i] = [
                'bucket' => $i,
                'start' => (int)floor($i * $duration / $bucketcount),
                'end' => (int)ceil(($i + 1) * $duration / $bucketcount),
                'viewers' => 0,
                'plays' => 0,
                'replays' => 0,
                'pauses' => 0,
                'skips' => 0,
                'dropoffs' => 0,
            ];
        }

        $seenbybucket = [];
        foreach ($sessions as $session) {
            $userid = (int)$session->userid;
            $viewers[$userid] = true;
            $ranges = json_decode((string)$session->ranges, true) ?: [];
            foreach ($ranges as $range) {
                if (!is_array($range) || count($range) < 2) {
                    continue;
                }
                $first = self::position_to_bucket((float)$range[0], $duration, $bucketcount);
                $last = self::position_to_bucket((float)$range[1], $duration, $bucketcount);
                for ($bucket = $first; $bucket <= $last; $bucket++) {
                    $seenbybucket[$bucket][$userid] = true;
                }
            }

            $buckets[self::position_to_bucket(0, $duration, $bucketcount)]['plays'] += (int)$session->plays;
            self::add_points($buckets, json_decode((string)$session->pausepoints, true) ?: [], 'pauses', $duration, $bucketcount);
            self::add_range_starts($buckets, json_decode((string)$session->skippoints, true) ?: [], 'skips', $duration, $bucketcount);
            self::add_range_starts($buckets, json_decode((string)$session->replaypoints, true) ?: [], 'replays', $duration, $bucketcount);
            $dropoff = max(0, min($duration, (int)$session->dropoff));
            $buckets[self::position_to_bucket($dropoff, $duration, $bucketcount)]['dropoffs']++;
        }

        foreach ($seenbybucket as $bucket => $users) {
            $buckets[$bucket]['viewers'] = count($users);
        }

        return [
            'duration' => $duration,
            'viewers' => count($viewers),
            'buckets' => array_values($buckets),
        ];
    }

    private static function add_points(array &$buckets, array $points, string $field, int $duration, int $bucketcount): void {
        foreach (array_slice($points, 0, 1000) as $point) {
            $bucket = self::position_to_bucket((float)$point, $duration, $bucketcount);
            $buckets[$bucket][$field]++;
        }
    }

    private static function add_range_starts(array &$buckets, array $ranges, string $field, int $duration, int $bucketcount): void {
        foreach (array_slice($ranges, 0, 1000) as $range) {
            if (!is_array($range) || count($range) < 2) {
                continue;
            }
            $bucket = self::position_to_bucket((float)$range[0], $duration, $bucketcount);
            $buckets[$bucket][$field]++;
        }
    }

    private static function position_to_bucket(float $position, int $duration, int $bucketcount): int {
        if ($duration <= 0) {
            return 0;
        }
        return max(0, min($bucketcount - 1, (int)floor(($position / $duration) * $bucketcount)));
    }

    private static function normalise_ranges($ranges, int $limit = 500): array {
        if (!is_array($ranges)) {
            return [];
        }
        $clean = [];
        foreach (array_slice($ranges, 0, $limit) as $range) {
            if (!is_array($range) || count($range) < 2) {
                continue;
            }
            $start = max(0, min(604800, (float)$range[0]));
            $end = max($start, min(604800, (float)$range[1]));
            if ($end - $start > 0.05) {
                $clean[] = [round($start, 2), round($end, 2)];
            }
        }
        usort($clean, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($clean as $range) {
            $last = count($merged) - 1;
            if ($last >= 0 && $range[0] <= $merged[$last][1] + 1.0) {
                $merged[$last][1] = max($merged[$last][1], $range[1]);
            } else {
                $merged[] = $range;
            }
        }
        return $merged;
    }

    private static function normalise_points($points): array {
        if (!is_array($points)) {
            return [];
        }
        $clean = [];
        foreach (array_slice($points, 0, 1000) as $point) {
            $clean[] = round(max(0, min(604800, (float)$point)), 2);
        }
        return $clean;
    }

    private static function normalise_rates($rates): array {
        if (!is_array($rates)) {
            return [];
        }
        $clean = [];
        foreach (array_slice($rates, 0, 50, true) as $rate => $seconds) {
            $key = (string)max(0.1, min(16.0, (float)$rate));
            $clean[$key] = max(0, min(604800, (int)$seconds));
        }
        return $clean;
    }

    private static function bound_int($value, int $min, int $max): int {
        return max($min, min($max, (int)$value));
    }
}
