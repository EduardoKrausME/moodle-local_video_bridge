<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Caption text format helpers.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\captiontool;

use moodle_exception;

/**
 * Normalizes and validates AI-generated caption formats.
 */
class format {
    public static function strip_code_fence(string $content): string {
        $content = trim($content);
        if (preg_match('/^\x60\x60\x60[^\n]*\n(.*)\n\x60\x60\x60$/s', $content, $matches)) {
            return trim($matches[1]);
        }
        return $content;
    }

    public static function webvtt(string $content): string {
        $content = self::strip_code_fence($content);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", trim($content));

        if (!preg_match('/^WEBVTT\\b/i', $content)) {
            $content = "WEBVTT\n\n" . $content;
        }
        if (!str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        self::assert_webvtt($content);
        return $content;
    }

    public static function assert_webvtt(string $content): void {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        if (!preg_match('/^WEBVTT(?:[ \t].*)?\n/i', $normalized)) {
            throw new moodle_exception('invalidtoolwebvtt', 'local_video_bridge');
        }
        if (!preg_match(
            '/(?:^|\n)(?:\d{2}:)?\d{2}:\d{2}\.\d{3}[ \t]+-->[ \t]+(?:\d{2}:)?\d{2}:\d{2}\.\d{3}/m',
            $normalized
        )) {
            throw new moodle_exception('invalidtoolwebvtt', 'local_video_bridge');
        }
    }

    public static function timing_signature(string $content): array {
        self::assert_webvtt($content);
        preg_match_all(
            '/^(?:\d{2}:)?\d{2}:\d{2}\.\d{3}[ \t]+-->[ \t]+(?:\d{2}:)?\d{2}:\d{2}\.\d{3}[^\r\n]*$/m',
            str_replace(["\r\n", "\r"], "\n", $content),
            $matches
        );
        return array_map('trim', $matches[0] ?? []);
    }

    public static function plain_text(string $content): string {
        self::assert_webvtt($content);
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        $text = [];
        $skipblock = false;
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $line = trim($lines[$index]);

            if ($skipblock) {
                if ($line === '') {
                    $skipblock = false;
                }
                continue;
            }

            if (preg_match('/^(NOTE|STYLE|REGION)\\b/i', $line)) {
                $skipblock = true;
                continue;
            }

            if ($line === '' || preg_match('/^WEBVTT\\b/i', $line) || str_contains($line, '-->')) {
                continue;
            }

            $next = $index + 1 < $count ? trim($lines[$index + 1]) : '';
            if ($next !== '' && str_contains($next, '-->')) {
                continue;
            }

            $line = trim(strip_tags($line));
            if ($line !== '') {
                $text[] = $line;
            }
        }

        return implode("\n", $text);
    }

    public static function mermaid(string $content): string {
        $content = self::strip_code_fence($content);
        $content = trim($content);
        if (!preg_match('/^mindmap\b/i', $content)) {
            throw new moodle_exception('invalidmindmap', 'local_video_bridge');
        }
        return $content . "\n";
    }
}
