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
 * Remote player adapter.
 *
 * @module     videoprogresssource_pandavideo/player
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    class PandaAdapter {
        constructor(root, config) {
            this.root = root;
            this.config = config;
            this.handlers = {};
            this.current = 0;
            this.duration = 0;
            this.iframe = root.querySelector('[data-region="panda-player"]');
            this.receive = (event) => this.receiveMessage(event);
            window.addEventListener('message', this.receive, false);
        }
        receiveMessage(event) {
            if (!this.iframe || event.source !== this.iframe.contentWindow || event.origin !== this.config.origin) {
                return;
            }
            const data = event.data || {};
            if (data.message === 'panda_allData' && data.playerData) {
                this.duration = Number(data.playerData.duration || 0);
                return;
            }
            if (data.message === 'panda_timeupdate') {
                const previous = this.current;
                this.current = Number(data.currentTime || 0);
                if (this.duration && Math.abs(this.current - previous) > 3) {
                    this.emit('seek', this.current, previous);
                }
                this.emit('timeupdate', this.current);
                if (this.duration > 0 && this.current >= this.duration) {
                    this.emit('ended');
                }
            }
        }
        play() {
            return Promise.resolve();
        }
        pause() {
        }
        getCurrentTime() {
            return this.current;
        }
        getDuration() {
            return this.duration;
        }
        getPlaybackRate() {
            return 1;
        }
        seek(position) {
            if (this.iframe && this.iframe.contentWindow) {
                this.iframe.contentWindow.postMessage({
                    type: 'currentTime',
                    parameter: Math.max(0, Number(position))
                }, this.config.origin);
            }
        }
        onPlay(handler) {
            this.on('play', handler);
        }
        onPause(handler) {
            this.on('pause', handler);
        }
        onTimeUpdate(handler) {
            this.on('timeupdate', handler);
        }
        onSeek(handler) {
            this.on('seek', handler);
        }
        onEnded(handler) {
            this.on('ended', handler);
        }
        onRateChange(handler) {
            this.on('ratechange', handler);
        }
        on(name, handler) {
            this.handlers[name] = (this.handlers[name] || []).concat(handler);
        }
        emit(name, ...args) {
            (this.handlers[name] || []).forEach((handler) => handler(...args));
        }
    }
    const create = (root, config) => Promise.resolve(new PandaAdapter(root, config));
    return {create: create};
});
