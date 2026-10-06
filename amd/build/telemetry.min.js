// This file is part of Moodle - http://moodle.org/
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
