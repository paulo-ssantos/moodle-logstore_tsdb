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
 * Settings for the logstore_tsdb plugin.
 *
 * @package   logstore_tsdb
 * @copyright Antônio Neto <antoniocarolino.neto@ucsal.edu.br>
 *            Henrique Viana <henrique.viana@ucsal.edu.br>
 *            Luís Carvalho <luisguilherme.carvalho@ucsal.edu.br>
 *            Paulo Santos <paulovitor.santos@ucsal.edu.br>
 *            Yuri Gomes <yurijesus.gomes@ucsal.edu.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * USAGE:
 * @Param 1 = Config context: 'logstore_tsdb'
 * @Param 2 = Config name: 'dbhost'
 * get_config('logstore_tsdb', 'dbhost')
 */

if ($hassiteconfig) {

    $testurl = new moodle_url('/admin/tool/log/store/tsdb/test_settings.php');
    $test = new admin_externalpage(
        'logstoretsdb_testsettings',
        get_string('testsettings', 'logstore_tsdb'),
        $testurl,
        'moodle/site:config',
        true
    );
    $ADMIN->add('logging', $test);

    // Add test connection link to settings page.
    $link = html_writer::link($testurl, get_string('testsettings', 'logstore_tsdb'), array('target' => '_blank'));
    $settings->add(new admin_setting_heading(
        'logstore_tsdb/testconnection',
        '',
        $link
    ));

    // TimescaleDB Type setting.
    $settings->add(new admin_setting_configselect(
        'logstore_tsdb/tsdb_type',
        get_string('tsdb_type', 'logstore_tsdb'),
        get_string('tsdb_type_help', 'logstore_tsdb'),
        'timescaledb',
        array(
            'timescaledb' => 'TimescaleDB',
        )
    ));

    // Database connection settings.
    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/host',
        get_string('host', 'logstore_tsdb'),
        get_string('host_help', 'logstore_tsdb'),
        '',
        PARAM_HOST
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/port',
        get_string('port', 'logstore_tsdb'),
        get_string('port_help', 'logstore_tsdb'),
        '5433',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/database',
        get_string('database', 'logstore_tsdb'),
        get_string('database_help', 'logstore_tsdb'),
        'moodle_logs_tsdb',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/username',
        get_string('username', 'logstore_tsdb'),
        get_string('username_help', 'logstore_tsdb'),
        'moodleuser',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'logstore_tsdb/password',
        get_string('password', 'logstore_tsdb'),
        get_string('password_help', 'logstore_tsdb'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/dbtable',
        get_string('databasetable', 'logstore_tsdb'),
        get_string('databasetable_help', 'logstore_tsdb'),
        'moodle_events'
    ));

    // Write mode settings.
    $settings->add(new admin_setting_configselect(
        'logstore_tsdb/writemode',
        get_string('writemode', 'logstore_tsdb'),
        get_string('writemode_help', 'logstore_tsdb'),
        'async',
        array(
            'sync' => get_string('writemode_sync', 'logstore_tsdb'),
            'async' => get_string('writemode_async', 'logstore_tsdb')
        )
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/buffersize',
        get_string('buffersize', 'logstore_tsdb'),
        get_string('buffersize_help', 'logstore_tsdb'),
        '1000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/flushinterval',
        get_string('flushinterval', 'logstore_tsdb'),
        get_string('flushinterval_help', 'logstore_tsdb'),
        '60',
        PARAM_INT
    ));
}
