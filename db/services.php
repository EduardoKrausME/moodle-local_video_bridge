<?php
defined('MOODLE_INTERNAL') || die;

$functions = [
    'local_video_bridge_save_progress' => [
        'classname' => '\\local_video_bridge\\external\\save_progress',
        'methodname' => 'execute',
        'description' => 'Persist an authoritative progress batch and optional compact playback telemetry.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
