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
 * Shared caption source manager.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\caption;

use coding_exception;
use context_module;
use core_collator;
use core_component;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Discovers caption providers and exposes them to video consumer plugins.
 */
class manager {
    /** @var plugin_base[]|null Cached provider instances. */
    private ?array $plugins = null;

    /** @var string Field storing the caption provider short name. */
    private string $sourcefield;

    /** @var string Field storing normalized caption configuration. */
    private string $configfield;

    public function __construct(
        string $sourcefield = 'captionsource',
        string $configfield = 'captionconfig'
    ) {
        foreach ([$sourcefield, $configfield] as $field) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $field)) {
                throw new coding_exception('Invalid Video Bridge caption field name: ' . $field);
            }
        }

        $this->sourcefield = $sourcefield;
        $this->configfield = $configfield;
    }

    public function get_plugins(): array {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $plugins = [];
        foreach (array_keys(core_component::get_plugin_list('videocaptionsource')) as $name) {
            $class = '\\videocaptionsource_' . $name . '\\plugin';
            if (!class_exists($class) || !is_subclass_of($class, plugin_base::class)) {
                debugging(get_string('invalidcaptionplugin', 'local_video_bridge', $name), DEBUG_DEVELOPER);
                continue;
            }
            $plugins[$name] = new $class();
        }

        $groups = [];
        foreach ($plugins as $name => $plugin) {
            $groups[$plugin->get_sort_order()][$name] = $plugin->get_name();
        }
        ksort($groups, SORT_NUMERIC);

        $this->plugins = [];
        foreach ($groups as $displaynames) {
            core_collator::asort($displaynames);
            foreach (array_keys($displaynames) as $name) {
                $this->plugins[$name] = $plugins[$name];
            }
        }

        return $this->plugins;
    }

    public function get_plugin(string $name): plugin_base {
        $name = clean_param($name, PARAM_PLUGIN);
        $plugins = $this->get_plugins();
        if (!isset($plugins[$name])) {
            throw new moodle_exception('captionpluginmissing', 'local_video_bridge', '', $name);
        }
        return $plugins[$name];
    }

    public function get_options(): array {
        $options = [];
        foreach ($this->get_plugins() as $name => $plugin) {
            $options[$name] = $plugin->get_name();
        }
        return $options;
    }

    public function get_default_source(): string {
        return (string)(array_key_first($this->get_plugins()) ?? '');
    }

    public function add_form_elements(MoodleQuickForm $mform, ?string $sourcefield = null): void {
        $sourcefield ??= $this->sourcefield;
        foreach ($this->get_plugins() as $plugin) {
            $plugin->add_form_elements($mform, $sourcefield);
        }
    }

    public function validation(array $data, array $files): array {
        $source = clean_param((string)($data[$this->sourcefield] ?? ''), PARAM_PLUGIN);
        if ($source === '') {
            return [];
        }

        try {
            return $this->get_plugin($source)->validation($data, $files);
        } catch (moodle_exception $exception) {
            return [$this->sourcefield => $exception->getMessage()];
        }
    }

    public function normalise_record(stdClass $data): array {
        $source = clean_param((string)($data->{$this->sourcefield} ?? ''), PARAM_PLUGIN);
        if ($source === '') {
            $data->{$this->configfield} = '';
            return [];
        }

        $config = $this->get_plugin($source)->build_config($data);
        $data->{$this->configfield} = json_encode(
            $config,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        return $config;
    }

    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $source = clean_param((string)($defaultvalues[$this->sourcefield] ?? ''), PARAM_PLUGIN);
        if ($source === '') {
            return;
        }

        $hadsourcealias = array_key_exists('captionsource', $defaultvalues);
        $hadconfigalias = array_key_exists('captionconfig', $defaultvalues);

        $working = $defaultvalues;
        $working['captionsource'] = $source;
        $working['captionconfig'] = $defaultvalues[$this->configfield] ?? '';

        $this->get_plugin($source)->prepare_form_data($working, $context);
        $defaultvalues = array_replace($defaultvalues, $working);

        if ($this->sourcefield !== 'captionsource' && !$hadsourcealias) {
            unset($defaultvalues['captionsource']);
        }
        if ($this->configfield !== 'captionconfig' && !$hadconfigalias) {
            unset($defaultvalues['captionconfig']);
        }
    }

    public function save_files(stdClass $data, context_module $context, ?string $previoussource = null): void {
        $source = clean_param((string)($data->{$this->sourcefield} ?? ''), PARAM_PLUGIN);
        $plugins = $this->get_plugins();

        if ($previoussource && $previoussource !== $source && isset($plugins[$previoussource])) {
            $plugins[$previoussource]->delete_files($context);
        }

        if ($source === '') {
            return;
        }

        $this->get_plugin($source)->save_files($data, $context);
    }

    public function delete_files(context_module $context): void {
        foreach ($this->get_plugins() as $plugin) {
            $plugin->delete_files($context);
        }
    }

    public function get_tracks(stdClass $activity, context_module $context): array {
        $record = $this->provider_record($activity);
        $source = clean_param((string)$record->captionsource, PARAM_PLUGIN);
        if ($source === '') {
            return [];
        }

        $tracks = $this->get_plugin($source)->get_tracks($record, $context);
        $normalized = [];
        foreach ($tracks as $track) {
            if (!is_array($track) || empty($track['url'])) {
                continue;
            }

            $language = trim((string)($track['language'] ?? 'und'));
            $label = trim((string)($track['label'] ?? $language));
            $normalized[] = [
                'url' => (string)$track['url'],
                'language' => $language !== '' ? $language : 'und',
                'label' => $label !== '' ? $label : ($language !== '' ? $language : 'Caption'),
                'isdefault' => !empty($track['isdefault']),
            ];
        }

        return $normalized;
    }

    private function provider_record(stdClass $record): stdClass {
        $copy = clone $record;
        $copy->captionsource = $record->{$this->sourcefield} ?? '';
        $copy->captionconfig = $record->{$this->configfield} ?? '';
        return $copy;
    }
}
