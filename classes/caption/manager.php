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
use local_video_bridge\media\config as media_config;
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

    /**
     * Initializes the caption manager.
     *
     * @param string $sourcefield Caption source field name.
     * @param string $configfield Caption configuration field name.
     */
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

    /**
     * Returns discovered caption source plugins.
     *
     * @return array Return value.
     */
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

    /**
     * Returns one caption source plugin.
     *
     * @param string $name Plugin short name.
     * @return plugin_base Return value.
     */
    public function get_plugin(string $name): plugin_base {
        $name = clean_param($name, PARAM_PLUGIN);
        $plugins = $this->get_plugins();
        if (!isset($plugins[$name])) {
            throw new moodle_exception('captionpluginmissing', 'local_video_bridge', '', $name);
        }
        return $plugins[$name];
    }

    /**
     * Returns caption source options for forms.
     *
     * @return array Return value.
     */
    public function get_options(): array {
        $options = [];
        foreach ($this->get_plugins() as $name => $plugin) {
            $options[$name] = $plugin->get_name();
        }
        return $options;
    }

    /**
     * Returns the default caption source.
     *
     * @return string Return value.
     */
    public function get_default_source(): string {
        return (string)(array_key_first($this->get_plugins()) ?? '');
    }

    /**
     * Adds caption source form elements.
     *
     * @param MoodleQuickForm $mform Moodle form instance.
     * @param ?string $sourcefield Caption source field name.
     * @return void Return value.
     */
    public function add_form_elements(MoodleQuickForm $mform, ?string $sourcefield = null): void {
        $sourcefield ??= $this->sourcefield;
        foreach ($this->get_plugins() as $plugin) {
            $plugin->add_form_elements($mform, $sourcefield);
        }
    }

    /**
     * Validates caption source form data.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Return value.
     */
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

    /**
     * Normalizes caption source configuration on an activity record.
     *
     * @param stdClass $data Submitted form data.
     * @return array Return value.
     */
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

    /**
     * Prepares caption values for the edit form.
     *
     * @param array $defaultvalues Form default values.
     * @param context_module $context Module context.
     * @return void Return value.
     */
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

    /**
     * Saves files owned by the selected caption source.
     *
     * @param stdClass $data Submitted form data.
     * @param context_module $context Module context.
     * @param ?string $previoussource Previously selected caption source.
     * @return void Return value.
     */
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

    /**
     * Prepares caption fields for one media item.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return void
     */
    public function prepare_form_data_for_media(
        array &$defaultvalues,
        context_module $context,
        int $mediaid
    ): void {
        $source = clean_param((string)($defaultvalues[$this->sourcefield] ?? ''), PARAM_PLUGIN);
        if ($source === '') {
            return;
        }

        $working = $defaultvalues;
        $working['captionsource'] = $source;
        $working['captionconfig'] = $defaultvalues[$this->configfield] ?? '';
        $this->get_plugin($source)->prepare_media_form_data($working, $context, $mediaid);
        $defaultvalues = array_replace($defaultvalues, $working);
    }

    /**
     * Saves caption files for one media item.
     *
     * @param stdClass $data Form data.
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @param string|null $previoussource Previous source.
     * @return void
     */
    public function save_files_for_media(
        stdClass $data,
        context_module $context,
        int $mediaid,
        ?string $previoussource = null
    ): void {
        $source = clean_param((string)($data->{$this->sourcefield} ?? ''), PARAM_PLUGIN);
        $plugins = $this->get_plugins();

        if ($previoussource && $previoussource !== $source && isset($plugins[$previoussource])) {
            $plugins[$previoussource]->delete_media_files($context, $mediaid);
        }
        if ($source !== '') {
            $this->get_plugin($source)->save_media_files($data, $context, $mediaid);
        }
    }

    /**
     * Deletes caption files for one media item.
     *
     * @param context_module $context Module context.
     * @param int $mediaid Media item id.
     * @return void
     */
    public function delete_files_for_media(context_module $context, int $mediaid): void {
        foreach ($this->get_plugins() as $plugin) {
            $plugin->delete_media_files($context, $mediaid);
        }
    }

    /**
     * Deletes files owned by caption sources.
     *
     * @param context_module $context Module context.
     * @return void Return value.
     */
    public function delete_files(context_module $context): void {
        foreach ($this->get_plugins() as $plugin) {
            $plugin->delete_files($context);
        }
    }

    /**
     * Returns normalized browser-ready caption tracks.
     *
     * @param stdClass $activity Activity record.
     * @param context_module $context Module context.
     * @return array Return value.
     */
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

    /**
     * Returns normalized tracks for a generic media item.
     *
     * @param media_config $media Media configuration.
     * @param context_module $context Module context.
     * @return array Browser-ready caption tracks.
     */
    public function get_tracks_for_media(media_config $media, context_module $context): array {
        $source = $media->get_captionsource();
        if ($source === '') {
            return [];
        }

        $tracks = $this->get_plugin($source)->get_tracks_for_media(
            $media->to_caption_record(),
            $context,
            $media->get_mediaid()
        );

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

    /**
     * Builds the provider-facing activity record.
     *
     * @param stdClass $record Activity record.
     * @return stdClass Return value.
     */
    private function provider_record(stdClass $record): stdClass {
        $copy = clone $record;
        $copy->captionsource = $record->{$this->sourcefield} ?? '';
        $copy->captionconfig = $record->{$this->configfield} ?? '';
        return $copy;
    }
}
