<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Video Bridge progress consumer identity.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\progress;

use coding_exception;
use context_module;

/**
 * Identifies the Moodle component and activity instance that own media progress.
 */
final class consumer {
    /** @var string Moodle component. */
    private string $component;

    /** @var int Consumer activity instance id. */
    private int $itemid;

    /**
     * Creates a consumer identity.
     *
     * @param string $component Moodle component.
     * @param int $itemid Activity instance id.
     */
    public function __construct(string $component, int $itemid) {
        $component = clean_param($component, PARAM_COMPONENT);
        if ($component === '' || $itemid < 1) {
            throw new coding_exception('Invalid Video Bridge progress consumer.');
        }
        $this->component = $component;
        $this->itemid = $itemid;
    }

    /**
     * Derives the owner from a module context.
     *
     * @param context_module $context Module context.
     * @return self
     */
    public static function from_context(context_module $context): self {
        $cm = get_coursemodule_from_id(null, $context->instanceid, 0, false, MUST_EXIST);
        return new self('mod_' . $cm->modname, (int)$cm->instance);
    }

    /** @return string Moodle component. */
    public function get_component(): string {
        return $this->component;
    }

    /** @return int Activity instance id. */
    public function get_itemid(): int {
        return $this->itemid;
    }
}
