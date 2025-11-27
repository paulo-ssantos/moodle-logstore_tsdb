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

if ($hassiteconfig) {

    /**
     * Custom setting class to validate TimescaleDB connection before saving.
     * We extend the Password field because that is usually the final credential needed.
     */
    class admin_setting_tsdb_connection_validate extends admin_setting_configpasswordunmask {

        public function write_setting($data) {
            global $CFG;

            // 1. Perform standard validation first (check if string is valid).
            $result = parent::write_setting($data);
            if ($result !== '') {
                return $result; // If parent failed, return that error.
            }

            // 2. Retrieve the OTHER settings from the form submission (POST data).
            // We cannot rely on get_config() here because the user might be changing
            // the host/user/port in this very request, and those haven't been saved yet.
            // Moodle admin fields are prefixed with 's_' followed by plugin_name_setting_name.
            
            $host = optional_param('s_logstore_tsdb_host', 'localhost', PARAM_TEXT);
            $port = optional_param('s_logstore_tsdb_port', 5433, PARAM_INT);
            $dbname = optional_param('s_logstore_tsdb_database', 'moodle_logs_tsdb', PARAM_TEXT);
            $user = optional_param('s_logstore_tsdb_username', 'moodleuser', PARAM_TEXT);
            
            // The password is the $data passed to this function.
            $password = $data;

            // Build the config array for the client.
            $test_config = [
                'host' => $host,
                'port' => $port,
                'database' => $dbname,
                'username' => $user,
                'password' => $password,
                'dbtable' => optional_param('s_logstore_tsdb_dbtable', 'moodle_events', PARAM_TEXT),
            ];

            // 3. Attempt Connection.
            try {
                // Ensure the client class is loaded.
                $clientpath = $CFG->dirroot . '/admin/tool/log/store/tsdb/classes/client/timescaledb_client.php';
                
                // Check if file exists to avoid fatal error during development
                if (!file_exists($clientpath)) {
                     // Fallback if class file is missing, but allow save to proceed (or block if strict).
                     return "Error: Client class not found at $clientpath";
                }

                require_once($clientpath);
                
                // Assuming your client constructor takes an array or individual params.
                // Based on your previous code: new client($config)
                $client = new \logstore_tsdb\client\timescaledb_client($test_config);

                if ($client->is_connected()) {
                    // CONNECTION SUCCESSFUL: Return '' (empty string) means success in Moodle settings.
                    return ''; 
                } else {
                    // CONNECTION FAILED: Return error message.
                    return get_string('connectiontest_failed', 'logstore_tsdb');
                }

            } catch (Exception $e) {
                // EXCEPTION CAUGHT: Return the error message to display in the interface.
                return get_string('connectiontest_exception', 'logstore_tsdb', $e->getMessage());
            }
        }
    }

    $settings = new admin_settingpage('logstore_tsdb', get_string('pluginname', 'logstore_tsdb'));

    // Link to external test page (Keep this if you still want a manual test button)
    $testurl = new moodle_url('/admin/tool/log/store/tsdb/test_settings.php');
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

    // Host
    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/host',
        get_string('host', 'logstore_tsdb'),
        get_string('host_help', 'logstore_tsdb'),
        '',
        PARAM_HOST
    ));

    // Port
    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/port',
        get_string('port', 'logstore_tsdb'),
        get_string('port_help', 'logstore_tsdb'),
        '5433',
        PARAM_INT
    ));

    // Database
    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/database',
        get_string('database', 'logstore_tsdb'),
        get_string('database_help', 'logstore_tsdb'),
        'moodle_logs_tsdb',
        PARAM_TEXT
    ));

    // Username
    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/username',
        get_string('username', 'logstore_tsdb'),
        get_string('username_help', 'logstore_tsdb'),
        'moodleuser',
        PARAM_TEXT
    ));

    // --- CHANGED: Use the custom validation class for the Password field ---
    // This triggers the validation logic defined in the class above when saving.
    $settings->add(new admin_setting_tsdb_connection_validate(
        'logstore_tsdb/password',
        get_string('password', 'logstore_tsdb'),
        get_string('password_help', 'logstore_tsdb'),
        ''
    ));

    // Table
    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/dbtable',
        get_string('databasetable', 'logstore_tsdb'),
        get_string('databasetable_help', 'logstore_tsdb'),
        'moodle_events',
        PARAM_TEXT
    ));
}