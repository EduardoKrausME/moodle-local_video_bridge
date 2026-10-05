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
 * Administration page for Video Bridge source subplugins.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$type = required_param('type', PARAM_ALPHA);
$types = [
    'videoprogresssource' => [
        'title' => 'subplugintype_videoprogresssource_plural',
        'description' => 'sources_desc',
    ],
];

if (!isset($types[$type])) {
    throw new invalid_parameter_exception(get_string('invalidsubplugintype', 'local_video_bridge'));
}

require_admin();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/video_bridge/admin_plugins.php', ['type' => $type]));
$PAGE->set_pagelayout('admin');

$pluginmanager = core_plugin_manager::instance();
$plugins = [];

foreach ($pluginmanager->get_plugins_of_type($type) as $plugininfo) {
    $settingsurl = $plugininfo->get_settings_url();
    $canuninstall = $pluginmanager->can_uninstall_plugin($plugininfo->component);

    $plugins[] = [
        'name' => $plugininfo->displayname,
        'component' => $plugininfo->component,
        'version' => $plugininfo->versiondisk ?? get_string('unknown', 'local_video_bridge'),
        'release' => $plugininfo->release ?? get_string('unknown', 'local_video_bridge'),
        'status' => get_string(
            $plugininfo->is_installed_and_upgraded() ? 'pluginstatusready' : 'pluginstatusupgrade',
            'local_video_bridge'
        ),
        'settingsurl' => $settingsurl ? $settingsurl->out(false) : null,
        'uninstallurl' => $canuninstall ? $plugininfo->get_default_uninstall_url('manage')->out(false) : null,
        'canuninstall' => $canuninstall,
    ];
}

$PAGE->set_title(get_string($types[$type]['title'], 'local_video_bridge'));
$PAGE->set_heading(get_string('pluginadministration', 'local_video_bridge'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_video_bridge/admin_plugins', [
    'title' => get_string($types[$type]['title'], 'local_video_bridge'),
    'description' => get_string($types[$type]['description'], 'local_video_bridge'),
    'plugins' => $plugins,
    'hasplugins' => !empty($plugins),
    'componentlabel' => get_string('plugincomponent', 'local_video_bridge'),
    'versionlabel' => get_string('pluginversion', 'local_video_bridge'),
    'releaselabel' => get_string('pluginrelease', 'local_video_bridge'),
    'statuslabel' => get_string('pluginstatus', 'local_video_bridge'),
    'settingslabel' => get_string('settings'),
    'uninstalllabel' => get_string('uninstallplugin', 'core_admin'),
    'inuselabel' => get_string('subplugininuse', 'local_video_bridge'),
    'emptytitle' => get_string('nosubplugins', 'local_video_bridge'),
    'emptydescription' => get_string('nosubpluginshelp', 'local_video_bridge'),
]);
echo $OUTPUT->footer();
