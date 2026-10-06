// This file is part of Moodle - http://moodle.org/.
/**
 * Generic timeline cue engine for Video Bridge adapters.
 *
 * @module local_video_bridge/timeline
 */
define([], function() {
    class Timeline {
        constructor(adapter, options) {
            this.adapter = adapter;
            this.options = Object.assign({tolerance: 0.5, maxstep: 4}, options || {});
            this.cues = [];
            this.callbacks = [];
            this.fired = new Set();
            this.playing = false;
            this.lastTime = Number(adapter.getCurrentTime && adapter.getCurrentTime() || 0);
            this.markerViews = [];
            this.bind();
        }
        bind() {
            if (this.adapter.onPlay) this.adapter.onPlay(() => { this.playing = true; this.lastTime = this.getCurrentTime(); });
            if (this.adapter.onPause) this.adapter.onPause(() => { this.playing = false; this.lastTime = this.getCurrentTime(); });
            if (this.adapter.onSeek) this.adapter.onSeek((current) => { this.lastTime = Number(current || this.getCurrentTime()); this.refreshMarkers(); });
            if (this.adapter.onTimeUpdate) this.adapter.onTimeUpdate((current) => this.handleTimeUpdate(Number(current)));
            if (this.adapter.onEnded) this.adapter.onEnded(() => { if (this.playing) this.handleTimeUpdate(Number(this.adapter.getDuration && this.adapter.getDuration() || this.lastTime)); this.playing = false; });
        }
        register(cue) {
            if (!cue || cue.id === undefined || !Number.isFinite(Number(cue.time))) throw new Error('Invalid Video Bridge timeline cue.');
            const normalized = Object.assign({}, cue, {id: String(cue.id), time: Math.max(0, Number(cue.time)), once: Boolean(cue.once)});
            const index = this.cues.findIndex((item) => item.id === normalized.id);
            if (index === -1) this.cues.push(normalized); else this.cues[index] = normalized;
            this.cues.sort((a, b) => a.time - b.time);
            this.refreshMarkers();
            return normalized;
        }
        unregister(id) { id = String(id); this.cues = this.cues.filter((cue) => cue.id !== id); this.fired.delete(id); this.refreshMarkers(); }
        clear() { this.cues = []; this.fired.clear(); this.refreshMarkers(); }
        onTrigger(callback) { if (typeof callback === 'function') this.callbacks.push(callback); return () => { this.callbacks = this.callbacks.filter((item) => item !== callback); }; }
        handleTimeUpdate(current) {
            if (!Number.isFinite(current)) return;
            const previous = Number(this.lastTime || 0);
            this.lastTime = current;
            this.refreshMarkers();
            if (!this.playing || current < previous) return;
            const rate = Math.max(0.25, Number(this.adapter.getPlaybackRate && this.adapter.getPlaybackRate() || 1));
            const allowed = Math.max(1, Number(this.options.maxstep || 4) * rate) + Number(this.options.tolerance || 0);
            if (current - previous > allowed) return;
            const tolerance = Math.max(0, Number(this.options.tolerance || 0));
            this.cues.forEach((cue) => {
                if (cue.once && this.fired.has(cue.id)) return;
                if (cue.time >= previous && cue.time <= current + tolerance) {
                    if (cue.once) this.fired.add(cue.id);
                    this.callbacks.slice().forEach((callback) => callback(cue));
                }
            });
        }
        getCurrentTime() { return Number(this.adapter.getCurrentTime && this.adapter.getCurrentTime() || 0); }
        seekTo(time) { if (this.adapter.seek) this.adapter.seek(Math.max(0, Number(time) || 0)); }
        resetFired(id) { if (id === undefined) this.fired.clear(); else this.fired.delete(String(id)); }
        renderMarkers(root, cues, options) { if (!root) return; this.markerViews = [{root: root, cues: Array.isArray(cues) ? cues : this.cues, options: options || {}}]; this.refreshMarkers(true); }
        refreshMarkers(force) {
            this.markerViews.forEach((view) => {
                const duration = Number(this.adapter.getDuration && this.adapter.getDuration() || 0);
                if (!force && view.duration === duration) return;
                view.duration = duration;
                const root = view.root;
                root.replaceChildren();
                root.dataset.videoBridgeTimeline = '1';
                if (duration <= 0) return;
                const track = document.createElement('div');
                track.className = 'video-bridge-timeline__track';
                root.append(track);
                let dragged = null;
                (view.cues || []).filter((cue) => cue.visible !== false).forEach((cue) => {
                    const marker = document.createElement('button');
                    marker.type = 'button';
                    marker.className = 'video-bridge-timeline__marker';
                    marker.dataset.cueId = String(cue.id);
                    marker.style.left = Math.max(0, Math.min(100, Number(cue.time) / duration * 100)) + '%';
                    marker.title = cue.title || String(cue.time);
                    marker.setAttribute('aria-label', marker.title);
                    if (cue.type) marker.dataset.cueType = String(cue.type);
                    marker.addEventListener('click', () => { if (typeof view.options.onSelect === 'function') view.options.onSelect(cue); });
                    if (view.options.draggable) {
                        marker.draggable = true;
                        marker.addEventListener('dragstart', () => { dragged = cue; marker.classList.add('is-dragging'); });
                        marker.addEventListener('dragend', () => marker.classList.remove('is-dragging'));
                    }
                    track.append(marker);
                });
                if (view.options.draggable) {
                    track.addEventListener('dragover', (event) => event.preventDefault());
                    track.addEventListener('drop', (event) => {
                        event.preventDefault();
                        if (!dragged || typeof view.options.onMove !== 'function') return;
                        const rect = track.getBoundingClientRect();
                        const ratio = Math.max(0, Math.min(1, (event.clientX - rect.left) / Math.max(1, rect.width)));
                        view.options.onMove(dragged, ratio * duration);
                        dragged = null;
                    });
                }
            });
        }
    }
    const create = (adapter, options) => new Timeline(adapter, options);
    return {create: create, Timeline: Timeline};
});
