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
 * @module     videoprogresssource_drive/player
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    class DriveAdapter {
        constructor(root) {
            this.root = root;
            this.handlers = {};
        }
        play() {
            return Promise.resolve();
        }
        pause() {
        }
        getCurrentTime() {
            return 0;
        }
        getDuration() {
            return 0;
        }
        getPlaybackRate() {
            return 1;
        }
        seek() {
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
    }
    const create = (root) => Promise.resolve(new DriveAdapter(root));
    return {create: create};
});
