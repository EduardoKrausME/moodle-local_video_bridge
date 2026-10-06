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
 * Shared video progress manager.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\progress;

use context_module;
use local_video_bridge\media\config as media_config;
use stdClass;

/**
 * Stores the viewing map once for every Video Bridge consumer.
 */
class manager {
    /** Maximum number of normalized buckets in a viewing map. */
    public const MAX_BUCKETS = 100;

    /** Minimum time between normal browser saves, in milliseconds. */
    public const SAVE_INTERVAL_MS = 60000;

    /**
     * Builds browser-safe progress configuration for one module video.
     *
     * @param context_module $context Module context.
     * @param string $source Source short name.
     * @param string $sourceconfig Normalized source configuration.
     * @param string $telemetrylevel Telemetry detail level.
     * @return array
     */
    public static function build_config(
        context_module $context,
        string $source,
        string $sourceconfig = '',
        string $telemetrylevel = \local_video_bridge\analytics::LEVEL_BASIC
    ): array {
        global $CFG, $USER;

        $telemetrylevel = \local_video_bridge\analytics::normalise_level($telemetrylevel);
        if (!isloggedin() || isguestuser() || $telemetrylevel === \local_video_bridge\analytics::LEVEL_OFF) {
            return ['enabled' => false, 'telemetrylevel' => $telemetrylevel];
        }

        $cm = get_coursemodule_from_id(null, $context->instanceid, 0, false, MUST_EXIST);
        $component = 'mod_' . $cm->modname;
        $itemid = (int)$cm->instance;
        $mediahash = \local_video_bridge\analytics::media_hash($source, $sourceconfig);
        $progress = self::get_or_create(
            $context->id,
            $component,
            $itemid,
            $source,
            $mediahash,
            (int)$USER->id
        );

        return [
            'enabled' => true,
            'endpoint' => $CFG->wwwroot . '/local/video_bridge/progress.php',
            'ajaxmethod' => 'local_video_bridge_save_progress',
            'sesskey' => sesskey(),
            'contextid' => $context->id,
            'component' => $component,
            'itemid' => $itemid,
            'source' => $source,
            'mediahash' => $mediahash,
            'currenttime' => (int)$progress->currenttime,
            'duration' => (int)$progress->duration,
            'percent' => (int)$progress->percent,
            'map' => self::decode_map((string)$progress->map),
            'saveinterval' => self::SAVE_INTERVAL_MS,
            'label' => get_string('progressmap', 'local_video_bridge'),
            'telemetrylevel' => $telemetrylevel,
        ];
    }

    /**
     * Builds progress configuration for one media item in a multi-media consumer.
     *
     * @param context_module $context Module context.
     * @param consumer $consumer Consumer identity.
     * @param media_config $media Media configuration.
     * @param string $telemetrylevel Telemetry detail level.
     * @return array
     */
    public static function build_config_for_media(
        context_module $context,
        consumer $consumer,
        media_config $media,
        string $telemetrylevel = \local_video_bridge\analytics::LEVEL_BASIC
    ): array {
        global $CFG, $USER;

        $telemetrylevel = \local_video_bridge\analytics::normalise_level($telemetrylevel);
        if (!isloggedin() || isguestuser() || $telemetrylevel === \local_video_bridge\analytics::LEVEL_OFF) {
            return ['enabled' => false, 'telemetrylevel' => $telemetrylevel];
        }

        $mediahash = $media->get_mediahash();
        $progress = self::get_or_create(
            $context->id,
            $consumer->get_component(),
            $consumer->get_itemid(),
            $media->get_source(),
            $mediahash,
            (int)$USER->id
        );

        return [
            'enabled' => true,
            'endpoint' => $CFG->wwwroot . '/local/video_bridge/progress.php',
            'sesskey' => sesskey(),
            'contextid' => $context->id,
            'component' => $consumer->get_component(),
            'itemid' => $consumer->get_itemid(),
            'mediaid' => $media->get_mediaid(),
            'source' => $media->get_source(),
            'mediahash' => $mediahash,
            'currenttime' => (int)$progress->currenttime,
            'duration' => (int)$progress->duration,
            'percent' => (int)$progress->percent,
            'map' => self::decode_map((string)$progress->map),
            'saveinterval' => self::SAVE_INTERVAL_MS,
            'label' => get_string('progressmap', 'local_video_bridge'),
            'telemetrylevel' => $telemetrylevel,
        ];
    }

    /**
     * Returns consolidated progress for reports or consumer activity logic.
     *
     * @param int $contextid Context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer instance id.
     * @param string $mediahash Media hash.
     * @param int $userid User id.
     * @return stdClass|null
     */
    public static function get_progress(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        int $userid
    ): ?stdClass {
        global $DB;

        $record = $DB->get_record('local_video_bridge_progress', [
            'contextid' => $contextid,
            'component' => $component,
            'itemid' => $itemid,
            'mediahash' => $mediahash,
            'userid' => $userid,
        ]);

        return $record ?: null;
    }

    /**
     * Loads a user x media progress matrix with one database query.
     *
     * The returned array is indexed as [userid][mediahash].
     *
     * @param int $contextid Context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer activity instance id.
     * @param array $mediahashes Media hashes.
     * @param array $userids User ids.
     * @return array Nested progress records.
     */
    public static function get_progress_bulk(
        int $contextid,
        string $component,
        int $itemid,
        array $mediahashes,
        array $userids
    ): array {
        global $DB;

        $mediahashes = array_values(array_unique(array_filter(array_map('strval', $mediahashes))));
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (!$mediahashes || !$userids) {
            return [];
        }

        [$hashsql, $hashparams] = $DB->get_in_or_equal($mediahashes, SQL_PARAMS_NAMED, 'mh');
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $params = [
            'contextid' => $contextid,
            'component' => clean_param($component, PARAM_COMPONENT),
            'itemid' => $itemid,
        ] + $hashparams + $userparams;

        $sql = "SELECT *
                  FROM {local_video_bridge_progress}
                 WHERE contextid = :contextid
                   AND component = :component
                   AND itemid = :itemid
                   AND mediahash {$hashsql}
                   AND userid {$usersql}";

        $result = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $result[(int)$record->userid][(string)$record->mediahash] = $record;
        }
        return $result;
    }

    /**
     * Returns the stable media identity used by Video Bridge progress rows.
     *
     * @param string $source Source short name.
     * @param string $sourceconfig Normalized source configuration.
     * @return string SHA-256 media hash.
     */
    public static function media_hash(string $source, string $sourceconfig = ''): string {
        return hash('sha256', $source . '|' . $sourceconfig);
    }

    /**
     * Returns progress rows for one consumer activity without exposing table details to consumers.
     *
     * @param int $contextid Module context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer activity instance id.
     * @param string $mediahash Stable media hash.
     * @param array|null $userids Optional learner ids to restrict the result.
     * @return stdClass[] Progress rows indexed by record id.
     */
    public static function get_activity_progress(
        int $contextid,
        string $component,
        int $itemid,
        string $mediahash,
        ?array $userids = null
    ): array {
        global $DB;

        $baseparams = [
            'contextid' => $contextid,
            'component' => clean_param($component, PARAM_COMPONENT),
            'itemid' => $itemid,
            'mediahash' => $mediahash,
        ];
        $basewhere = 'contextid = :contextid AND component = :component ' .
            'AND itemid = :itemid AND mediahash = :mediahash';

        if ($userids === null) {
            return $DB->get_records_select(
                'local_video_bridge_progress',
                $basewhere,
                $baseparams,
                'userid ASC'
            );
        }

        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (!$userids) {
            return [];
        }

        $records = [];
        foreach (array_chunk($userids, 500) as $chunkindex => $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal(
                $chunk,
                SQL_PARAMS_NAMED,
                'bridgeuser' . $chunkindex
            );
            foreach ($DB->get_records_select(
                'local_video_bridge_progress',
                $basewhere . " AND userid {$insql}",
                $baseparams + $inparams,
                'userid ASC'
            ) as $id => $record) {
                $records[$id] = $record;
            }
        }

        uasort($records, static fn(stdClass $a, stdClass $b): int =>
            (int)$a->userid <=> (int)$b->userid
        );
        return $records;
    }

    /**
     * Deletes bridge-owned progress and session telemetry for one consumer.
     *
     * Consumer plugins should call this instead of deleting Video Bridge tables directly.
     *
     * @param int $contextid Module context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer activity instance id.
     * @return void
     */
    public static function delete_consumer(
        int $contextid,
        string $component,
        int $itemid
    ): void {
        global $DB;

        $params = [
            'contextid' => $contextid,
            'component' => clean_param($component, PARAM_COMPONENT),
            'itemid' => $itemid,
        ];
        $DB->delete_records('local_video_bridge_progress', $params);
        $DB->delete_records('local_video_bridge_session', $params);
    }

    /**
     * Merges a batch of watched buckets and recalculates authoritative progress.
     *
     * @param int $contextid Context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer instance id.
     * @param string $source Source short name.
     * @param string $mediahash Media hash.
     * @param int $userid User id.
     * @param int $currenttime Last player position.
     * @param int $duration Video duration.
     * @param array $buckets Watched buckets accumulated in browser memory.
     * @return stdClass Updated progress.
     */
    public static function save(
        int $contextid,
        string $component,
        int $itemid,
        string $source,
        string $mediahash,
        int $userid,
        int $currenttime,
        int $duration,
        array $buckets
    ): stdClass {
        global $DB;

        $duration = max(0, min(604800, $duration));
        $currenttime = max(0, $currenttime);
        if ($duration > 0) {
            $currenttime = min($currenttime, $duration);
        }

        $record = self::get_or_create(
            $contextid,
            $component,
            $itemid,
            $source,
            $mediahash,
            $userid
        );

        $duration = max($duration, (int)$record->duration);
        $length = self::progress_length($duration);
        $stored = self::decode_map((string)$record->map);
        $watched = array_fill_keys($stored, true);

        $incoming = [];
        foreach ($buckets as $bucket) {
            $bucket = (int)$bucket;
            if ($bucket >= 1 && $bucket <= $length && empty($watched[$bucket])) {
                $incoming[] = $bucket;
            }
        }

        $currentbucket = self::bucket_for_position($currenttime, $duration);
        if ($currentbucket > 0 && empty($watched[$currentbucket]) && !in_array($currentbucket, $incoming, true)) {
            $incoming[] = $currentbucket;
        }

        // Browser data is useful, but it is not authoritative. Bound the number
        // of newly accepted buckets by elapsed server time, with room for high
        // playback rates and coarse player callbacks.
        $elapsed = max(1, min(300, time() - (int)$record->timemodified));
        $bucketseconds = $length > 0 ? max(1, $duration / $length) : 1;
        $maxnew = max(1, (int)ceil(($elapsed * 4) / $bucketseconds) + 2);
        $incoming = array_slice(array_values(array_unique($incoming)), 0, $maxnew);

        foreach ($incoming as $bucket) {
            $watched[$bucket] = true;
        }

        $map = array_map('intval', array_keys($watched));
        sort($map, SORT_NUMERIC);

        $record->source = clean_param($source, PARAM_PLUGIN);
        $record->currenttime = $currenttime;
        $record->duration = $duration;
        $record->map = json_encode($map, JSON_THROW_ON_ERROR);
        $record->percent = $length > 0
            ? min(100, (int)floor((count($map) / $length) * 100))
            : 0;
        $record->timemodified = time();

        $DB->update_record('local_video_bridge_progress', $record);
        return $record;
    }

    /**
     * Converts one playback position into a normalized viewing-map bucket.
     *
     * @param int $currenttime Playback position.
     * @param int $duration Video duration.
     * @return int Bucket from 1 to progress_length(), or zero when unavailable.
     */
    public static function bucket_for_position(int $currenttime, int $duration): int {
        $length = self::progress_length($duration);
        if ($length <= 0 || $currenttime <= 0) {
            return 0;
        }

        if ($length < self::MAX_BUCKETS) {
            return max(1, min($length, $currenttime));
        }

        return max(
            1,
            min($length, (int)floor(($currenttime / max(1, $duration)) * $length))
        );
    }

    /**
     * Returns the number of buckets used by a duration.
     *
     * @param int $duration Video duration.
     * @return int
     */
    public static function progress_length(int $duration): int {
        if ($duration <= 0) {
            return 0;
        }
        return max(1, min(self::MAX_BUCKETS, (int)floor($duration)));
    }

    /**
     * Returns or creates one consolidated progress row.
     *
     * @param int $contextid Context id.
     * @param string $component Consumer component.
     * @param int $itemid Consumer instance id.
     * @param string $source Source short name.
     * @param string $mediahash Media hash.
     * @param int $userid User id.
     * @return stdClass
     */
    private static function get_or_create(
        int $contextid,
        string $component,
        int $itemid,
        string $source,
        string $mediahash,
        int $userid
    ): stdClass {
        global $DB;

        $params = [
            'contextid' => $contextid,
            'component' => $component,
            'itemid' => $itemid,
            'mediahash' => $mediahash,
            'userid' => $userid,
        ];

        $record = $DB->get_record('local_video_bridge_progress', $params);
        if ($record) {
            return $record;
        }

        $now = time();
        $record = (object)($params + [
            'source' => clean_param($source, PARAM_PLUGIN),
            'currenttime' => 0,
            'duration' => 0,
            'percent' => 0,
            'map' => '[]',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        try {
            $record->id = $DB->insert_record('local_video_bridge_progress', $record);
            return $record;
        } catch (\dml_write_exception $exception) {
            // A parallel tab may have created the unique row first.
            return $DB->get_record('local_video_bridge_progress', $params, '*', MUST_EXIST);
        }
    }

    /**
     * Decodes and normalizes the persisted bucket list.
     *
     * @param string $json Persisted JSON map.
     * @return array
     */
    private static function decode_map(string $json): array {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $bucket) {
            $bucket = (int)$bucket;
            if ($bucket >= 1 && $bucket <= self::MAX_BUCKETS) {
                $map[$bucket] = $bucket;
            }
        }

        ksort($map, SORT_NUMERIC);
        return array_values($map);
    }
}
