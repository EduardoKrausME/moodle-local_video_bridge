# Shared video sources

Video Bridge owns the source subplugin type stored under `local/video_bridge/source/<name>`.

For backwards compatibility the technical type remains `videoprogresssource` and bundled components remain `videoprogresssource_<name>`. This is only the Moodle component identifier; the providers are shared and must not contain Video Progress business rules.

A provider owns source-specific form fields, validation, normalized configuration, source files when needed, its Mustache player template and its AMD adapter. Completion, grades, anti-skip, tracking, annotations, branching, questions and analytics belong to the consumer activity.

The AMD module returns an object with `create(root, config)`. The resulting adapter implements:

```text
play()
pause()
getCurrentTime()
getDuration()
getPlaybackRate()
seek(position)
onPlay(handler)
onPause(handler)
onTimeUpdate(handler)
onSeek(handler)
onEnded(handler)
onRateChange(handler)
```

Generated AMD output belongs only in `amd/build/*.min.js`. Files named `*.min.min.js` are invalid and must not be committed.
