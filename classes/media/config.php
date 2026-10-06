<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Generic media configuration for multi-media Video Bridge consumers.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\media;

use coding_exception;
use stdClass;

/**
 * Describes one configured media item independently from an activity record.
 */
final class config {
    /** @var string Video source short name. */
    private string $source;

    /** @var string Normalized source configuration. */
    private string $sourceconfig;

    /** @var int Stable media item id inside the consumer activity. */
    private int $mediaid;

    /** @var string Optional legacy provider value. */
    private string $legacyvalue;

    /** @var string Optional caption source short name. */
    private string $captionsource;

    /** @var string Optional normalized caption configuration. */
    private string $captionconfig;

    /** @var string Optional browser-ready poster URL. */
    private string $posterurl;

    /**
     * Creates a media configuration.
     *
     * mediaid=0 is reserved for legacy single-media consumers.
     *
     * @param string $source Source short name.
     * @param string $sourceconfig Normalized source JSON.
     * @param int $mediaid Stable media item id.
     * @param string $legacyvalue Legacy provider value.
     * @param string $captionsource Caption source short name.
     * @param string $captionconfig Normalized caption JSON.
     * @param string $posterurl Poster URL.
     */
    public function __construct(
        string $source,
        string $sourceconfig,
        int $mediaid,
        string $legacyvalue = '',
        string $captionsource = '',
        string $captionconfig = '',
        string $posterurl = ''
    ) {
        $source = clean_param($source, PARAM_PLUGIN);
        $captionsource = clean_param($captionsource, PARAM_PLUGIN);
        if ($source === '') {
            throw new coding_exception('Video Bridge media source cannot be empty.');
        }
        if ($mediaid < 0) {
            throw new coding_exception('Video Bridge media id cannot be negative.');
        }

        $this->source = $source;
        $this->sourceconfig = $sourceconfig;
        $this->mediaid = $mediaid;
        $this->legacyvalue = $legacyvalue;
        $this->captionsource = $captionsource;
        $this->captionconfig = $captionconfig;
        $this->posterurl = $posterurl;
    }

    /** @return string Source short name. */
    public function get_source(): string {
        return $this->source;
    }

    /** @return string Normalized source configuration. */
    public function get_sourceconfig(): string {
        return $this->sourceconfig;
    }

    /** @return int Stable media item id. */
    public function get_mediaid(): int {
        return $this->mediaid;
    }

    /** @return string Caption source short name. */
    public function get_captionsource(): string {
        return $this->captionsource;
    }

    /** @return string Normalized caption configuration. */
    public function get_captionconfig(): string {
        return $this->captionconfig;
    }

    /** @return string Poster URL. */
    public function get_posterurl(): string {
        return $this->posterurl;
    }

    /**
     * Returns the progress identity for this media.
     *
     * @return string SHA-256 media identity.
     */
    public function get_mediahash(): string {
        if ($this->mediaid === 0) {
            return \local_video_bridge\analytics::media_hash($this->source, $this->sourceconfig);
        }

        return hash(
            'sha256',
            $this->source . '|' . $this->sourceconfig . '|media:' . $this->mediaid
        );
    }

    /**
     * Returns a source-provider compatible record.
     *
     * @return stdClass Provider record.
     */
    public function to_source_record(): stdClass {
        return (object)[
            'id' => $this->mediaid,
            'mediaid' => $this->mediaid,
            'videosource' => $this->source,
            'sourceconfig' => $this->sourceconfig,
            'videourl' => $this->legacyvalue,
            'poster' => $this->posterurl,
        ];
    }

    /**
     * Returns a caption-provider compatible record.
     *
     * @return stdClass Provider record.
     */
    public function to_caption_record(): stdClass {
        return (object)[
            'id' => $this->mediaid,
            'mediaid' => $this->mediaid,
            'captionsource' => $this->captionsource,
            'captionconfig' => $this->captionconfig,
        ];
    }
}
