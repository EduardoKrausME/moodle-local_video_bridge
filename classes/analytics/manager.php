<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Consolidated analytics facts for Video Bridge consumers.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\analytics;

use local_video_bridge\analytics as session_analytics;
use local_video_bridge\progress\manager as progress_manager;

/**
 * Returns provider-independent playback facts without pedagogical interpretation.
 */
class manager {
    /**
     * Builds the media hash used by Video Bridge storage.
     *
     * @param string $source Source identifier.
     * @param string $sourceconfig Normalized source configuration.
     * @return string
     */
    public static function media_hash(string $source, string $sourceconfig = ''): string {
        return hash('sha256', $source . '|' . $sourceconfig);
    }

    /**
     * Returns consolidated facts for one learner and media item.
     *
     * @param int $contextid Module context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer instance id.
     * @param string $mediahash Video Bridge media hash.
     * @param int $userid Learner id.
     * @param array $filters Optional session filters such as from/to timestamps.
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
        $sessions = session_analytics::get_session_metrics(
            $contextid,
            $component,
            $itemid,
            $mediahash,
            $userid,
            $filters
        );

        $progress = progress_manager::get_progress(
            $contextid,
            $component,
            $itemid,
            $mediahash,
            $userid
        );

        $facts = [
            'percent_watched' => $progress ? (float)$progress->percent : 0.0,
            'unique_watch_time' => 0.0,
            'playback_time' => 0.0,
            'real_session_time' => 0.0,
            'maximum_playback_rate' => 1.0,
            'average_playback_rate' => 1.0,
            'seeks_forward' => 0,
            'seeks_backward' => 0,
            'largest_forward_seek' => 0.0,
            'pause_count' => 0,
            'session_count' => count($sessions),
            'reached_end' => false,
            'watched_ranges' => [],
            'continuous_blocks' => [],
            'inactivity_gaps' => [],
            'duration' => $progress ? (float)$progress->duration : 0.0,
            'current_position' => $progress ? (float)$progress->currenttime : 0.0,
            'sessions' => [],
            'latest_session' => null,
        ];

        $allranges = [];
        $weightedrate = 0.0;
        $rateweight = 0.0;

        foreach ($sessions as $session) {
            $sessionfacts = self::session_facts($session);
            $facts['sessions'][] = $sessionfacts;
            $facts['latest_session'] = $sessionfacts;
            $facts['duration'] = max($facts['duration'], $sessionfacts['duration']);
            $facts['playback_time'] += $sessionfacts['playback_time'];
            $facts['real_session_time'] += $sessionfacts['real_session_time'];
            $facts['maximum_playback_rate'] = max(
                $facts['maximum_playback_rate'],
                $sessionfacts['maximum_playback_rate']
            );
            $facts['seeks_forward'] += $sessionfacts['seeks_forward'];
            $facts['seeks_backward'] += $sessionfacts['seeks_backward'];
            $facts['largest_forward_seek'] = max(
                $facts['largest_forward_seek'],
                $sessionfacts['largest_forward_seek']
            );
            $facts['pause_count'] += $sessionfacts['pause_count'];
            $facts['reached_end'] = $facts['reached_end'] || $sessionfacts['reached_end'];
            $facts['continuous_blocks'] = array_merge(
                $facts['continuous_blocks'],
                $sessionfacts['continuous_blocks']
            );
            $facts['inactivity_gaps'] = array_merge(
                $facts['inactivity_gaps'],
                $sessionfacts['inactivity_gaps']
            );
            $allranges = array_merge($allranges, $sessionfacts['watched_ranges']);

            $weight = max(0.0, $sessionfacts['playback_time']);
            $weightedrate += $sessionfacts['average_playback_rate'] * $weight;
            $rateweight += $weight;
        }

        $facts['watched_ranges'] = self::merge_ranges($allranges);
        $facts['unique_watch_time'] = self::ranges_seconds($facts['watched_ranges']);

        if ($facts['duration'] > 0 && $facts['watched_ranges']) {
            $facts['percent_watched'] = min(
                100.0,
                round(($facts['unique_watch_time'] / $facts['duration']) * 100, 2)
            );
        }
        if ($rateweight > 0) {
            $facts['average_playback_rate'] = round($weightedrate / $rateweight, 3);
        }

        return $facts;
    }


    /**
     * Returns the stable object contract shared by score/reporting consumers.
     *
     * @param int $contextid Module context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer instance id.
     * @param string $mediahash Video Bridge media hash.
     * @param int $userid Learner id.
     * @param array $filters Optional session filters.
     * @return metrics
     */
    public static function get_user_metrics(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        int $userid,
        array $filters = []
    ): metrics {
        return metrics::from_facts(self::get_facts(
            $contextid,
            $component,
            $itemid,
            $mediahash,
            $userid,
            $filters
        ));
    }

    /**
     * Normalizes one compact session into facts useful to consumers.
     *
     * @param object $session Stored session row.
     * @return array
     */
    private static function session_facts(object $session): array {
        $ranges = self::merge_ranges(self::decode_array($session->ranges ?? '[]'));
        $rates = self::decode_array($session->rates ?? '[]');
        $skips = self::decode_array($session->skippoints ?? '[]');
        $replays = self::decode_array($session->replaypoints ?? '[]');
        $continuous = self::decode_array($session->continuousblocks ?? '[]');
        $gaps = self::decode_array($session->inactivitygaps ?? '[]');
        $events = self::decode_array($session->events ?? '[]');
        $receivedended = false;
        foreach ($events as $event) {
            if (is_array($event) && ($event['type'] ?? '') === 'ended') {
                $receivedended = true;
                break;
            }
        }

        $maximumrate = max(1.0, (float)($session->speedavg ?? 1.0));
        $weightedrate = 0.0;
        $rateweight = 0.0;
        foreach ($rates as $rate => $seconds) {
            $ratevalue = max(0.1, min(16.0, (float)$rate));
            $secondsvalue = max(0.0, (float)$seconds);
            $maximumrate = max($maximumrate, $ratevalue);
            $weightedrate += $ratevalue * $secondsvalue;
            $rateweight += $secondsvalue;
        }

        $largestseek = 0.0;
        foreach ($skips as $skip) {
            if (is_array($skip) && count($skip) >= 2) {
                $largestseek = max($largestseek, max(0.0, (float)$skip[1] - (float)$skip[0]));
            }
        }

        $duration = max(0.0, (float)($session->duration ?? 0));
        $endposition = max(
            (float)($session->dropoff ?? 0),
            (float)($session->maxposition ?? 0)
        );
        $ended = (int)($session->endedat ?? 0) > 0;
        $serverstart = (int)($session->timecreated ?? 0);
        $serverend = (int)($session->timemodified ?? 0);
        $realtime = !empty($session->sessionduration)
            ? max(0, min(86400, (int)$session->sessionduration))
            : max(0, min(86400, $serverend - $serverstart));

        return [
            'session_id' => (string)($session->sessionid ?? ''),
            'started_at' => (int)($session->startedat ?? 0),
            'ended_at' => (int)($session->endedat ?? 0),
            'duration' => $duration,
            'playback_time' => max(0.0, (float)($session->watchtime ?? 0)),
            'real_session_time' => (float)$realtime,
            'paused_time' => max(0.0, (float)($session->pausedtime ?? 0)),
            'start_position' => max(0.0, (float)($session->startposition ?? 0)),
            'end_position' => max(0.0, (float)($session->endposition ?? $session->dropoff ?? 0)),
            'percent_start' => max(0, min(100, (int)($session->percentstart ?? 0))),
            'percent_end' => max(0, min(100, (int)($session->percentend ?? 0))),
            'rate_changes' => max(0, (int)($session->ratechanges ?? 0)),
            'end_reason' => (string)($session->endreason ?? ''),
            'maximum_playback_rate' => round($maximumrate, 3),
            'average_playback_rate' => $rateweight > 0
                ? round($weightedrate / $rateweight, 3)
                : max(0.1, (float)($session->speedavg ?? 1.0)),
            'seeks_forward' => count($skips),
            'seeks_backward' => count($replays),
            'largest_forward_seek' => round($largestseek, 3),
            'pause_count' => max(0, (int)($session->pauses ?? 0)),
            'reached_end' => $receivedended,
            'watched_ranges' => $ranges,
            'continuous_blocks' => is_array($continuous) ? $continuous : [],
            'inactivity_gaps' => array_values(array_filter(array_map('floatval', is_array($gaps) ? $gaps : []))),
            'events' => $events,
            'dropoff' => max(0.0, (float)($session->dropoff ?? 0)),
            'max_position' => max(0.0, (float)($session->maxposition ?? 0)),
        ];
    }

    /**
     * Decodes a JSON array safely.
     *
     * @param mixed $json JSON string.
     * @return array
     */
    private static function decode_array($json): array {
        if (is_array($json)) {
            return $json;
        }
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Merges timeline ranges.
     *
     * @param array $ranges Raw ranges.
     * @return array
     */
    public static function merge_ranges(array $ranges): array {
        $clean = [];
        foreach ($ranges as $range) {
            if (!is_array($range) || count($range) < 2) {
                continue;
            }
            $start = max(0.0, (float)$range[0]);
            $end = max($start, (float)$range[1]);
            if ($end > $start) {
                $clean[] = [round($start, 3), round($end, 3)];
            }
        }
        usort($clean, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($clean as $range) {
            $last = count($merged) - 1;
            if ($last >= 0 && $range[0] <= $merged[$last][1] + 0.05) {
                $merged[$last][1] = max($merged[$last][1], $range[1]);
            } else {
                $merged[] = $range;
            }
        }
        return $merged;
    }

    /**
     * Returns total unique seconds represented by ranges.
     *
     * @param array $ranges Normalized ranges.
     * @return float
     */
    public static function ranges_seconds(array $ranges): float {
        $seconds = 0.0;
        foreach (self::merge_ranges($ranges) as $range) {
            $seconds += $range[1] - $range[0];
        }
        return round($seconds, 3);
    }

    /**
     * Calculates coverage of one required segment.
     *
     * @param array $ranges Watched ranges.
     * @param float $start Segment start.
     * @param float $end Segment end.
     * @return float Percentage from 0 to 100.
     */
    public static function segment_coverage(array $ranges, float $start, float $end): float {
        if ($end <= $start) {
            return 0.0;
        }
        $covered = 0.0;
        foreach (self::merge_ranges($ranges) as $range) {
            $overlap = max(0.0, min($end, $range[1]) - max($start, $range[0]));
            $covered += $overlap;
        }
        return min(100.0, round(($covered / ($end - $start)) * 100, 2));
    }
}
