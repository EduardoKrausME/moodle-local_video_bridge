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
 * Shared in-memory viewing-map tracker.
 *
 * @module     local_video_bridge/progress
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/templates', 'core/ajax'], function(Templates, Ajax) {
    const MIN_SAVE_INTERVAL = 60000;
    const MAX_BUCKETS = 100;

    const formatTime = (seconds) => {
        seconds = Math.max(0, Math.floor(Number(seconds) || 0));
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor(seconds / 60) % 60;
        const secs = seconds % 60;
        if (hours > 0) {
            return [hours, minutes, secs].map((value) => String(value).padStart(2, '0')).join(':');
        }
        return [minutes, secs].map((value) => String(value).padStart(2, '0')).join(':');
    };

    const progressLength = (duration) => {
        duration = Math.floor(Number(duration) || 0);
        if (duration <= 0) {
            return 0;
        }
        return Math.max(1, Math.min(MAX_BUCKETS, duration));
    };

    const bucketForPosition = (position, duration) => {
        const length = progressLength(duration);
        position = Math.floor(Number(position) || 0);
        if (!length || position <= 0) {
            return 0;
        }
        if (length < MAX_BUCKETS) {
            return Math.max(1, Math.min(length, position));
        }
        return Math.max(1, Math.min(length, Math.floor(position / Math.max(1, duration) * length)));
    };

    class ProgressTracker {
        constructor(adapter, root, config) {
            this.adapter = adapter;
            this.root = root;
            this.config = config.progress || {};
            this.duration = Number(this.config.duration || adapter.getDuration() || 0);
            this.currentTime = Number(this.config.currenttime || adapter.getCurrentTime() || 0);
            this.watched = new Set((this.config.map || []).map(Number).filter(Boolean));
            this.pending = new Set();
            this.lastSentAt = Date.now();
            this.lastPosition = this.currentTime;
            this.dirty = false;
            this.renderedLength = 0;
            this.mapElement = null;
            this.percentElement = null;
            this.sessionStartedAt = Date.now();
            this.pauseStartedAt = 0;
            this.telemetry = this.createTelemetry();
            this.recordEvent('sessionstart', {position: this.currentTime});
            this.lastTickAt = Date.now();
            this.rateWeight = 0;
            this.rateSeconds = 0;
            this.lastPlaybackRate = Number(this.adapter.getPlaybackRate ? this.adapter.getPlaybackRate() : 1) || 1;
            this.playing = false;
            this.continuousStartedAt = 0;
            this.continuousStartPosition = this.currentTime;
            this.idleStartedAt = 0;
            this.pageHideHandler = () => {
                this.stopContinuous(this.currentTime);
                this.recordEvent('sessionend', {position: this.currentTime});
                this.telemetry.endedat = Math.floor(Date.now() / 1000);
                this.dirty = true;
                this.flush(true);
            };

            this.bind();
            this.render();
        }

        createTelemetry() {
            const level = String(this.config.telemetrylevel || 'basic').toLowerCase();
            const random = (window.crypto && window.crypto.randomUUID)
                ? window.crypto.randomUUID()
                : String(Date.now()) + '-' + Math.random().toString(36).slice(2);
            return {
                level: level,
                sessionid: random.replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 64),
                startedat: Math.floor(Date.now() / 1000),
                endedat: 0,
                duration: Math.floor(this.duration || 0),
                watchtime: 0,
                pausedtime: 0,
                sessionseconds: 0,
                startposition: Math.floor(this.currentTime || 0),
                plays: 0,
                pauses: 0,
                seeks: 0,
                replays: 0,
                skips: 0,
                dropoff: Math.floor(this.currentTime || 0),
                maxposition: Math.floor(this.currentTime || 0),
                speedavg: Number(this.adapter.getPlaybackRate ? this.adapter.getPlaybackRate() : 1) || 1,
                ranges: [],
                pausepoints: [],
                skippoints: [],
                replaypoints: [],
                rates: {},
                continuousblocks: [],
                inactivitygaps: [],
                events: [],
            };
        }

        telemetryEnabled(key) {
            const options = this.config.telemetryoptions || {};
            if (!(key in options)) {
                return true;
            }
            return options[key] !== false && Number(options[key]) !== 0;
        }

        recordEvent(type, data = {}) {
            const optionByType = {
                pause: 'recordpauses',
                seek: 'recordseeks',
                playbackrate: 'recordrates',
                waiting: 'recordbuffering',
                playing: 'recordbuffering',
                sessionend: 'recorddropoff',
            };
            const option = optionByType[type];
            if ((option && !this.telemetryEnabled(option))
                    || this.telemetry.level !== 'detailed'
                    || this.telemetry.events.length >= 500) {
                return;
            }
            const position = Number(
                data.position !== undefined
                    ? data.position
                    : (this.adapter.getCurrentTime ? this.adapter.getCurrentTime() : this.currentTime)
            ) || 0;
            this.telemetry.events.push(Object.assign({
                type: type,
                position: Math.max(0, position),
            }, data));
        }

        addRange(start, end) {
            if (this.telemetry.level !== 'detailed' || end <= start) {
                return;
            }
            const ranges = this.telemetry.ranges;
            const cleanStart = Math.max(0, Number(start) || 0);
            const cleanEnd = Math.max(cleanStart, Number(end) || 0);
            const last = ranges[ranges.length - 1];
            if (last && cleanStart <= last[1] + 1) {
                last[1] = Math.max(last[1], cleanEnd);
            } else if (ranges.length < 500) {
                ranges.push([cleanStart, cleanEnd]);
            }
        }

        startContinuous(position) {
            if (this.telemetry.level !== 'detailed' || this.continuousStartedAt) {
                return;
            }
            this.continuousStartedAt = Date.now();
            this.continuousStartPosition = Math.max(0, Number(position) || 0);
        }

        stopContinuous(position) {
            if (this.telemetry.level !== 'detailed' || !this.continuousStartedAt) {
                return;
            }
            const seconds = Math.max(0, Math.min(604800, (Date.now() - this.continuousStartedAt) / 1000));
            if (seconds >= 0.25 && this.telemetry.continuousblocks.length < 500) {
                this.telemetry.continuousblocks.push([
                    this.continuousStartPosition,
                    Math.max(0, Number(position) || 0),
                    seconds,
                ]);
            }
            this.continuousStartedAt = 0;
        }

        recordInactivity() {
            if (this.telemetry.level !== 'detailed' || !this.idleStartedAt) {
                this.idleStartedAt = 0;
                return;
            }
            const seconds = Math.max(0, Math.min(604800, (Date.now() - this.idleStartedAt) / 1000));
            if (seconds >= 0.25 && this.telemetry.inactivitygaps.length < 500) {
                this.telemetry.inactivitygaps.push(seconds);
            }
            this.idleStartedAt = 0;
        }

        observeTelemetry(position) {
            const now = Date.now();
            const wallSeconds = Math.max(0, Math.min(5, (now - this.lastTickAt) / 1000));
            const previous = Number(this.lastPosition || 0);
            const delta = position - previous;
            const rate = Number(this.adapter.getPlaybackRate ? this.adapter.getPlaybackRate() : 1) || 1;

            if (delta >= 0 && delta <= Math.max(5, wallSeconds * Math.max(1, rate) * 3)) {
                if (delta > 0) {
                    this.telemetry.watchtime += wallSeconds;
                    if (this.telemetryEnabled('recordrates')) {
                        this.rateWeight += rate * wallSeconds;
                        this.rateSeconds += wallSeconds;
                    }
                    if (this.telemetryEnabled('recordrates')) {
                        this.telemetry.rates[String(rate)] =
                            (Number(this.telemetry.rates[String(rate)]) || 0) + wallSeconds;
                    }
                    this.addRange(previous, position);
                }
            } else if (Math.abs(delta) > 1) {
                this.stopContinuous(previous);
                if (this.telemetryEnabled('recordseeks')) {
                    this.telemetry.seeks++;
                }
                this.recordEvent('seek', {
                    position: position,
                    from: previous,
                    to: position,
                    distance: Math.abs(delta),
                    direction: delta > 0 ? 'forward' : 'backward',
                });
                if (delta > 0) {
                    if (this.telemetryEnabled('recordseeks')) {
                        this.telemetry.skips++;
                    }
                    if (this.telemetryEnabled('recordseeks')
                            && this.telemetry.level === 'detailed'
                            && this.telemetry.skippoints.length < 1000) {
                        this.telemetry.skippoints.push([previous, position]);
                    }
                } else {
                    if (this.telemetryEnabled('recordseeks')) {
                        this.telemetry.replays++;
                    }
                    if (this.telemetryEnabled('recordseeks')
                            && this.telemetry.level === 'detailed'
                            && this.telemetry.replaypoints.length < 1000) {
                        this.telemetry.replaypoints.push([position, previous]);
                    }
                }
                if (this.playing) {
                    this.startContinuous(position);
                }
            }

            this.telemetry.dropoff = Math.max(0, position);
            this.telemetry.maxposition = Math.max(this.telemetry.maxposition, position);
            this.telemetry.duration = Math.max(this.telemetry.duration, this.duration);
            this.telemetry.speedavg = this.rateSeconds > 0 ? this.rateWeight / this.rateSeconds : rate;
            this.lastTickAt = now;
        }

        bind() {
            if (typeof this.adapter.onPlay === 'function') {
                this.adapter.onPlay(() => {
                    this.telemetry.plays++;
                    if (this.pauseStartedAt) {
                        this.telemetry.pausedtime += Math.max(0, (Date.now() - this.pauseStartedAt) / 1000);
                        this.pauseStartedAt = 0;
                    }
                    this.recordEvent('play', {position: this.adapter.getCurrentTime ? this.adapter.getCurrentTime() : this.currentTime});
                    this.recordInactivity();
                    this.playing = true;
                    this.startContinuous(this.adapter.getCurrentTime ? this.adapter.getCurrentTime() : this.currentTime);
                    this.lastTickAt = Date.now();
                    this.dirty = true;
                });
            }
            if (typeof this.adapter.onPause === 'function') {
                this.adapter.onPause(() => {
                    this.stopContinuous(this.adapter.getCurrentTime ? this.adapter.getCurrentTime() : this.currentTime);
                    this.playing = false;
                    this.idleStartedAt = Date.now();
                    if (!this.pauseStartedAt) {
                        this.pauseStartedAt = Date.now();
                    }
                    if (this.telemetryEnabled('recordpauses')) {
                        this.telemetry.pauses++;
                    }
                    this.recordEvent('pause', {position: this.adapter.getCurrentTime ? this.adapter.getCurrentTime() : this.currentTime});
                    if (this.telemetryEnabled('recordpauses')
                            && this.telemetry.level === 'detailed'
                            && this.telemetry.pausepoints.length < 1000) {
                        this.telemetry.pausepoints.push(Number(this.adapter.getCurrentTime() || this.currentTime || 0));
                    }
                    this.dirty = true;
                });
            }
            if (typeof this.adapter.onRateChange === 'function') {
                this.adapter.onRateChange(() => {
                    const nextRate = Number(this.adapter.getPlaybackRate ? this.adapter.getPlaybackRate() : 1) || 1;
                    this.recordEvent('playbackrate', {
                        position: this.adapter.getCurrentTime ? this.adapter.getCurrentTime() : this.currentTime,
                        from: this.lastPlaybackRate,
                        to: nextRate,
                    });
                    this.lastPlaybackRate = nextRate;
                    this.lastTickAt = Date.now();
                    this.dirty = true;
                });
            }
            if (typeof this.adapter.onWaiting === 'function') {
                this.adapter.onWaiting(() => {
                    this.recordEvent('waiting');
                    this.dirty = true;
                });
            }
            if (typeof this.adapter.onPlaying === 'function') {
                this.adapter.onPlaying(() => {
                    this.recordEvent('playing');
                    this.dirty = true;
                });
            }

            this.adapter.onTimeUpdate((position) => {
                this.currentTime = Number(position || 0);
                const duration = Number(this.adapter.getDuration() || this.duration || 0);
                if (duration > 0) {
                    this.duration = duration;
                }
                this.observeTelemetry(this.currentTime);
                this.markPosition(this.currentTime);
                this.lastPosition = this.currentTime;
            });

            this.adapter.onEnded(() => {
                this.stopContinuous(this.currentTime);
                this.playing = false;
                const duration = Number(this.adapter.getDuration() || this.duration || 0);
                if (duration > 0) {
                    this.duration = duration;
                    this.currentTime = duration;
                    this.markPosition(duration);
                }
                this.recordEvent('ended', {position: this.currentTime});
                this.telemetry.endedat = Math.floor(Date.now() / 1000);
                this.telemetry.dropoff = Math.floor(this.currentTime || 0);
                this.dirty = true;
                this.flush(true);
            });

            document.addEventListener('visibilitychange', () => {
                this.recordEvent('visibilitychange', {
                    position: this.currentTime,
                    state: document.hidden ? 'hidden' : 'visible',
                });
                if (document.hidden) {
                    this.stopContinuous(this.currentTime);
                    if (!this.idleStartedAt) {
                        this.idleStartedAt = Date.now();
                    }
                } else {
                    this.recordInactivity();
                    if (this.playing) {
                        this.startContinuous(this.currentTime);
                    }
                }
            });

            const interval = Math.max(MIN_SAVE_INTERVAL, Number(this.config.saveinterval || MIN_SAVE_INTERVAL));
            this.timer = window.setInterval(() => this.flush(false), interval);
            window.addEventListener('pagehide', this.pageHideHandler);
        }

        markPosition(position) {
            const bucket = bucketForPosition(position, this.duration);
            if (!bucket) {
                return;
            }

            if (!this.watched.has(bucket)) {
                this.watched.add(bucket);
                this.pending.add(bucket);
                this.dirty = true;
            } else if (Math.floor(position) !== Math.floor(this.lastPosition)) {
                this.dirty = true;
            }

            if (this.renderedLength !== progressLength(this.duration)) {
                this.render();
            } else {
                this.paint();
            }
        }

        getPercent() {
            const length = progressLength(this.duration);
            return length > 0 ? Math.min(100, Math.floor(this.watched.size / length * 100)) : 0;
        }

        buildTemplateContext() {
            const length = progressLength(this.duration);
            const buckets = [];
            for (let index = 1; index <= length; index++) {
                const time = Math.floor((index - 1) / Math.max(1, length) * this.duration);
                buckets.push({
                    index: index,
                    watched: this.watched.has(index),
                    time: time,
                    title: formatTime(time),
                });
            }

            return {
                label: this.config.label || 'Viewing map',
                percent: this.getPercent(),
                buckets: buckets,
            };
        }

        render() {
            const length = progressLength(this.duration);
            if (!length || !this.root) {
                return;
            }

            const old = this.root.querySelector('[data-video-bridge-progress]');
            if (old) {
                old.remove();
            }

            this.renderedLength = length;
            Templates.render('local_video_bridge/progress_map', this.buildTemplateContext())
                .then((html) => {
                    this.root.insertAdjacentHTML('beforeend', html);
                    this.mapElement = this.root.querySelector('[data-region="progress-map"]');
                    this.percentElement = this.root.querySelector('[data-region="progress-percent"]');
                    if (this.mapElement) {
                        this.mapElement.addEventListener('click', (event) => {
                            const bucket = event.target.closest('[data-bucket]');
                            if (!bucket || !this.adapter.seek) {
                                return;
                            }
                            this.adapter.seek(Number(bucket.dataset.time || 0));
                        });
                    }
                    this.paint();
                    return null;
                })
                .catch(() => {});
        }

        paint() {
            const percent = this.getPercent();
            if (this.percentElement) {
                this.percentElement.textContent = percent + '%';
            }
            if (this.mapElement) {
                this.mapElement.setAttribute('aria-valuenow', String(percent));
                this.mapElement.querySelectorAll('[data-bucket]').forEach((element) => {
                    const bucket = Number(element.dataset.bucket || 0);
                    element.classList.toggle('is-watched', this.watched.has(bucket));
                });
            }
        }

        buildFormData(snapshot) {
            const data = new FormData();
            data.append('sesskey', this.config.sesskey);
            data.append('contextid', String(this.config.contextid));
            data.append('component', this.config.component);
            data.append('itemid', String(this.config.itemid));
            data.append('source', this.config.source);
            data.append('mediahash', this.config.mediahash);
            data.append('currenttime', String(Math.max(0, Math.floor(this.currentTime || 0))));
            data.append('duration', String(Math.max(0, Math.floor(this.duration || 0))));
            data.append('buckets', JSON.stringify(snapshot));
            if (this.telemetry
                    && this.telemetry.level !== 'off'
                    && this.config.telemetryenabled !== false
                    && Number(this.config.telemetryenabled ?? 1) !== 0) {
                const rates = {};
                Object.entries(this.telemetry.rates || {}).forEach(([rate, seconds]) => {
                    rates[rate] = Math.floor(Number(seconds) || 0);
                });
                const pausedtime = this.telemetry.pausedtime
                    + (this.pauseStartedAt ? Math.max(0, (Date.now() - this.pauseStartedAt) / 1000) : 0);
                const payload = Object.assign({}, this.telemetry, {
                    duration: Math.floor(this.duration || 0),
                    watchtime: Math.floor(this.telemetry.watchtime || 0),
                    pausedtime: Math.floor(pausedtime),
                    sessionseconds: Math.floor(Math.max(0, (Date.now() - this.sessionStartedAt) / 1000)),
                    startposition: Math.floor(this.telemetry.startposition || 0),
                    endposition: Math.floor(this.currentTime || 0),
                    dropoff: Math.floor(this.currentTime || 0),
                    maxposition: Math.floor(this.telemetry.maxposition || 0),
                    speedavg: Number(this.telemetry.speedavg || 1),
                    rates: rates,
                });
                data.append('telemetry', JSON.stringify(payload));
            }
            return data;
        }

        flush(finalFlush) {
            if (!this.dirty || !this.config.endpoint) {
                return;
            }

            const now = Date.now();
            if (!finalFlush && now - this.lastSentAt < MIN_SAVE_INTERVAL) {
                return;
            }

            const snapshot = Array.from(this.pending);
            const data = this.buildFormData(snapshot);

            if (finalFlush) {
                if (navigator.sendBeacon && navigator.sendBeacon(this.config.endpoint, data)) {
                    return;
                }
                fetch(this.config.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data,
                    keepalive: true,
                }).catch(() => {});
                return;
            }

            this.lastSentAt = now;
            if (this.config.ajaxmethod) {
                const args = {};
                data.forEach((value, key) => {
                    if (key !== 'sesskey') {
                        args[key] = value;
                    }
                });
                Ajax.call([{
                    methodname: this.config.ajaxmethod,
                    args: args,
                }])[0].then((result) => {
                    if (!result || !result.success) {
                        throw new Error('Unable to save Video Bridge progress.');
                    }
                    snapshot.forEach((bucket) => this.pending.delete(bucket));
                    this.dirty = this.pending.size > 0;
                    if (Array.isArray(result.map)) {
                        result.map.forEach((bucket) => this.watched.add(Number(bucket)));
                    }
                    if (Number(result.duration || 0) > 0) {
                        this.duration = Number(result.duration);
                    }
                    this.paint();
                    return null;
                }).catch(() => {
                    this.dirty = true;
                });
                return;
            }

            fetch(this.config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to save Video Bridge progress.');
                }
                return response.json();
            }).then((result) => {
                if (!result || !result.success) {
                    throw new Error('Unable to save Video Bridge progress.');
                }
                snapshot.forEach((bucket) => this.pending.delete(bucket));
                this.dirty = this.pending.size > 0;
                if (Array.isArray(result.map)) {
                    result.map.forEach((bucket) => this.watched.add(Number(bucket)));
                }
                if (Number(result.duration || 0) > 0) {
                    this.duration = Number(result.duration);
                }
                this.paint();
                return null;
            }).catch(() => {
                this.dirty = true;
            });
        }
    }

    const attach = (adapter, root, config) => {
        if (!config || !config.progress || !config.progress.enabled || !adapter) {
            return adapter;
        }
        if (!adapter.__videoBridgeProgressTracker) {
            adapter.__videoBridgeProgressTracker = new ProgressTracker(adapter, root, config);
        }
        return adapter;
    };

    return {
        attach: attach,
        progressLength: progressLength,
        bucketForPosition: bucketForPosition,
    };
});
