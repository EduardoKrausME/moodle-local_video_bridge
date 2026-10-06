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

define(['core/templates'], function(Templates) {
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
            this.pageHideHandler = () => this.flush(true);

            this.bind();
            this.render();
        }

        bind() {
            this.adapter.onTimeUpdate((position) => {
                this.currentTime = Number(position || 0);
                const duration = Number(this.adapter.getDuration() || this.duration || 0);
                if (duration > 0) {
                    this.duration = duration;
                }
                this.markPosition(this.currentTime);
                this.lastPosition = this.currentTime;
            });

            this.adapter.onEnded(() => {
                const duration = Number(this.adapter.getDuration() || this.duration || 0);
                if (duration > 0) {
                    this.duration = duration;
                    this.currentTime = duration;
                    this.markPosition(duration);
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
