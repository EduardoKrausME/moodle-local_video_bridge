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
    /** Telemetry disabled. */
    public const LEVEL_OFF = 'off';

    /** Compact aggregate telemetry. */
    public const LEVEL_BASIC = 'basic';

    /** Detailed positional telemetry. */
    public const LEVEL_DETAILED = 'detailed';

    /**
     * Returns supported telemetry levels.
     *
     * @return string[]
     */
    public static function levels(): array {
        return [self::LEVEL_OFF, self::LEVEL_BASIC, self::LEVEL_DETAILED];
    }

    /**
     * Returns the stable non-reversible identifier used by progress and analytics.
     */
    public static function media_hash(string $source, string $sourceconfig = ''): string {
        return hash('sha256', clean_param($source, PARAM_PLUGIN) . '|' . $sourceconfig);
    }

    /**
     * Validates and normalizes one telemetry level.
     *
     * @param string $level Requested telemetry level.
     * @return string Normalized telemetry level.
     */
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
            ? self::normalise_event_ranges($payload['skippoints'] ?? [], 1000)
            : [];
        $replaypoints = $level === self::LEVEL_DETAILED
            ? self::normalise_event_ranges($payload['replaypoints'] ?? [], 1000)
            : [];
        $continuousblocks = $level === self::LEVEL_DETAILED
            ? self::normalise_blocks($payload['continuousblocks'] ?? [])
            : [];
        $inactivitygaps = $level === self::LEVEL_DETAILED
            ? self::normalise_gaps($payload['inactivitygaps'] ?? [])
            : [];
        $rates = self::normalise_rates($payload['rates'] ?? []);
        $events = $level === self::LEVEL_DETAILED
            ? self::normalise_events($payload['events'] ?? [])
            : [];

        // Detailed telemetry is client-observed evidence, not an authoritative
        // completion decision. Anchor time to server persistence and bound the
        // amount of accepted playback evidence by elapsed server time.
        $serverelapsed = $record
            ? max(1, min(86400, $now - (int)$record->timecreated + 90))
            : 90;
        $watchtime = min(
            self::bound_int($payload['watchtime'] ?? 0, 0, 604800),
            $serverelapsed
        );

        $maximumrate = max(1.0, min(4.0, (float)($payload['speedavg'] ?? 1.0)));
        foreach (array_keys($rates) as $rate) {
            $maximumrate = max($maximumrate, min(4.0, (float)$rate));
        }
        $ranges = self::limit_range_seconds(
            $ranges,
            max(5.0, $watchtime * $maximumrate + 5.0)
        );
        $continuousblocks = self::limit_block_seconds(
            $continuousblocks,
            max(5.0, $watchtime + 5.0)
        );

        $serverstarted = $record
            ? max(0, min($now, (int)$record->startedat))
            : $now;
        $serverended = (!empty($payload['endedat']) || ($record && !empty($record->endedat)))
            ? $now
            : 0;

        $sessionduration = min(
            self::bound_int($payload['sessionseconds'] ?? 0, 0, 86400),
            $serverelapsed
        );
        $pausedtime = min(
            self::bound_int($payload['pausedtime'] ?? 0, 0, 86400),
            $sessionduration
        );
        $startposition = $record
            ? (int)($record->startposition ?? 0)
            : self::bound_int($payload['startposition'] ?? 0, 0, 604800);
        $endposition = self::bound_int(
            $payload['endposition'] ?? ($payload['dropoff'] ?? 0),
            0,
            604800
        );
        $percentstart = $record
            ? (int)($record->percentstart ?? 0)
            : self::bound_int($payload['_serverpercentstart'] ?? 0, 0, 100);
        $percentend = self::bound_int($payload['_serverpercentend'] ?? $percentstart, 0, 100);
        $ratechanges = self::count_event_type($events, 'playbackrate');
        $receivedended = self::events_have_type($events, 'ended') ? 1 : 0;
        if ($receivedended) {
            $endreason = 'ended';
        } else if (self::events_have_type($events, 'sessionend')) {
            $endreason = 'page_closed_or_navigated';
        } else if ($serverended) {
            $endreason = 'closed_before_end';
        } else {
            $endreason = 'active_or_unclosed';
        }

        // Keep the incremental cursor stable even when a normal AJAX save and
        // the final beacon update the same session within the same second.
        $modifiedtime = $record
            ? max($now, (int)$record->timemodified + 1)
            : $now;

        $values = [
            'source' => clean_param($source, PARAM_PLUGIN),
            'level' => $level,
            'startedat' => $serverstarted,
            'endedat' => $serverended,
            'duration' => self::bound_int($payload['duration'] ?? 0, 0, 604800),
            'sessionduration' => $sessionduration,
            'watchtime' => $watchtime,
            'pausedtime' => $pausedtime,
            'startposition' => $startposition,
            'endposition' => $endposition,
            'percentstart' => $percentstart,
            'percentend' => $percentend,
            'plays' => self::bound_int($payload['plays'] ?? 0, 0, 100000),
            'pauses' => self::bound_int($payload['pauses'] ?? 0, 0, 100000),
            'seeks' => self::bound_int($payload['seeks'] ?? 0, 0, 100000),
            'replays' => self::bound_int($payload['replays'] ?? 0, 0, 100000),
            'skips' => self::bound_int($payload['skips'] ?? 0, 0, 100000),
            'dropoff' => self::bound_int($payload['dropoff'] ?? 0, 0, 604800),
            'maxposition' => self::bound_int($payload['maxposition'] ?? 0, 0, 604800),
            'speedavg' => max(0.1, min(16.0, (float)($payload['speedavg'] ?? 1.0))),
            'ratechanges' => $ratechanges,
            'receivedended' => $receivedended,
            'endreason' => $endreason,
            'ranges' => json_encode($ranges, JSON_THROW_ON_ERROR),
            'pausepoints' => json_encode($pausepoints, JSON_THROW_ON_ERROR),
            'skippoints' => json_encode($skippoints, JSON_THROW_ON_ERROR),
            'replaypoints' => json_encode($replaypoints, JSON_THROW_ON_ERROR),
            'rates' => json_encode($rates, JSON_THROW_ON_ERROR),
            'continuousblocks' => json_encode($continuousblocks, JSON_THROW_ON_ERROR),
            'inactivitygaps' => json_encode($inactivitygaps, JSON_THROW_ON_ERROR),
            'events' => json_encode($events, JSON_THROW_ON_ERROR),
            'timemodified' => $modifiedtime,
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
        $modifiedcursor = false;
        if (array_key_exists('modifiedafter', $filters)) {
            $where[] = '(timemodified > :modifiedafternewer OR ' .
                '(timemodified = :modifiedaftersame AND id > :modifiedafterid))';
            $params['modifiedafternewer'] = max(0, (int)$filters['modifiedafter']);
            $params['modifiedaftersame'] = max(0, (int)$filters['modifiedafter']);
            $params['modifiedafterid'] = max(0, (int)($filters['modifiedafterid'] ?? 0));
            $modifiedcursor = true;
        } else if (!empty($filters['modifiedfrom'])) {
            // Kept for compatibility with existing consumers. New incremental
            // consumers should use modifiedafter + modifiedafterid.
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

        $limit = max(0, min(5000, (int)($filters['limit'] ?? 0)));
        $sort = $modifiedcursor ? 'timemodified ASC, id ASC' : 'startedat ASC, id ASC';
        $records = array_values($DB->get_records_select(
            'local_video_bridge_session',
            implode(' AND ', $where),
            $params,
            $sort,
            '*',
            0,
            $limit
        ));

        // Expose an explicit normalized end signal so consumers do not need
        // to understand the compact event JSON or guess from maxposition.
        foreach ($records as $record) {
            $events = json_decode((string)($record->events ?? ''), true) ?: [];
            $record->reachedend = self::events_have_type($events, 'ended') ? 1 : 0;
        }
        return $records;
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

            $events = json_decode((string)($session->events ?? ''), true) ?: [];
            $playpoints = [];
            foreach ($events as $event) {
                if (is_array($event) && ($event['type'] ?? '') === 'play') {
                    $playpoints[] = (float)($event['position'] ?? 0);
                }
            }
            if ($playpoints) {
                self::add_points($buckets, $playpoints, 'plays', $duration, $bucketcount);
            } else {
                // BASIC and older DETAILED sessions do not contain ordered
                // play events, so retain the historical aggregate fallback.
                $buckets[self::position_to_bucket(0, $duration, $bucketcount)]['plays'] += (int)$session->plays;
            }

            self::add_points(
                $buckets,
                json_decode((string)$session->pausepoints, true) ?: [],
                'pauses',
                $duration,
                $bucketcount
            );
            self::add_range_events(
                $buckets,
                json_decode((string)$session->skippoints, true) ?: [],
                'skips',
                $duration,
                $bucketcount
            );
            self::add_range_events(
                $buckets,
                json_decode((string)$session->replaypoints, true) ?: [],
                'replays',
                $duration,
                $bucketcount
            );

            // A natural ended event is completion, not abandonment. Count only
            // sessions that were closed without the normalized ended signal.
            if ((int)$session->endedat > 0 && empty($session->reachedend)) {
                $dropoff = max(0, min($duration, (int)$session->dropoff));
                $buckets[self::position_to_bucket($dropoff, $duration, $bucketcount)]['dropoffs']++;
            }
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

    /**
     * Counts one normalized event type.
     *
     * @param array $events Normalized event list.
     * @param string $type Event type.
     * @return int
     */
    private static function count_event_type(array $events, string $type): int {
        $count = 0;
        foreach ($events as $event) {
            if (is_array($event) && ($event['type'] ?? '') === $type) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Checks for one normalized event type without exposing event storage to consumers.
     */
    private static function events_have_type(array $events, string $type): bool {
        foreach ($events as $event) {
            if (is_array($event) && ($event['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Adds point events to timeline buckets.
     *
     * @param array $buckets Timeline buckets.
     * @param array $points Playback positions.
     * @param string $field Bucket counter field.
     * @param int $duration Media duration.
     * @param int $bucketcount Number of timeline buckets.
     * @return void
     */
    private static function add_points(
        array &$buckets,
        array $points,
        string $field,
        int $duration,
        int $bucketcount
    ): void {
        foreach (array_slice($points, 0, 1000) as $point) {
            $bucket = self::position_to_bucket((float)$point, $duration, $bucketcount);
            $buckets[$bucket][$field]++;
        }
    }

    /**
     * Adds range events to every intersected timeline bucket.
     *
     * @param array $buckets Timeline buckets.
     * @param array $ranges Position ranges.
     * @param string $field Bucket counter field.
     * @param int $duration Media duration.
     * @param int $bucketcount Number of timeline buckets.
     * @return void
     */
    private static function add_range_events(
        array &$buckets,
        array $ranges,
        string $field,
        int $duration,
        int $bucketcount
    ): void {
        foreach (array_slice($ranges, 0, 1000) as $range) {
            if (!is_array($range) || count($range) < 2) {
                continue;
            }
            $first = self::position_to_bucket((float)$range[0], $duration, $bucketcount);
            $last = self::position_to_bucket((float)$range[1], $duration, $bucketcount);
            for ($bucket = min($first, $last); $bucket <= max($first, $last); $bucket++) {
                $buckets[$bucket][$field]++;
            }
        }
    }

    /**
     * Converts a playback position to a timeline bucket index.
     *
     * @param float $position Playback position.
     * @param int $duration Media duration.
     * @param int $bucketcount Number of buckets.
     * @return int Bucket index.
     */
    private static function position_to_bucket(float $position, int $duration, int $bucketcount): int {
        if ($duration <= 0) {
            return 0;
        }
        return max(0, min($bucketcount - 1, (int)floor(($position / $duration) * $bucketcount)));
    }

    /**
     * Normalizes and merges watched ranges.
     *
     * @param mixed $ranges Browser range data.
     * @param int $limit Maximum accepted ranges.
     * @return array
     */
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

    /**
     * Normalizes ordered event ranges without merging direction.
     *
     * @param mixed $ranges Browser event ranges.
     * @param int $limit Maximum accepted ranges.
     * @return array
     */
    private static function normalise_event_ranges($ranges, int $limit = 1000): array {
        if (!is_array($ranges)) {
            return [];
        }
        $clean = [];
        foreach (array_slice($ranges, 0, $limit) as $range) {
            if (!is_array($range) || count($range) < 2) {
                continue;
            }
            $start = max(0, min(604800, (float)$range[0]));
            $end = max(0, min(604800, (float)$range[1]));
            if (abs($end - $start) > 0.05) {
                $clean[] = [round($start, 2), round($end, 2)];
            }
        }
        return $clean;
    }

    /**
     * Normalizes playback point observations.
     *
     * @param mixed $points Browser point data.
     * @return array
     */
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

    /**
     * Normalizes ordered browser playback events.
     *
     * @param mixed $events Browser event data.
     * @return array
     */
    private static function normalise_events($events): array {
        if (!is_array($events)) {
            return [];
        }
        $allowed = [
            'sessionstart',
            'play',
            'pause',
            'seek',
            'playbackrate',
            'waiting',
            'playing',
            'ended',
            'visibilitychange',
            'sessionend',
        ];
        $clean = [];
        foreach (array_slice($events, 0, 500) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $type = clean_param((string)($event['type'] ?? ''), PARAM_ALPHANUMEXT);
            if (!in_array($type, $allowed, true)) {
                continue;
            }
            $item = [
                'type' => $type,
                'position' => round(max(0, min(604800, (float)($event['position'] ?? 0))), 2),
            ];
            if ($type === 'seek') {
                $from = max(0, min(604800, (float)($event['from'] ?? 0)));
                $to = max(0, min(604800, (float)($event['to'] ?? 0)));
                $item['from'] = round($from, 2);
                $item['to'] = round($to, 2);
                $item['distance'] = round(abs($to - $from), 2);
                $item['direction'] = $to >= $from ? 'forward' : 'backward';
            } else if ($type === 'playbackrate') {
                $item['from'] = max(0.1, min(16.0, (float)($event['from'] ?? 1)));
                $item['to'] = max(0.1, min(16.0, (float)($event['to'] ?? 1)));
            } else if ($type === 'visibilitychange') {
                $item['state'] = (($event['state'] ?? '') === 'hidden') ? 'hidden' : 'visible';
            }
            $clean[] = $item;
        }
        return $clean;
    }

    /**
     * Normalizes playback-rate duration observations.
     *
     * @param mixed $rates Browser rate data.
     * @return array
     */
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

    /**
     * Normalises continuous playback blocks as [start position, end position, real seconds].
     *
     * @param mixed $blocks Browser observations.
     * @return array
     */
    private static function normalise_blocks($blocks): array {
        if (!is_array($blocks)) {
            return [];
        }
        $clean = [];
        foreach (array_slice($blocks, 0, 500) as $block) {
            if (!is_array($block) || count($block) < 3) {
                continue;
            }
            $start = max(0, min(604800, (float)$block[0]));
            $end = max(0, min(604800, (float)$block[1]));
            $seconds = max(0, min(604800, (float)$block[2]));
            if ($seconds >= 0.25) {
                $clean[] = [round($start, 2), round($end, 2), round($seconds, 2)];
            }
        }
        return $clean;
    }

    /**
     * Normalises real-time inactivity gaps observed by the player.
     *
     * @param mixed $gaps Browser observations.
     * @return array
     */
    private static function normalise_gaps($gaps): array {
        if (!is_array($gaps)) {
            return [];
        }
        $clean = [];
        foreach (array_slice($gaps, 0, 500) as $gap) {
            $seconds = max(0, min(604800, (float)$gap));
            if ($seconds >= 0.25) {
                $clean[] = round($seconds, 2);
            }
        }
        return $clean;
    }

    /**
     * Limits accepted unique watched ranges to a server-plausible amount of media time.
     *
     * @param array $ranges Normalized ranges.
     * @param float $maximumseconds Maximum accepted unique media seconds.
     * @return array
     */
    private static function limit_range_seconds(array $ranges, float $maximumseconds): array {
        $remaining = max(0.0, $maximumseconds);
        $limited = [];
        foreach ($ranges as $range) {
            if ($remaining <= 0 || !is_array($range) || count($range) < 2) {
                break;
            }
            $length = max(0.0, (float)$range[1] - (float)$range[0]);
            if ($length <= 0) {
                continue;
            }
            $accepted = min($length, $remaining);
            $limited[] = [
                round((float)$range[0], 2),
                round((float)$range[0] + $accepted, 2),
            ];
            $remaining -= $accepted;
        }
        return $limited;
    }

    /**
     * Bounds reported continuous playback blocks by accepted real playback time.
     *
     * @param array $blocks Normalized blocks.
     * @param float $maximumseconds Maximum accepted real seconds.
     * @return array
     */
    private static function limit_block_seconds(array $blocks, float $maximumseconds): array {
        $remaining = max(0.0, $maximumseconds);
        $limited = [];
        foreach ($blocks as $block) {
            if ($remaining <= 0 || !is_array($block) || count($block) < 3) {
                break;
            }
            $seconds = min(max(0.0, (float)$block[2]), $remaining);
            if ($seconds < 0.25) {
                continue;
            }
            $limited[] = [(float)$block[0], (float)$block[1], round($seconds, 2)];
            $remaining -= $seconds;
        }
        return $limited;
    }

    /**
     * Bounds an integer value to the accepted range.
     *
     * @param mixed $value Input value.
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     * @return int Bounded integer.
     */
    private static function bound_int($value, int $min, int $max): int {
        return max($min, min($max, (int)$value));
    }
}
