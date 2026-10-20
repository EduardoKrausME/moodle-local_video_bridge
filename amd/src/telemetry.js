// This file is part of Moodle - http://moodle.org/
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
 * telemetry.js
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Normalized playback event bus for Video Bridge adapters.
 *
 * @module local_video_bridge/telemetry
 */
define([], function() {
    const EVENT_MAP = {
        play: 'onPlay',
        pause: 'onPause',
        timeupdate: 'onTimeUpdate',
        seeking: 'onSeeking',
        seeked: 'onSeeked',
        ratechange: 'onRateChange',
        waiting: 'onWaiting',
        playing: 'onPlaying',
        ended: 'onEnded',
        durationchange: 'onDurationChange',
    };

    class TelemetryBus {
        constructor(adapter) {
            this.adapter = adapter;
            this.listeners = new Map();
            Object.keys(EVENT_MAP).forEach((name) => this.listeners.set(name, new Set()));
            this.bindAdapter();
        }

        bindAdapter() {
            Object.entries(EVENT_MAP).forEach(([event, method]) => {
                if (typeof this.adapter[method] !== 'function') {
                    return;
                }
                this.adapter[method]((...args) => this.emit(event, ...args));
            });
        }

        on(event, callback) {
            if (!this.listeners.has(event) || typeof callback !== 'function') {
                return () => {};
            }
            const bucket = this.listeners.get(event);
            bucket.add(callback);
            return () => bucket.delete(callback);
        }

        emit(event, ...args) {
            const bucket = this.listeners.get(event);
            if (!bucket) {
                return;
            }
            bucket.forEach((callback) => callback(...args));
        }
    }

    const create = (adapter) => new TelemetryBus(adapter);
    return {create};
});
