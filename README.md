# Moodle Video Bridge

Video Bridge is the shared video-source layer for Moodle video activities. The repository deliberately contains source/provider infrastructure only; progress tracking, grading, completion, quizzes, annotations, branching, discussions and other activity rules remain in the consumer plugins.

Bundled sources are Moodle protected upload, direct MP4/WebM/OGV/MOV/M4V/HLS URL, YouTube, Vimeo and public Nextcloud shares.

The source plugins keep the historical `videoprogresssource_*` component prefix so existing source configuration and backups do not become a second set of Moodle plugins. Despite that technical prefix, discovery, common adapters and runtime assets now belong to `local_video_bridge` and can be consumed by any activity.

Consumers use `local_video_bridge\source\manager`. By default it expects `videosource`, `sourceconfig` and `videourl`, but different field names can be passed to the constructor.

```php
$manager = new \local_video_bridge\source\manager(
    sourcefield: 'videosource',
    configfield: 'sourceconfig',
    legacyfield: 'videourl'
);

$options = $manager->get_options();
$manager->add_form_elements($mform);
$manager->normalise_record($data);
$player = $manager->get_player_config($activity, $context);
```

Every browser adapter exposes the same playback contract: `play()`, `pause()`, `getCurrentTime()`, `getDuration()`, `getPlaybackRate()`, `seek()`, and normalized play/pause/time/seek/end/rate events. HLS and Vimeo runtime libraries are shipped by Video Bridge, so a consumer no longer needs files from `mod_videoprogress`.
