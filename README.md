# Moodle Video Bridge

Video Bridge is the shared video infrastructure for Moodle video activities. It centralizes source adapters and the learner's normalized playback progress/viewing map, while grading, completion rules, quizzes, annotations, branching, discussions and other pedagogical rules stay in the consumer plugin.

## Moodle activities using Video Bridge

The following activity plugins use `local_video_bridge` for shared video playback, source adapters, progress tracking or analytics. The consuming activities are separate plugins with their own requirements:

- [Video Progress (`mod_videoprogress`)](https://github.com/EduardoKrausME/moodle-mod_videoprogress)
- [Video Summary (`mod_videosummary`)](https://github.com/EduardoKrausME/moodle-mod_videosummary)
- [Video Reactions (`mod_videoreactions`)](https://github.com/EduardoKrausME/moodle-mod_videoreactions)
- [Video Tracker Pro (`mod_videotrackerpro`)](https://github.com/EduardoKrausME/moodle-mod_videotrackerpro)
- [Video Tracker Max (`mod_videotrackermax`)](https://github.com/EduardoKrausME/moodle-mod_videotrackermax)
- [Video Tracker Prime (`mod_videotrackerprime`)](https://github.com/EduardoKrausME/moodle-mod_videotrackerprime)
- [Video Tracker Ultra (`mod_videotrackerultra`)](https://github.com/EduardoKrausME/moodle-mod_videotrackerultra)
- [Video Tracker Ultimate (`mod_videotrackerultimate`)](https://github.com/EduardoKrausME/moodle-mod_videotrackerultimate)
- [Video Tracker Premium (`mod_videotrackerpremium`)](https://github.com/EduardoKrausME/moodle-mod_videotrackerpremium)

Video Bridge requires **Moodle 4.5+** (2024100700). `local_ai_bridge` 1.2.3+ is optional and needed only to run AI caption tools. Without it, video playback, progress, analytics and caption uploads continue to work, while AI caption execution is unavailable. The consuming plugins are separate packages.

Maintained video sources:

- protected Moodle upload;
- direct HTML5/HLS URL;
- YouTube;
- Vimeo;
- Google Drive preview;
- Panda Video;
- Bunny Stream;
- Qencode playback URLs;
- generic/cooperative iframe embed.

Legacy source adapters may remain in older installations or distributions for compatibility with existing activity records; they are not part of the actively documented provider list. Do not delete an installed adapter before migrating activities configured to use it.

Caption providers are also extensible through the `videocaptionsource` subplugin type. The bundled upload provider stores protected WebVTT captions in Moodle, accepts VTT/SRT input and can expose multiple normalized tracks to any consumer.

The source plugins keep the historical `videoprogresssource_*` component prefix so existing source configuration and backups do not become a second set of Moodle plugins. Despite that technical prefix, discovery, shared adapters and runtime assets belong to `local_video_bridge` and may be consumed by any activity.

Consumers use `local_video_bridge\source\manager`. By default it expects `videosource`, `sourceconfig` and `videourl`, but different field names can be passed to the constructor.

```php
$manager = new \local_video_bridge\source\manager(
    sourcefield: 'videosource',
    configfield: 'sourceconfig',
    legacyfield: 'videourl'
);

$options = $manager->get_options();
$trackingoptions = $manager->get_options(['tracking']);
$manager->add_form_elements($mform);
$manager->normalise_record($data);
$player = $manager->get_player_config($activity, $context);
```

A provider also declares the capabilities a consumer may rely on: `tracking`, `seeking`, `playbackcontrol` and `playbackrate`. This matters for iframe-based providers such as Google Drive where playback is valid but a progress-oriented activity cannot reliably inspect or control the remote player.

Every AMD adapter still exposes the same safe calling surface: `play()`, `pause()`, `getCurrentTime()`, `getDuration()`, `getPlaybackRate()`, `seek()`, and normalized event registration methods. For unsupported capabilities those methods are best-effort/no-op and consumers must use the provider capability flags before making a feature mandatory.

HLS and Vimeo runtime libraries are shipped by Video Bridge, so a consumer never needs files from `mod_videoprogress`.

### Provider-hosted playback scripts

YouTube loads the official IFrame API from `https://www.youtube.com/iframe_api`, and Bunny Stream loads its version-pinned Player.js integration from `https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js`. These are intentional provider-hosted integrations, not locally bundled runtime dependencies. They execute in the Moodle page context, so administrators must trust these providers, review the site's Content Security Policy and outbound connectivity requirements, and monitor provider version/security changes. Do not blanket-allow third-party script hosts in CSP. Self-hosting an upstream script requires a separate licensing and compatibility review; copying vendor-managed player code is not automatically a security improvement.


Caption consumers use `local_video_bridge\caption\manager`. By default it expects `captionsource` and `captionconfig`, but consumers may map those aliases to their own schema. Caption providers return browser-ready tracks with `url`, `language`, `label` and `isdefault`.

AI caption operations are extensible through the `videocaptiontool` subplugin type. The bundled tools use `local_ai_bridge` rather than calling AI vendors directly:

- `videocaptiontool_generate` converts transcript text into reviewable WebVTT;
- `videocaptiontool_translate` translates WebVTT while preserving cue timings;
- `videocaptiontool_analyze` reviews caption quality, language and accessibility;
- `videocaptiontool_mindmap` turns caption content into a Mermaid mind map.

Each tool has its own configurable AI Bridge purpose idnumber, so tenants may route each operation to different providers, models, roles, limits and credit costs.

## Shared viewing map and progress

Every source that declares reliable `tracking` automatically receives the Video Bridge progress tracker. Consumer activities do not need their own progress table, AJAX service, percentage calculation or viewing-map JavaScript.

The tracker keeps watched buckets in browser memory, persists them at most once every 60 seconds, and sends one final best-effort payload with `navigator.sendBeacon()` when the page is closed or navigated away from. Both periodic saves and the final beacon now call the registered `local_video_bridge_save_progress` Moodle External Service, using `core/ajax` and `/lib/ajax/service.php` respectively. The historical `progress.php` endpoint is retained temporarily for clients with cached older JavaScript. Seeking never fills skipped ranges: only buckets reached by actual player progress events are marked.

Progress is stored per module context, consumer component, activity instance, media hash and user. Videos longer than 100 seconds use 100 normalized buckets; shorter videos use approximately one bucket per second. The server calculates the authoritative percentage from the merged map and never trusts a percentage supplied by the browser.

`local_video_bridge\progress\manager` exposes the consolidated state for reports or activity logic, including current position, duration, watched percentage and normalized map.

## Telemetry and analytics API

Video Bridge exposes three telemetry levels for consumers:

- `OFF`: no playback progress or session telemetry is persisted;
- `BASIC`: authoritative normalized progress plus compact session metrics;
- `DETAILED`: BASIC plus compact watched ranges and positional pause/skip/replay data.

Consumers opt in while building the shared player:

```php
$player = $manager->get_player_config(
    $activity,
    $context,
    \local_video_bridge\analytics::LEVEL_DETAILED
);
```

Detailed telemetry never stores one row per `timeupdate`. One session row is updated in place and watched intervals are compacted, for example `[[0,42],[44,103],[98,160]]`.

Analytics consumers should use the public methods `local_video_bridge\analytics::media_hash()`, `get_watched_ranges()`, `get_session_metrics()` and `get_activity_coverage()`. Direct reads from Video Bridge internal tables are intentionally unnecessary.

`get_session_metrics()` exposes a normalized `reachedend` flag based on the explicit player `ended` event. Incremental consumers may page with `modifiedafter`, `modifiedafterid` and `limit`; the bridge keeps session modification cursors monotonic so a final beacon cannot disappear behind a same-second update. Collective coverage treats a normal `ended` event as completion rather than drop-off, while sessions closed elsewhere contribute to abandonment at their last reported position.

## Optional detailed analytics

Consumers that need more than the lightweight viewing map can request `detailed` telemetry when calling `local_video_bridge\source\manager::get_player_config()`. Detailed telemetry is optional so ordinary video activities do not create unnecessary session data.

`local_video_bridge\analytics\manager` exposes provider-independent facts such as watched percentage, unique watched time, playback time, real session time, maximum and average playback rate, forward/backward seeks, largest forward seek, pause count, session count, end reached, watched ranges, continuous playback blocks and inactivity gaps.

The analytics manager returns facts only. Completion policies, required segments, penalties and other pedagogical rules belong in the consumer activity.

## Multi-media consumers

Activities that contain several independent videos inside the same module instance should use
`local_video_bridge\media\config` and `source\manager::get_player_config_for_media()`.
The media item id becomes part of the media identity, so two items configured with the same
provider/media still have independent progress, resume position, uploaded files and captions.

```php
$media = new \local_video_bridge\media\config(
    source: $item->source,
    sourceconfig: $item->sourceconfig,
    mediaid: (int)$item->id
);

$consumer = new \local_video_bridge\progress\consumer(
    'mod_example',
    (int)$activity->id
);

$player = $manager->get_player_config_for_media(
    $media,
    $context,
    $consumer,
    requiredcapabilities: ['tracking']
);
```

Multi-media reports should use `progress\manager::get_progress_bulk()`, which loads a
user × media matrix with one query rather than issuing a query for every table cell. The
bridge remains the owner of viewing maps and normalized progress; consumer plugins only
store their own media catalog and pedagogical/compliance rules.

## Generic timeline cues

`local_video_bridge/timeline` is a semantic-free AMD engine for consumers that need timestamp cues. It registers cues against the normalized adapter, detects effective continuous playback, ignores forward discontinuities/seeks, prevents accidental duplicate triggers, exposes the current time and seek operation, and can render reusable timeline markers.

The bridge does not define pedagogical cue types. A consumer may use payloads such as reflection, confidence, poll or message, but those meanings, persistence rules and UI remain in the consumer plugin.

```javascript
const timeline = Timeline.create(adapter);
timeline.register({id: 'intro', time: 42, type: 'consumer-defined', payload: {}, once: true});
timeline.onTrigger((cue) => console.log(cue));
timeline.getCurrentTime();
timeline.seekTo(42);
```

For reports, `local_video_bridge\\progress\\manager::media_hash()` and `get_activity_progress()` expose the same normalized progress identity and aggregate records without consumers coupling themselves to the bridge table schema.

## Progress thresholds and consumer events

Consumer activities can read authoritative progress in batches with `local_video_bridge\progress\manager::get_progress_batch()` and obtain latest session timestamps with `get_latest_session_times()`. Consumers that need to react to a meaningful percentage can register one threshold with `set_threshold()` or query it directly with `has_reached()`.

Video Bridge emits public Moodle events after authoritative server-side progress changes:

- `local_video_bridge\event\progress_updated` when the stored percentage changes;
- `local_video_bridge\event\progress_threshold_reached` only when a registered threshold is crossed;
- `local_video_bridge\event\video_completed` when progress first reaches 100%.

Threshold registration contains only consumer identifiers, media hash and percentage. Operational policies such as reminders, deadlines or compliance remain the responsibility of consumer plugins.

### Optional Moodle MCP integration

If `local_mcp` is installed, Video Bridge is discovered automatically through `classes/mcp/provider.php`, with no hard dependency on MCP. READ tools `video_bridge_list_providers` and `video_bridge_get_my_progress` expose the installed video sources, caption providers/tools and the authenticated learner's own activity progress (subject to Moodle course/module visibility and capabilities). This includes all installed source subplugins and consumer activities using the shared progress storage. When AI Bridge is installed, the confirmable WRITE tool `video_bridge_process_captions` can run any available caption tool (generate, translate, analyze or mindmap) for users with course activity management permission. It shows a content hash and size in previews instead of repeating the supplied caption text. No source credentials, private configuration or other learners' records are exposed.

The integration works without either `local_mcp` or `local_ai_bridge` installed; install or remove those plugins without changing Video Bridge dependencies. Refresh MCP tool discovery after changing installed plugins.
