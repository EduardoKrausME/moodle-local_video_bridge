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
 * hls.js
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "local_video_bridge/html5"], function ($, Html5Adapter) {
    let apiPromise;
    const loadApi = (url) => {
        if (window.Hls && typeof window.Hls.isSupported === 'function') {
            return Promise.resolve(window.Hls);
        }
        if (!apiPromise) {
            apiPromise = new Promise((resolve, reject) => {
                // The hls.js UMD bundle registers anonymously with RequireJS on Moodle.
                require([url], (module) => {
                    const Hls = module && (module.default || module.Hls || module) || window.Hls;
                    if (Hls && typeof Hls.isSupported === 'function') {
                        resolve(Hls);
                    } else {
                        reject(new Error('HLS Player API did not load'));
                    }
                }, reject);
            });
        }
        return apiPromise;
    };

    /**
     * Extends the HTML5 adapter with native HLS detection and hls.js fallback loading.
     */
    class HlsAdapter extends Html5Adapter {
        /**
         * Initialises the player integration and returns the ready adapter or tracker.
         *
         * @return {*} The ready adapter, tracker, or readiness promise.
         */
        initialise() {
            if (this.video && this.video.canPlayType('application/vnd.apple.mpegurl')) {
                this.video.src = this.config.url;
                return super.initialise();
            }
            return loadApi(this.config.hlsjsurl).then((Hls) => {
                if (!Hls || !Hls.isSupported()) {
                    throw new Error('HLS is not supported by this browser');
                }
                this.hls = new Hls({enableWorker: true, lowLatencyMode: true});
                this.hls.loadSource(this.config.url);
                this.hls.attachMedia(this.video);
                return super.initialise();
            });
        }
    }

    return HlsAdapter;
});
