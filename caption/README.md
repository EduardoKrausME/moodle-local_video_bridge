# Shared caption sources

Video Bridge owns the caption source subplugin type stored under `local/video_bridge/caption/<name>`.

The technical plugin type is `videocaptionsource`, so a provider component is named `videocaptionsource_<name>`.

A caption provider owns its source-specific form fields, validation, normalized configuration, files and the track metadata returned to a consumer. Consumers remain responsible for deciding where captions appear and for merging the returned tracks into their player configuration.

Consumers use `local_video_bridge\caption\manager`. By default it expects `captionsource` and `captionconfig`, but alternate field names can be passed to the constructor.

```php
$manager = new \local_video_bridge\caption\manager(
    sourcefield: 'captionsource',
    configfield: 'captionconfig'
);

$options = $manager->get_options();
$manager->add_form_elements($mform);
$manager->normalise_record($data);
$manager->save_files($data, $context, $previoussource);
$tracks = $manager->get_tracks($activity, $context);
```

Every returned track uses the same browser-facing structure:

```php
[
    'url' => '...',
    'language' => 'pt-BR',
    'label' => 'Português',
    'isdefault' => true,
]
```

The bundled `upload` provider accepts multiple `.vtt` and `.srt` files. SRT is normalized to WebVTT before storage. File names may encode metadata using `language__label.default.vtt`, for example `pt-BR__Português.default.vtt`.
