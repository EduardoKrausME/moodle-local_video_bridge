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
 * Qencode playback adapter.
 *
 * @module     videoprogresssource_qencode/player
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'local_video_bridge/html5',
    'local_video_bridge/hls'
], function(Html5Adapter, HlsAdapter) {
    const create = (root, config) => {
        const Adapter = config.hls ? HlsAdapter : Html5Adapter;
        return (new Adapter(root, config)).initialise();
    };

    return {create: create};
});
