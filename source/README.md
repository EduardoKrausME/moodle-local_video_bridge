# Shared video sources

Video Bridge owns the source subplugin type stored under `local/video_bridge/source/<name>`.

For backwards compatibility the technical type remains `videoprogresssource` and bundled components remain `videoprogresssource_<name>`. This is only the Moodle component identifier; providers are shared and must not contain Video Progress business rules.

A provider owns source-specific form fields, validation, normalized configuration, source files when needed, its Mustache player template and its AMD adapter. Completion, grades, anti-skip, tracking policy, annotations, branching, questions and analytics belong to the consumer activity.

Bundled providers currently cover protected Moodle uploads, direct HTML5/HLS URLs, YouTube, Vimeo, public Nextcloud shares, Google Drive preview, Panda Video, Bunny Stream, Qencode playback URLs, OTTFlix and generic/cooperative iframe embeds.

Providers declare guaranteed player capabilities through `get_capabilities()`:

- `tracking`: current time/duration updates can be trusted;
- `seeking`: a consumer can move the remote player;
- `playbackcontrol`: play/pause can be controlled programmatically;
- `playbackrate`: playback speed can be read and controlled.

The AMD module returns an object with `create(root, config)`. The resulting adapter implements the common calling surface even if some methods are no-ops for an unsupported capability.

Generated AMD output belongs only in `amd/build/*.min.js`. Files named `*.min.min.js` are invalid and must not be committed.
