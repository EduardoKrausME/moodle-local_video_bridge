<?php
namespace local_video_bridge\analytics;

defined('MOODLE_INTERNAL') || die;

/**
 * Provider-independent consolidated video metrics.
 *
 * Property names intentionally remain stable across consumer activities so
 * Pro, Ultra, Max, Prime, Ultimate and future modules can share one contract.
 *
 * @package local_video_bridge
 */
final class metrics {
    public int $percent = 0;
    public int $duration = 0;
    public int $uniqueWatchTime = 0;
    public int $playbackTime = 0;
    public int $sessionTime = 0;
    public int $sessions = 0;
    public int $pauseCount = 0;
    public int $seekCount = 0;
    public int $replayCount = 0;
    public float $maxRate = 1.0;
    public float $averageRate = 1.0;
    public bool $reachedEnd = false;
    public array $watchedRanges = [];
    public int $lastPosition = 0;
    public float $regularity = 0.0;
    public int $seeksForward = 0;
    public int $seeksBackward = 0;
    public float $largestForwardSeek = 0.0;
    public array $continuousBlocks = [];
    public array $inactivityGaps = [];

    public static function from_facts(array $facts): self {
        $value = new self();
        $value->percent = (int)round(max(0, min(100, (float)($facts['percent_watched'] ?? 0))));
        $value->duration = (int)round(max(0, (float)($facts['duration'] ?? 0)));
        $value->uniqueWatchTime = (int)round(max(0, (float)($facts['unique_watch_time'] ?? 0)));
        $value->playbackTime = (int)round(max(0, (float)($facts['playback_time'] ?? 0)));
        $value->sessionTime = (int)round(max(0, (float)($facts['real_session_time'] ?? 0)));
        $value->sessions = max(0, (int)($facts['session_count'] ?? 0));
        $value->pauseCount = max(0, (int)($facts['pause_count'] ?? 0));
        $value->seeksForward = max(0, (int)($facts['seeks_forward'] ?? 0));
        $value->seeksBackward = max(0, (int)($facts['seeks_backward'] ?? 0));
        $value->seekCount = $value->seeksForward + $value->seeksBackward;
        $value->replayCount = $value->seeksBackward;
        $value->maxRate = max(0.1, (float)($facts['maximum_playback_rate'] ?? 1));
        $value->averageRate = max(0.1, (float)($facts['average_playback_rate'] ?? 1));
        $value->reachedEnd = !empty($facts['reached_end']);
        $value->watchedRanges = is_array($facts['watched_ranges'] ?? null) ? $facts['watched_ranges'] : [];
        $value->lastPosition = (int)round(max(0, (float)($facts['current_position'] ?? 0)));
        $value->regularity = $value->sessionTime > 0
            ? round(min(100, ($value->playbackTime / $value->sessionTime) * 100), 2)
            : 0.0;
        $value->largestForwardSeek = max(0, (float)($facts['largest_forward_seek'] ?? 0));
        $value->continuousBlocks = is_array($facts['continuous_blocks'] ?? null) ? $facts['continuous_blocks'] : [];
        $value->inactivityGaps = is_array($facts['inactivity_gaps'] ?? null) ? $facts['inactivity_gaps'] : [];
        return $value;
    }

    public function to_array(): array {
        return [
            'percent' => $this->percent,
            'duration' => $this->duration,
            'uniqueWatchTime' => $this->uniqueWatchTime,
            'playbackTime' => $this->playbackTime,
            'sessionTime' => $this->sessionTime,
            'sessions' => $this->sessions,
            'pauseCount' => $this->pauseCount,
            'seekCount' => $this->seekCount,
            'replayCount' => $this->replayCount,
            'maxRate' => $this->maxRate,
            'averageRate' => $this->averageRate,
            'reachedEnd' => $this->reachedEnd,
            'watchedRanges' => $this->watchedRanges,
            'lastPosition' => $this->lastPosition,
            'regularity' => $this->regularity,
            'seeksForward' => $this->seeksForward,
            'seeksBackward' => $this->seeksBackward,
            'largestForwardSeek' => $this->largestForwardSeek,
            'continuousBlocks' => $this->continuousBlocks,
            'inactivityGaps' => $this->inactivityGaps,
        ];
    }
}
