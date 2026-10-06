<?php
// This file is part of Moodle - http://moodle.org/.
/**
 * AJAX endpoint for authoritative progress and compact telemetry batches.
 *
 * @package local_video_bridge
 */
namespace local_video_bridge\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_video_bridge\progress\manager as progress_manager;
use local_video_bridge\telemetry\manager as telemetry_manager;

final class save_progress extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Module context id'),
            'component' => new external_value(PARAM_COMPONENT, 'Consumer component'),
            'itemid' => new external_value(PARAM_INT, 'Consumer activity instance id'),
            'source' => new external_value(PARAM_PLUGIN, 'Video source provider'),
            'mediahash' => new external_value(PARAM_ALPHANUM, 'Stable SHA-256 media hash'),
            'currenttime' => new external_value(PARAM_INT, 'Current player position'),
            'duration' => new external_value(PARAM_INT, 'Observed media duration'),
            'buckets' => new external_value(PARAM_RAW, 'JSON array of newly watched buckets'),
            'telemetry' => new external_value(PARAM_RAW, 'Optional compact telemetry JSON', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        int $contextid,
        string $component,
        int $itemid,
        string $source,
        string $mediahash,
        int $currenttime,
        int $duration,
        string $buckets,
        string $telemetry = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'component' => $component,
            'itemid' => $itemid,
            'source' => $source,
            'mediahash' => $mediahash,
            'currenttime' => $currenttime,
            'duration' => $duration,
            'buckets' => $buckets,
            'telemetry' => $telemetry,
        ]);

        $context = \context::instance_by_id($params['contextid'], MUST_EXIST);
        if (!$context instanceof context_module) {
            throw new invalid_parameter_exception('Progress requires a module context.');
        }
        self::validate_context($context);

        $cm = get_coursemodule_from_id(null, $context->instanceid, 0, false, MUST_EXIST);
        require_login($cm->course, false, $cm);

        $expectedcomponent = 'mod_' . $cm->modname;
        if ($params['component'] !== $expectedcomponent || $params['itemid'] !== (int)$cm->instance) {
            throw new invalid_parameter_exception('Progress consumer does not match the module context.');
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $params['mediahash'])) {
            throw new invalid_parameter_exception('Invalid media hash.');
        }

        $decodedbuckets = json_decode($params['buckets'], true);
        if (!is_array($decodedbuckets) || count($decodedbuckets) > progress_manager::MAX_BUCKETS) {
            throw new invalid_parameter_exception('Invalid progress bucket list.');
        }

        $previousprogress = progress_manager::get_progress(
            $context->id,
            $params['component'],
            $params['itemid'],
            $params['mediahash'],
            (int)$USER->id
        );
        $previouspercent = $previousprogress ? (int)$previousprogress->percent : 0;

        $progress = progress_manager::save(
            $context->id,
            $params['component'],
            $params['itemid'],
            $params['source'],
            $params['mediahash'],
            (int)$USER->id,
            $params['currenttime'],
            $params['duration'],
            $decodedbuckets
        );

        if ($params['telemetry'] !== '') {
            if (strlen($params['telemetry']) > 65535) {
                throw new invalid_parameter_exception('Video Bridge telemetry payload is too large.');
            }
            $decodedtelemetry = json_decode($params['telemetry'], true);
            if (!is_array($decodedtelemetry)) {
                throw new invalid_parameter_exception('Invalid Video Bridge telemetry payload.');
            }
            $decodedtelemetry['_serverpercentstart'] = $previouspercent;
            $decodedtelemetry['_serverpercentend'] = (int)$progress->percent;
            telemetry_manager::save_session(
                $context,
                $params['component'],
                $params['itemid'],
                $params['source'],
                $params['mediahash'],
                (int)$USER->id,
                $decodedtelemetry
            );
        }

        \local_video_bridge\event\analytics_updated::create([
            'objectid' => (int)$progress->id,
            'context' => $context,
            'relateduserid' => (int)$USER->id,
            'other' => [
                'component' => $params['component'],
                'itemid' => $params['itemid'],
                'source' => $params['source'],
                'mediahash' => $params['mediahash'],
                'percent' => (int)$progress->percent,
            ],
        ])->trigger();

        return [
            'success' => true,
            'currenttime' => (int)$progress->currenttime,
            'duration' => (int)$progress->duration,
            'percent' => (int)$progress->percent,
            'map' => array_map('intval', json_decode((string)$progress->map, true) ?: []),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether persistence succeeded'),
            'currenttime' => new external_value(PARAM_INT, 'Authoritative current position'),
            'duration' => new external_value(PARAM_INT, 'Stored media duration'),
            'percent' => new external_value(PARAM_INT, 'Authoritative watched percentage'),
            'map' => new \core_external\external_multiple_structure(
                new external_value(PARAM_INT, 'Watched bucket')
            ),
        ]);
    }
}
