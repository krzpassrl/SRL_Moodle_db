<?php
// This file is part of Moodle - http://moodle.org/
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
 * This file adds the settings pages to the navigation menu
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/setask/adminlib.php');

$ADMIN->add('modsettings', new admin_category('modsetaskfolder', new lang_string('pluginname', 'mod_setask'), $module->is_enabled() === false));

$settings = new admin_settingpage($section, get_string('settings', 'mod_setask'), 'moodle/site:config', $module->is_enabled() === false);

if ($ADMIN->fulltree) {
    $menu = array();
    foreach (core_component::get_plugin_list('setaskfeedback') as $type => $notused) {
        $visible = !get_config('setaskfeedback_' . $type, 'disabled');
        if ($visible) {
            $menu['setaskfeedback_' . $type] = new lang_string('pluginname', 'setaskfeedback_' . $type);
        }
    }

    // The default here is feedback_comments (if it exists).
    $name = new lang_string('feedbackplugin', 'mod_setask');
    $description = new lang_string('feedbackpluginforgradebook', 'mod_setask');
    $settings->add(new admin_setting_configselect('setask/feedback_plugin_for_gradebook',
                                                  $name,
                                                  $description,
                                                  'setaskfeedback_comments',
                                                  $menu));

    $name = new lang_string('showrecentsubmissions', 'mod_setask');
    $description = new lang_string('configshowrecentsubmissions', 'mod_setask');
    $settings->add(new admin_setting_configcheckbox('setask/showrecentsubmissions',
                                                    $name,
                                                    $description,
                                                    0));

    $name = new lang_string('sendsubmissionreceipts', 'mod_setask');
    $description = new lang_string('sendsubmissionreceipts_help', 'mod_setask');
    $settings->add(new admin_setting_configcheckbox('setask/submissionreceipts',
                                                    $name,
                                                    $description,
                                                    1));

    $name = new lang_string('submissionstatement', 'mod_setask');
    $description = new lang_string('submissionstatement_help', 'mod_setask');
    $default = get_string('submissionstatementdefault', 'mod_setask');
    $settings->add(new admin_setting_configtextarea('setask/submissionstatement',
                                                    $name,
                                                    $description,
                                                    $default));

    $name = new lang_string('defaultsettings', 'mod_setask');
    $description = new lang_string('defaultsettings_help', 'mod_setask');
    $settings->add(new admin_setting_heading('defaultsettings', $name, $description));

    $name = new lang_string('alwaysshowdescription', 'mod_setask');
    $description = new lang_string('alwaysshowdescription_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/alwaysshowdescription',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('allowsubmissionsfromdate', 'mod_setask');
    $description = new lang_string('allowsubmissionsfromdate_help', 'mod_setask');
    $setting = new admin_setting_configduration('setask/allowsubmissionsfromdate',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('duedate', 'mod_setask');
    $description = new lang_string('duedate_help', 'mod_setask');
    $setting = new admin_setting_configduration('setask/duedate',
                                                    $name,
                                                    $description,
                                                    604800);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('cutoffdate', 'mod_setask');
    $description = new lang_string('cutoffdate_help', 'mod_setask');
    $setting = new admin_setting_configduration('setask/cutoffdate',
                                                    $name,
                                                    $description,
                                                    1209600);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('submissiondrafts', 'mod_setask');
    $description = new lang_string('submissiondrafts_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/submissiondrafts',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('requiresubmissionstatement', 'mod_setask');
    $description = new lang_string('requiresubmissionstatement_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/requiresubmissionstatement',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Constants from "locallib.php".
    $options = array(
        'none' => get_string('attemptreopenmethod_none', 'mod_setask'),
        'manual' => get_string('attemptreopenmethod_manual', 'mod_setask'),
        'untilpass' => get_string('attemptreopenmethod_untilpass', 'mod_setask')
    );
    $name = new lang_string('attemptreopenmethod', 'mod_setask');
    $description = new lang_string('attemptreopenmethod_help', 'mod_setask');
    $setting = new admin_setting_configselect('setask/attemptreopenmethod',
                                                    $name,
                                                    $description,
                                                    'none',
                                                    $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Constants from "locallib.php".
    $options = array(-1 => get_string('unlimitedattempts', 'mod_setask'));
    $options += array_combine(range(1, 30), range(1, 30));
    $name = new lang_string('maxattempts', 'mod_setask');
    $description = new lang_string('maxattempts_help', 'mod_setask');
    $setting = new admin_setting_configselect('setask/maxattempts',
                                                    $name,
                                                    $description,
                                                    -1,
                                                    $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('teamsubmission', 'mod_setask');
    $description = new lang_string('teamsubmission_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/teamsubmission',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('preventsubmissionnotingroup', 'mod_setask');
    $description = new lang_string('preventsubmissionnotingroup_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/preventsubmissionnotingroup',
        $name,
        $description,
        0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('requireallteammemberssubmit', 'mod_setask');
    $description = new lang_string('requireallteammemberssubmit_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/requireallteammemberssubmit',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('teamsubmissiongroupingid', 'mod_setask');
    $description = new lang_string('teamsubmissiongroupingid_help', 'mod_setask');
    $setting = new admin_setting_configempty('setask/teamsubmissiongroupingid',
                                                    $name,
                                                    $description);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendnotifications', 'mod_setask');
    $description = new lang_string('sendnotifications_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/sendnotifications',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendlatenotifications', 'mod_setask');
    $description = new lang_string('sendlatenotifications_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/sendlatenotifications',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendstudentnotificationsdefault', 'mod_setask');
    $description = new lang_string('sendstudentnotificationsdefault_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/sendstudentnotifications',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('blindmarking', 'mod_setask');
    $description = new lang_string('blindmarking_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/blindmarking',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('markingworkflow', 'mod_setask');
    $description = new lang_string('markingworkflow_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/markingworkflow',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('markingallocation', 'mod_setask');
    $description = new lang_string('markingallocation_help', 'mod_setask');
    $setting = new admin_setting_configcheckbox('setask/markingallocation',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);
}

$ADMIN->add('modsetaskfolder', $settings);
// Tell core we already added the settings structure.
$settings = null;

$ADMIN->add('modsetaskfolder', new admin_category('setasksubmissionplugins',
    new lang_string('submissionplugins', 'setask'), !$module->is_enabled()));
$ADMIN->add('setasksubmissionplugins', new setask_admin_page_manage_setask_plugins('setasksubmission'));
$ADMIN->add('modsetaskfolder', new admin_category('setaskfeedbackplugins',
    new lang_string('feedbackplugins', 'setask'), !$module->is_enabled()));
$ADMIN->add('setaskfeedbackplugins', new setask_admin_page_manage_setask_plugins('setaskfeedback'));

foreach (core_plugin_manager::instance()->get_plugins_of_type('setasksubmission') as $plugin) {
    /** @var \mod_setask\plugininfo\setasksubmission $plugin */
    $plugin->load_settings($ADMIN, 'setasksubmissionplugins', $hassiteconfig);
}

foreach (core_plugin_manager::instance()->get_plugins_of_type('setaskfeedback') as $plugin) {
    /** @var \mod_setask\plugininfo\setaskfeedback $plugin */
    $plugin->load_settings($ADMIN, 'setaskfeedbackplugins', $hassiteconfig);
}
