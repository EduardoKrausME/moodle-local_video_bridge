# Shared caption tools

Video Bridge owns the caption-processing subplugin type stored under `local/video_bridge/captiontool/<name>`.

The technical plugin type is `videocaptiontool`, so components are named `videocaptiontool_<name>`. Caption tools process text or WebVTT and return a normalized `local_video_bridge\captiontool\result`.

Bundled tools:

- `generate`: transcript/plain text -> WebVTT;
- `translate`: WebVTT -> translated WebVTT with the original timing signature enforced;
- `analyze`: WebVTT -> Markdown quality/accessibility analysis;
- `mindmap`: WebVTT -> Mermaid `mindmap`.

All four bundled tools call `local_ai_bridge\api::generate()`. They never store provider credentials and never select an AI vendor or model directly. Each tool has a configurable AI Bridge purpose idnumber, which lets every tenant route the operation through its own providers, models, roles and credit policy.

Example:

```php
$tools = new \local_video_bridge\captiontool\manager();

$result = $tools->execute('translate', $webvtt, [
    'targetlanguage' => 'en-US',
]);

echo $result->format;   // webvtt
echo $result->content;
```

The `generate` tool works from transcript text. The current `local_ai_bridge` contract is text generation only and does not expose audio transcription, so generating captions directly from a video/audio file is intentionally not faked here. A future transcription capability should be implemented in `local_ai_bridge` itself and then consumed by this tool.


Because the bundled caption tools call `local_ai_bridge`, Video Bridge now requires Moodle 4.5+ and `local_ai_bridge` 1.0.1 or newer. The actual AI route is still tenant/purpose controlled by AI Bridge.
