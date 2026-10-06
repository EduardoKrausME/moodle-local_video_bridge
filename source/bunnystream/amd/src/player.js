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
 * Bunny Stream Player.js adapter.
 *
 * @module     videoprogresssource_bunnystream/player
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    let apiPromise;

    const loadApi = (url) => {
        if (window.playerjs && window.playerjs.Player) {
            return Promise.resolve(window.playerjs);
        }

        if (!apiPromise) {
            apiPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = url;
                script.async = true;
                script.onload = () => {
                    if (window.playerjs && window.playerjs.Player) {
                        resolve(window.playerjs);
                    } else {
                        reject(new Error('Bunny Stream Player.js did not initialise.'));
                    }
                };
                script.onerror = () => reject(new Error('Unable to load Bunny Stream Player.js.'));
                document.head.appendChild(script);
            });
        }

        return apiPromise;
    };

    class BunnyStreamAdapter {
        constructor(root, config) {
            this.root = root;
            this.config = config;
            this.handlers = {};
            this.current = 0;
            this.duration = 0;
            this.rate = 1;
            this.iframe = root.querySelector('[data-region="bunnystream-player"]');
        }

        initialise() {
            if (!this.iframe) {
                return Promise.reject(new Error('Bunny Stream iframe was not found.'));
            }

            return loadApi(this.config.playerjsurl).then((playerjs) => new Promise((resolve) => {
                this.player = new playerjs.Player(this.iframe);

                this.player.on('play', () => this.emit('play'));
                this.player.on('pause', () => this.emit('pause'));
                this.player.on('ended', () => this.emit('ended'));
                this.player.on('timeupdate', (data) => {
                    const payload = data || {};
                    this.current = Number(payload.seconds || 0);
                    if (payload.duration !== undefined) {
                        this.duration = Number(payload.duration || 0);
                    }
                    this.emit('timeupdate', this.current);
                });
                this.player.on('seeked', (data) => {
                    const payload = data || {};
                    const position = typeof payload === 'number'
                        ? payload
                        : Number(payload.seconds !== undefined
                            ? payload.seconds
                            : (payload.currentTime !== undefined ? payload.currentTime : this.current));
                    const previous = this.current;
                    this.current = Number(position || 0);
                    this.emit('seek', this.current, previous);
                });
                this.player.on('ready', () => {
                    this.player.getDuration((duration) => {
                        this.duration = Number(duration || 0);
                    });
                    this.player.getCurrentTime((current) => {
                        this.current = Number(current || 0);
                    });
                    resolve(this);
                });
            }));
        }

        play() {
            this.player.play();
            return Promise.resolve();
        }

        pause() {
            this.player.pause();
            return Promise.resolve();
        }

        getCurrentTime() {
            return Number(this.current || 0);
        }

        getDuration() {
            return Number(this.duration || 0);
        }

        getPlaybackRate() {
            return this.rate;
        }

        seek(position) {
            const target = Math.max(0, Number(position));
            this.player.setCurrentTime(target);
            return Promise.resolve(target);
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

    const create = (root, config) => (new BunnyStreamAdapter(root, config)).initialise();
    return {create: create};
});
