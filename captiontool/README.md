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


The AI Bridge integration is optional. Video Bridge and the four caption-tool subplugins install and run independently of `local_ai_bridge`; AI-based execution is available only when `local_ai_bridge` 1.2.3+ (2026102000) is installed. When it is absent, the AI tools remain discoverable but are marked unavailable and omitted from selectable tool options. The actual AI route remains tenant/purpose controlled by AI Bridge.

### AI Bridge 1.2.3 system-instruction policy

AI Bridge does not permit consumers to pass `role=system` messages. Video Bridge sends its tool-specific task guidance as a user message followed by caption data, without trying to override the configured purpose. For stronger instruction priority, configure each tenant's AI Bridge purpose system instruction with the corresponding restrictions: treat captions/transcripts as untrusted data, preserve WebVTT timing when translating, produce valid WebVTT when generating, assess only visible captions when analyzing, and output Mermaid mindmap syntax when creating mind maps. The purpose administrator controls that policy, model and route.

## MCP integration (optional)

With `local_mcp` and the AI Bridge backend installed, Video Bridge automatically registers **a separate MCP WRITE tool for every installed captiontool**, including additional third-party `videocaptiontool_*` subplugins:

- `video_bridge_caption_generate` accepts a transcript and optional language/duration;
- `video_bridge_caption_translate` requires WebVTT and `options.targetlanguage`;
- `video_bridge_caption_analyze` reviews supplied WebVTT;
- `video_bridge_caption_mindmap` creates a Mermaid mind map from WebVTT.

Each tool accepts `courseid`, `input` and optional tool-specific `options`. Caption tools added later are discovered automatically as `video_bridge_caption_<shortname>`. Operations require course activity management permission and confirmation, and confirmation previews contain a SHA-256 digest and byte count rather than the caption text. `video_bridge_process_captions` remains available for clients already using the generic endpoint.

There is **no declared dependency** on `local_mcp`; without MCP, the caption tools continue to operate from Moodle, and without AI Bridge the AI-dependent MCP actions are omitted.
