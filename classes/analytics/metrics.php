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
    public int $uniquewatchtime = 0;

    /** @var int Total playback time in seconds. */
    public int $playbacktime = 0;

    /** @var int Total real session time in seconds. */
    public int $sessiontime = 0;

    /** @var int Number of playback sessions. */
    public int $sessions = 0;

    /** @var int Number of pauses. */
    public int $pausecount = 0;

    /** @var int Number of seeks. */
    public int $seekcount = 0;

    /** @var int Number of replay/backward-seek actions. */
    public int $replaycount = 0;

    /** @var float Maximum observed playback rate. */
    public float $maxrate = 1.0;

    /** @var float Average observed playback rate. */
    public float $averagerate = 1.0;

    /** @var bool Whether playback reached the end. */
    public bool $reachedend = false;

    /** @var array Consolidated watched ranges. */
    public array $watchedranges = [];

    /** @var int Last observed playback position. */
    public int $lastposition = 0;

    /** @var float Playback regularity percentage. */
    public float $regularity = 0.0;

    /** @var int Number of forward seeks. */
    public int $seeksforward = 0;

    /** @var int Number of backward seeks. */
    public int $seeksbackward = 0;

    /** @var float Largest observed forward seek in seconds. */
    public float $largestforwardseek = 0.0;

    /** @var array Continuous playback blocks. */
    public array $continuousblocks = [];

    /** @var array Inactivity gaps. */
    public array $inactivitygaps = [];

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
        $value->uniquewatchtime = (int)round(max(0, (float)($facts['unique_watch_time'] ?? 0)));
        $value->playbacktime = (int)round(max(0, (float)($facts['playback_time'] ?? 0)));
        $value->sessiontime = (int)round(max(0, (float)($facts['real_session_time'] ?? 0)));
        $value->sessions = max(0, (int)($facts['session_count'] ?? 0));
        $value->pausecount = max(0, (int)($facts['pause_count'] ?? 0));
        $value->seeksforward = max(0, (int)($facts['seeks_forward'] ?? 0));
        $value->seeksbackward = max(0, (int)($facts['seeks_backward'] ?? 0));
        $value->seekcount = $value->seeksforward + $value->seeksbackward;
        $value->replaycount = $value->seeksbackward;
        $value->maxrate = max(0.1, (float)($facts['maximum_playback_rate'] ?? 1));
        $value->averagerate = max(0.1, (float)($facts['average_playback_rate'] ?? 1));
        $value->reachedend = !empty($facts['reached_end']);
        $value->watchedranges = is_array($facts['watched_ranges'] ?? null) ? $facts['watched_ranges'] : [];
        $value->lastposition = (int)round(max(0, (float)($facts['current_position'] ?? 0)));
        $value->regularity = $value->sessiontime > 0
            ? round(min(100, ($value->playbacktime / $value->sessiontime) * 100), 2)
            : 0.0;
        $value->largestforwardseek = max(0, (float)($facts['largest_forward_seek'] ?? 0));
        $value->continuousblocks = is_array($facts['continuous_blocks'] ?? null)
            ? $facts['continuous_blocks']
            : [];
        $value->inactivitygaps = is_array($facts['inactivity_gaps'] ?? null)
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
            'uniqueWatchTime' => $this->uniquewatchtime,
            'playbackTime' => $this->playbacktime,
            'sessionTime' => $this->sessiontime,
            'sessions' => $this->sessions,
            'pauseCount' => $this->pausecount,
            'seekCount' => $this->seekcount,
            'replayCount' => $this->replaycount,
            'maxRate' => $this->maxrate,
            'averageRate' => $this->averagerate,
            'reachedEnd' => $this->reachedend,
            'watchedRanges' => $this->watchedranges,
            'lastPosition' => $this->lastposition,
            'regularity' => $this->regularity,
            'seeksForward' => $this->seeksforward,
            'seeksBackward' => $this->seeksbackward,
            'largestForwardSeek' => $this->largestforwardseek,
            'continuousBlocks' => $this->continuousblocks,
            'inactivityGaps' => $this->inactivitygaps,
        ];
    }
}
