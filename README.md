# Moodle Video Bridge

Video Bridge is the shared video infrastructure for Moodle video activities. It centralizes source adapters and the learner's normalized playback progress/viewing map, while grading, completion rules, quizzes, annotations, branching, discussions and other pedagogical rules stay in the consumer plugin.

Bundled sources:

- protected Moodle upload;
- direct HTML5/HLS URL;
- YouTube;
- Vimeo;
- public Nextcloud shares;
- Google Drive preview;
- Panda Video;
- Bunny Stream;
- Qencode playback URLs;
- OTTFlix;
- generic/cooperative iframe embed.

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


Caption consumers use `local_video_bridge\caption\manager`. By default it expects `captionsource` and `captionconfig`, but consumers may map those aliases to their own schema. Caption providers return browser-ready tracks with `url`, `language`, `label` and `isdefault`.


AI caption operations are extensible through the `videocaptiontool` subplugin type. The bundled tools use `local_ai_bridge` rather than calling AI vendors directly:

- `videocaptiontool_generate` converts transcript text into reviewable WebVTT;
- `videocaptiontool_translate` translates WebVTT while preserving cue timings;
- `videocaptiontool_analyze` reviews caption quality, language and accessibility;
- `videocaptiontool_mindmap` turns caption content into a Mermaid mind map.

Each tool has its own configurable AI Bridge purpose idnumber, so tenants may route each operation to different providers, models, roles, limits and credit costs.


## Shared viewing map and progress

Every source that declares reliable `tracking` automatically receives the Video Bridge progress tracker. Consumer activities do not need their own progress table, AJAX service, percentage calculation or viewing-map JavaScript.

The tracker keeps watched buckets in browser memory, persists them at most once every 60 seconds, and sends one final best-effort payload with `navigator.sendBeacon()` when the page is closed or navigated away from. Seeking never fills skipped ranges: only buckets reached by actual player progress events are marked.

Progress is stored per module context, consumer component, activity instance, media hash and user. Videos longer than 100 seconds use 100 normalized buckets; shorter videos use approximately one bucket per second. The server calculates the authoritative percentage from the merged map and never trusts a percentage supplied by the browser.

`local_video_bridge\progress\manager` exposes the consolidated state for reports or activity logic, including current position, duration, watched percentage and normalized map.
