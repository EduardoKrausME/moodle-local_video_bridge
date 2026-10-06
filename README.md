# Moodle Video Bridge

Video Bridge is the shared source/provider layer for Moodle video activities. The repository deliberately contains source infrastructure only; progress, grading, completion, quizzes, annotations, branching, discussions and other activity rules stay in the consumer plugin.

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
