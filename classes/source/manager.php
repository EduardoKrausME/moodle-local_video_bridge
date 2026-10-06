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
 * Shared video source manager.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_video_bridge\source;

use coding_exception;
use context_module;
use core_collator;
use core_component;
use moodle_exception;
use moodle_url;
use local_video_bridge\caption\manager as caption_manager;
use local_video_bridge\media\config as media_config;
use local_video_bridge\progress\consumer;
use local_video_bridge\progress\manager as progress_manager;
use MoodleQuickForm;
use stdClass;
use stored_file;

/**
 * Discovers shared source providers and exposes them to any video activity.
 */
class manager {
    /** @var plugin_base[]|null Cached provider instances. */
    private ?array $plugins = null;

    /** @var string Field storing the provider short name. */
    private string $sourcefield;

    /** @var string Field storing normalized JSON configuration. */
    private string $configfield;

    /** @var string|null Optional field storing a legacy provider value. */
    private ?string $legacyfield;

    /**
     * Creates a manager for a consumer plugin database schema.
     *
     * @param string $sourcefield Field storing the provider short name.
     * @param string $configfield Field storing normalized JSON configuration.
     * @param string|null $legacyfield Optional legacy provider value field.
     */
    public function __construct(
        string $sourcefield = 'videosource',
        string $configfield = 'sourceconfig',
        ?string $legacyfield = 'videourl'
    ) {
        foreach (array_filter([$sourcefield, $configfield, $legacyfield]) as $field) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $field)) {
                throw new coding_exception('Invalid Video Bridge field name: ' . $field);
            }
        }

        $this->sourcefield = $sourcefield;
        $this->configfield = $configfield;
        $this->legacyfield = $legacyfield;
    }

    /**
     * Returns installed providers ordered by provider priority and localized name.
     *
     * @return plugin_base[] Providers indexed by short name.
     */
    public function get_plugins(): array {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $plugins = [];
        foreach (array_keys(core_component::get_plugin_list('videoprogresssource')) as $name) {
            $class = '\\videoprogresssource_' . $name . '\\plugin';
            if (!class_exists($class) || !is_subclass_of($class, plugin_base::class)) {
                debugging(get_string('invalidsourceplugin', 'local_video_bridge', $name), DEBUG_DEVELOPER);
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
     * Returns one provider by short name.
     *
     * @param string $name Provider short name.
     * @return plugin_base Provider instance.
     * @throws moodle_exception When the provider is unavailable.
     */
    public function get_plugin(string $name): plugin_base {
        $name = clean_param($name, PARAM_PLUGIN);
        $plugins = $this->get_plugins();
        if (!isset($plugins[$name])) {
            throw new moodle_exception('sourcepluginmissing', 'local_video_bridge', '', $name);
        }
        return $plugins[$name];
    }

    /**
     * Returns localized options, optionally requiring guaranteed capabilities.
     *
     * @param array $requiredcapabilities Capability names which must all be true.
     * @return array Provider names indexed by short name.
     */
    public function get_options(array $requiredcapabilities = []): array {
        $options = [];
        foreach ($this->get_plugins() as $name => $plugin) {
            $supported = true;
            foreach ($requiredcapabilities as $capability) {
                if (!$plugin->supports((string)$capability)) {
                    $supported = false;
                    break;
                }
            }
            if ($supported) {
                $options[$name] = $plugin->get_name();
            }
        }
        return $options;
    }

    /**
     * Returns the capabilities guaranteed by a provider.
     *
     * @param string $name Provider short name.
     * @return array Boolean capabilities.
     */
    public function get_capabilities(string $name): array {
        return $this->get_plugin($name)->get_capabilities();
    }

    /**
     * Returns the first available provider according to provider ordering.
     *
     * @return string Provider short name or an empty string.
     */
    public function get_default_source(): string {
        return (string)(array_key_first($this->get_plugins()) ?? '');
    }

    /**
     * Adds form fields from every installed provider.
     *
     * @param MoodleQuickForm $mform Form receiving provider fields.
     * @param string|null $sourcefield Optional selector field override.
     * @return void
     */
    public function add_form_elements(MoodleQuickForm $mform, ?string $sourcefield = null): void {
        $sourcefield ??= $this->sourcefield;
        foreach ($this->get_plugins() as $plugin) {
            $plugin->add_form_elements($mform, $sourcefield);
        }
    }

    /**
     * Validates fields owned by the currently selected provider.
     *
     * @param array $data Submitted form values.
     * @param array $files Submitted form files.
     * @return array Validation errors indexed by form field.
     */
    public function validation(array $data, array $files): array {
        $source = clean_param((string)($data[$this->sourcefield] ?? ''), PARAM_PLUGIN);
        try {
            return $this->get_plugin($source)->validation($data, $files);
        } catch (moodle_exception $exception) {
            return [$this->sourcefield => $exception->getMessage()];
        }
    }

    /**
     * Normalizes provider configuration into the consumer record.
     *
     * @param stdClass $data Consumer activity record.
     * @return array Normalized provider configuration.
     */
    public function normalise_record(stdClass $data): array {
        $source = clean_param((string)($data->{$this->sourcefield} ?? ''), PARAM_PLUGIN);
        $plugin = $this->get_plugin($source);
        $config = $plugin->build_config($data);

        $data->{$this->configfield} = json_encode(
            $config,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        if ($this->legacyfield !== null) {
            $data->{$this->legacyfield} = $plugin->get_legacy_value($config);
        }
        return $config;
    }

    /**
     * Restores provider-specific fields before displaying an edit form.
     *
     * @param array $defaultvalues Consumer form values.
     * @param context_module $context Activity module context.
     * @return void
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $source = clean_param((string)($defaultvalues[$this->sourcefield] ?? ''), PARAM_PLUGIN);
        if ($source === '') {
            return;
        }

        $hadsourcealias = array_key_exists('videosource', $defaultvalues);
        $hadconfigalias = array_key_exists('sourceconfig', $defaultvalues);

        $working = $defaultvalues;
        $working['videosource'] = $source;
        $working['sourceconfig'] = $defaultvalues[$this->configfield] ?? '';
        $working['videourl'] = $this->legacyfield === null
            ? ($defaultvalues['videourl'] ?? '')
            : ($defaultvalues[$this->legacyfield] ?? ($defaultvalues['videourl'] ?? ''));

        $this->get_plugin($source)->prepare_form_data($working, $context);
        $defaultvalues = array_replace($defaultvalues, $working);

        if ($this->sourcefield !== 'videosource' && !$hadsourcealias) {
            unset($defaultvalues['videosource']);
        }
        if ($this->configfield !== 'sourceconfig' && !$hadconfigalias) {
            unset($defaultvalues['sourceconfig']);
        }
    }

    /**
     * Persists files owned by the selected provider and cleans the previous provider when changed.
     *
     * @param stdClass $data Saved consumer activity record.
     * @param context_module $context Activity module context.
     * @param string|null $previoussource Previous provider short name.
     * @return void
     */
    public function save_files(stdClass $data, context_module $context, ?string $previoussource = null): void {
        $source = clean_param((string)($data->{$this->sourcefield} ?? ''), PARAM_PLUGIN);
        $plugins = $this->get_plugins();

        if ($previoussource && $previoussource !== $source && isset($plugins[$previoussource])) {
            $plugins[$previoussource]->delete_files($context);
        }
        $this->get_plugin($source)->save_files($data, $context);
    }

    /**
     * Prepares provider fields for one item in a multi-media consumer.
     *
     * @param array $defaultvalues Form values.
     * @param context_module $context Module context.
     * @param int $mediaid Stable media item id.
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
        $working['videosource'] = $source;
        $working['sourceconfig'] = $defaultvalues[$this->configfield] ?? '';
        $working['videourl'] = $this->legacyfield === null
            ? ($defaultvalues['videourl'] ?? '')
            : ($defaultvalues[$this->legacyfield] ?? ($defaultvalues['videourl'] ?? ''));

        $this->get_plugin($source)->prepare_media_form_data($working, $context, $mediaid);
        $defaultvalues = array_replace($defaultvalues, $working);
    }

    /**
     * Saves provider files for one item in a multi-media consumer.
     *
     * @param stdClass $data Form data.
     * @param context_module $context Module context.
     * @param int $mediaid Stable media item id.
     * @param string|null $previoussource Previous provider short name.
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
        $this->get_plugin($source)->save_media_files($data, $context, $mediaid);
    }

    /**
     * Deletes provider files for one media item.
     *
     * @param context_module $context Module context.
     * @param int $mediaid Stable media item id.
     * @return void
     */
    public function delete_files_for_media(context_module $context, int $mediaid): void {
        foreach ($this->get_plugins() as $plugin) {
            $plugin->delete_media_files($context, $mediaid);
        }
    }

    /**
     * Deletes source-owned files for an activity context.
     *
     * @param context_module $context Activity module context.
     * @return void
     */
    public function delete_files(context_module $context): void {
        foreach ($this->get_plugins() as $plugin) {
            $plugin->delete_files($context);
        }
    }

    /**
     * Returns providers for which consumers should not offer a custom poster.
     *
     * @return array Provider short names.
     */
    public function get_sources_without_poster(): array {
        return array_keys(array_filter(
            $this->get_plugins(),
            static fn(plugin_base $plugin): bool => !$plugin->supports_poster()
        ));
    }

    /**
     * Returns providers for which consumers should not offer uploaded captions.
     *
     * @return array Provider short names.
     */
    public function get_sources_without_uploaded_captions(): array {
        return array_keys(array_filter(
            $this->get_plugins(),
            static fn(plugin_base $plugin): bool => !$plugin->supports_uploaded_captions()
        ));
    }

    /**
     * Builds browser-safe provider data plus template and AMD identifiers.
     *
     * @param stdClass $activity Consumer activity record.
     * @param context_module $context Activity module context.
     * @param string $telemetrylevel Telemetry level requested by the consumer.
     * @return array Player configuration.
     */
    public function get_player_config(
        stdClass $activity,
        context_module $context,
        string $telemetrylevel = \local_video_bridge\analytics::LEVEL_BASIC
    ): array {
        $record = $this->provider_record($activity);
        $source = clean_param((string)$record->videosource, PARAM_PLUGIN);
        $plugin = $this->get_plugin($source);
        $config = $plugin->get_player_config($record, $context);

        if (array_key_exists('hlsjsurl', $config)) {
            $config['hlsjsurl'] = (new moodle_url('/local/video_bridge/vendor/hls/hls.min.js'))->out(false);
        }
        if (array_key_exists('vimeoplayerurl', $config)) {
            $config['vimeoplayerurl'] =
                (new moodle_url('/local/video_bridge/vendor/vimeo/player.min.js'))->out(false);
        }

        $playerconfig = $config + [
            'source' => $source,
            'capabilities' => $plugin->get_capabilities(),
            'adaptermodule' => $plugin->get_amd_module(),
            'sourcetemplate' => $plugin->get_player_template(),
        ];

        if ($plugin->supports('tracking')) {
            $playerconfig['progress'] = progress_manager::build_config(
                $context,
                $source,
                (string)($record->sourceconfig ?? ''),
                $telemetrylevel
            );
        }

        return $playerconfig;
    }

    /**
     * Builds browser-safe player data for one item in a multi-media consumer.
     *
     * @param media_config $media Media configuration.
     * @param context_module $context Module context.
     * @param consumer|null $consumer Explicit progress consumer identity.
     * @param string $telemetrylevel Telemetry level requested by the consumer.
     * @param array $requiredcapabilities Capabilities required by the consumer.
     * @return array Player configuration.
     */
    public function get_player_config_for_media(
        media_config $media,
        context_module $context,
        ?consumer $consumer = null,
        string $telemetrylevel = \local_video_bridge\analytics::LEVEL_BASIC,
        array $requiredcapabilities = []
    ): array {
        $source = $media->get_source();
        $plugin = $this->get_plugin($source);

        foreach ($requiredcapabilities as $capability) {
            if (!$plugin->supports((string)$capability)) {
                throw new coding_exception(
                    'Video source ' . $source . ' does not provide required capability: ' . $capability
                );
            }
        }

        $config = $plugin->get_player_config_for_media($media, $context);
        if (array_key_exists('hlsjsurl', $config)) {
            $config['hlsjsurl'] = (new moodle_url('/local/video_bridge/vendor/hls/hls.min.js'))->out(false);
        }
        if (array_key_exists('vimeoplayerurl', $config)) {
            $config['vimeoplayerurl'] =
                (new moodle_url('/local/video_bridge/vendor/vimeo/player.min.js'))->out(false);
        }

        if ($media->get_posterurl() !== '' && $plugin->supports_poster()) {
            $config['poster'] = $media->get_posterurl();
        }
        if ($media->get_captionsource() !== '' && $plugin->supports_uploaded_captions()) {
            $config['captions'] = (new caption_manager())->get_tracks_for_media($media, $context);
        }

        $playerconfig = $config + [
            'source' => $source,
            'mediaid' => $media->get_mediaid(),
            'mediahash' => $media->get_mediahash(),
            'capabilities' => $plugin->get_capabilities(),
            'adaptermodule' => $plugin->get_amd_module(),
            'sourcetemplate' => $plugin->get_player_template(),
        ];

        if ($plugin->supports('tracking')) {
            $playerconfig['progress'] = progress_manager::build_config_for_media(
                $context,
                $consumer ?? consumer::from_context($context),
                $media,
                $telemetrylevel
            );
        }

        return $playerconfig;
    }

    /**
     * Returns a provider-owned media file suitable for transcription.
     *
     * @param stdClass $activity Consumer activity record.
     * @param context_module $context Activity module context.
     * @return stored_file|null Source media file when available.
     */
    public function get_transcription_file(stdClass $activity, context_module $context): ?stored_file {
        $record = $this->provider_record($activity);
        return $this->get_plugin((string)$record->videosource)->get_transcription_file($context);
    }

    /**
     * Returns a provider-owned file for one media item.
     *
     * @param media_config $media Media configuration.
     * @param context_module $context Module context.
     * @return stored_file|null
     */
    public function get_transcription_file_for_media(
        media_config $media,
        context_module $context
    ): ?stored_file {
        return $this->get_plugin($media->get_source())->get_transcription_file_for_media(
            $context,
            $media->get_mediaid()
        );
    }

    /**
     * Maps a consumer record to the standard aliases expected by source providers.
     *
     * @param stdClass $record Consumer activity record.
     * @return stdClass Provider-compatible record copy.
     */
    private function provider_record(stdClass $record): stdClass {
        $copy = clone $record;
        $copy->videosource = $record->{$this->sourcefield} ?? '';
        $copy->sourceconfig = $record->{$this->configfield} ?? '';
        $copy->videourl = $this->legacyfield === null ? '' : ($record->{$this->legacyfield} ?? '');
        return $copy;
    }
}
