# Changelog

This changelog covers only the two latest **published** releases of `local_video_bridge`, verified on October 24, 2026. Each entry summarizes the changes from its immediately preceding published Git tag. Changes to files in the comparisons, including workflow files, are historical release changes; creating this changelog does not modify them.

## [1.9.4] - 2026-10-21

### Fixed
- Fixed the Moodle Privacy API user-list SQL calls in `classes/privacy/provider.php` by supplying the required `userid` selector argument to `userlist::add_from_sql()`.
- Normalized `sendBeacon` spelling in PHP comments for Moodle Code Checker compliance.

### Tests and security coverage
- Added regression coverage for server-registered media identity validation: progress submitted for an unregistered media hash or a mismatched source must be rejected.
- Added tests proving that arbitrary client-generated session IDs do not create unlimited telemetry rows and that an existing active session can be reused after the per-hour limit.
- Added Privacy API tests for discovering users with video progress/telemetry, selectively deleting approved users' records in the selected context, and retaining other users' and contexts' records.
- Added source-security tests that reject arbitrary Bunny Stream iframe query parameters and preserve the access hash for unlisted Vimeo URLs.

### Release metadata
- Updated `version.php` metadata for release `1.9.4` and reformatted the existing `local_ai_bridge` dependency declaration without changing its minimum version.

**Full comparison:** [1.9.3...1.9.4](https://github.com/EduardoKrausME/moodle-local_video_bridge/compare/1.9.3...1.9.4)

## [1.9.3] - 2026-10-21

### Security and progress tracking
- Added an expiring, session-scoped allowlist for server-rendered media identities, limited to 256 entries, and required a matching registered context, consumer, item, source and media hash before saving progress via AJAX or `sendBeacon`.
- Bounded stored playback-telemetry sessions per user and media: a maximum of 60 recent session rows per hour and 2,000 overall, reusing an active session when possible.
- Returned generic JSON errors from the beacon progress endpoint rather than exposing exception details; enabled Moodle's AJAX response handling.
- Validated and canonicalized Bunny Stream player query parameters using an explicit allowlist and escaped the iframe URL in its Mustache template.

### Player compatibility
- Restored the upstream Vimeo Player SDK v2.29.2 and switched the Vimeo integration to load the SDK through Moodle RequireJS.
- Added support for unlisted Vimeo video URLs whose access hash is part of the playback URL.

### Privacy and file migration
- Added Moodle Privacy API user-list discovery and selective multi-user deletion for stored video progress and session telemetry.
- Changed migration of historical `mod_videoprogress` uploaded files to use Moodle's File API instead of changing the `files.component` field directly.
- Added a repair upgrade step for prior files with inconsistent File API pathname hashes, preserving stored content while re-creating affected records.

### Other changes
- Removed the `source/nextcloud` and `source/ottflix` source-adapter directories.
- Documented Moodle activity plugins that depend on Video Bridge in `README.md`.
- Applied Mustache, language-string, licensing-header and JavaScript build maintenance across the main plugin and bundled subplugins.
- Added Git-ref-based concurrency handling to the automatic-release workflow as part of the historical release diff.
- Updated `version.php` metadata for release `1.9.3`.

**Full comparison:** [1.9.2...1.9.3](https://github.com/EduardoKrausME/moodle-local_video_bridge/compare/1.9.2...1.9.3)
