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
 * Provider-independent consolidated video metrics.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\analytics;

/**
 * Stable provider-independent metrics returned to Video Bridge consumers.
 *
 * Array keys intentionally remain camelCase in to_array() for compatibility
 * with existing consumers, while PHP properties follow Moodle naming rules.
 */
final class metrics {
    /** @var int Authoritative watched percentage. */
    public int $percent = 0;

    /** @var int Media duration in seconds. */
    public int $duration = 0;

    /** @var int Unique watched time in seconds. */
    public int $unique_watch_time = 0;

    /** @var int Total playback time in seconds. */
    public int $playback_time = 0;

    /** @var int Total real session time in seconds. */
    public int $session_time = 0;

    /** @var int Number of playback sessions. */
    public int $sessions = 0;

    /** @var int Number of pauses. */
    public int $pause_count = 0;

    /** @var int Number of seeks. */
    public int $seek_count = 0;

    /** @var int Number of replay/backward-seek actions. */
    public int $replay_count = 0;

    /** @var float Maximum observed playback rate. */
    public float $max_rate = 1.0;

    /** @var float Average observed playback rate. */
    public float $average_rate = 1.0;

    /** @var bool Whether playback reached the end. */
    public bool $reached_end = false;

    /** @var array Consolidated watched ranges. */
    public array $watched_ranges = [];

    /** @var int Last observed playback position. */
    public int $last_position = 0;

    /** @var float Playback regularity percentage. */
    public float $regularity = 0.0;

    /** @var int Number of forward seeks. */
    public int $seeks_forward = 0;

    /** @var int Number of backward seeks. */
    public int $seeks_backward = 0;

    /** @var float Largest observed forward seek in seconds. */
    public float $largest_forward_seek = 0.0;

    /** @var array Continuous playback blocks. */
    public array $continuous_blocks = [];

    /** @var array Inactivity gaps. */
    public array $inactivity_gaps = [];

    /**
     * Builds metrics from provider-independent analytics facts.
     *
     * @param array $facts Normalized analytics facts.
     * @return self
     */
    public static function from_facts(array $facts): self {
        $value = new self();
        $value->percent = (int)round(max(0, min(100, (float)($facts['percent_watched'] ?? 0))));
        $value->duration = (int)round(max(0, (float)($facts['duration'] ?? 0)));
        $value->unique_watch_time = (int)round(max(0, (float)($facts['unique_watch_time'] ?? 0)));
        $value->playback_time = (int)round(max(0, (float)($facts['playback_time'] ?? 0)));
        $value->session_time = (int)round(max(0, (float)($facts['real_session_time'] ?? 0)));
        $value->sessions = max(0, (int)($facts['session_count'] ?? 0));
        $value->pause_count = max(0, (int)($facts['pause_count'] ?? 0));
        $value->seeks_forward = max(0, (int)($facts['seeks_forward'] ?? 0));
        $value->seeks_backward = max(0, (int)($facts['seeks_backward'] ?? 0));
        $value->seek_count = $value->seeks_forward + $value->seeks_backward;
        $value->replay_count = $value->seeks_backward;
        $value->max_rate = max(0.1, (float)($facts['maximum_playback_rate'] ?? 1));
        $value->average_rate = max(0.1, (float)($facts['average_playback_rate'] ?? 1));
        $value->reached_end = !empty($facts['reached_end']);
        $value->watched_ranges = is_array($facts['watched_ranges'] ?? null) ? $facts['watched_ranges'] : [];
        $value->last_position = (int)round(max(0, (float)($facts['current_position'] ?? 0)));
        $value->regularity = $value->session_time > 0
            ? round(min(100, ($value->playback_time / $value->session_time) * 100), 2)
            : 0.0;
        $value->largest_forward_seek = max(0, (float)($facts['largest_forward_seek'] ?? 0));
        $value->continuous_blocks = is_array($facts['continuous_blocks'] ?? null)
            ? $facts['continuous_blocks']
            : [];
        $value->inactivity_gaps = is_array($facts['inactivity_gaps'] ?? null)
            ? $facts['inactivity_gaps']
            : [];
        return $value;
    }

    /**
     * Returns the stable consumer-facing metrics contract.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'percent' => $this->percent,
            'duration' => $this->duration,
            'uniqueWatchTime' => $this->unique_watch_time,
            'playbackTime' => $this->playback_time,
            'sessionTime' => $this->session_time,
            'sessions' => $this->sessions,
            'pauseCount' => $this->pause_count,
            'seekCount' => $this->seek_count,
            'replayCount' => $this->replay_count,
            'maxRate' => $this->max_rate,
            'averageRate' => $this->average_rate,
            'reachedEnd' => $this->reached_end,
            'watchedRanges' => $this->watched_ranges,
            'lastPosition' => $this->last_position,
            'regularity' => $this->regularity,
            'seeksForward' => $this->seeks_forward,
            'seeksBackward' => $this->seeks_backward,
            'largestForwardSeek' => $this->largest_forward_seek,
            'continuousBlocks' => $this->continuous_blocks,
            'inactivityGaps' => $this->inactivity_gaps,
        ];
    }
}
