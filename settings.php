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

    // Debug: Log that we're entering settings configuration
    debugging('logstore_tsdb: Loading settings.php', DEBUG_DEVELOPER);

    // Check if parent class exists before defining custom class
    if (!class_exists('admin_setting_configpasswordunmask')) {
        debugging('logstore_tsdb: ERROR - admin_setting_configpasswordunmask not found!', DEBUG_DEVELOPER);
        error_log('logstore_tsdb: ERROR - admin_setting_configpasswordunmask class not available');
    }

    /**
     * Custom setting class to validate TimescaleDB connection before saving.
     * 
     * This validates the connection when the Save button is clicked on the settings page.
     * Validation happens BEFORE saving to prevent invalid credentials from being persisted.
     * 
     * IMPORTANT: We check if class exists first to prevent redefinition errors
     * when settings.php is loaded multiple times by Moodle.
     */
    if (!class_exists('admin_setting_tsdb_connection_validate')) {
        class admin_setting_tsdb_connection_validate extends admin_setting_configpasswordunmask {

        /** @var bool Flag to ensure validation only runs once per form submission */
        private static $validated = false;

        /** @var string|null Stores the HTML error UI to display via output_html() */
        private static $connection_error_html = null;

        /**
         * Validate connection and save setting.
         *
         * @param string $data The password value to save
         * @return string Empty string for success, error message string for failure
         */
        public function write_setting($data) {
            global $CFG;

            debugging('logstore_tsdb: write_setting called', DEBUG_DEVELOPER);

            // Check if user clicked "Setup Later" to bypass validation
            $setup_later = optional_param('logstore_tsdb_setup_later', 0, PARAM_INT);
            if ($setup_later) {
                debugging('logstore_tsdb: Setup Later clicked, bypassing validation', DEBUG_DEVELOPER);
                self::$validated = true; // Mark as validated to prevent re-checking
                self::$connection_error_html = null; // Clear any previous error
                return parent::write_setting($data);
            }

            // Only validate once per form submission (not on every field save)
            if (self::$validated) {
                debugging('logstore_tsdb: Already validated, skipping', DEBUG_DEVELOPER);
                return parent::write_setting($data);
            }
            self::$validated = true;

            // Get current form values from POST
            $host = optional_param('s_logstore_tsdb_host', '', PARAM_TEXT);
            $port = optional_param('s_logstore_tsdb_port', 5433, PARAM_INT);
            $dbname = optional_param('s_logstore_tsdb_database', '', PARAM_TEXT);
            $user = optional_param('s_logstore_tsdb_username', '', PARAM_TEXT);
            $password = $data;

            debugging('logstore_tsdb: Collected form params - host: ' . $host . ', db: ' . $dbname, DEBUG_DEVELOPER);

            // Skip validation if critical fields are empty (incomplete configuration)
            if (empty($host) || empty($dbname) || empty($user) || empty($password)) {
                debugging('logstore_tsdb: Skipping validation - empty fields', DEBUG_DEVELOPER);
                return parent::write_setting($data);
            }

            // Build test configuration
            $test_config = [
                'host' => $host,
                'port' => $port,
                'database' => $dbname,
                'username' => $user,
                'password' => $password,
                'dbtable' => optional_param('s_logstore_tsdb_dbtable', 'moodle_events', PARAM_TEXT),
                'writemode' => optional_param('s_logstore_tsdb_writemode', 'async', PARAM_ALPHA)
            ];

            // Test connection BEFORE saving
            try {
                debugging('logstore_tsdb: Starting connection test', DEBUG_DEVELOPER);
                
                $clientpath = $CFG->dirroot . '/admin/tool/log/store/tsdb/classes/client/timescaledb_client.php';
                
                if (!file_exists($clientpath)) {
                    $error = 'TimescaleDB client class not found at ' . $clientpath;
                    debugging('logstore_tsdb: ' . $error, DEBUG_DEVELOPER);
                    error_log('logstore_tsdb: ' . $error);
                    return parent::write_setting($data);
                }

                require_once($clientpath);
                debugging('logstore_tsdb: Client class loaded', DEBUG_DEVELOPER);
                
                // Create client - constructor will attempt connection
                $client = new \logstore_tsdb\client\timescaledb_client($test_config);
                debugging('logstore_tsdb: Client instantiated', DEBUG_DEVELOPER);

                // Verify connection is working
                if (!$client->is_connected()) {
                    debugging('logstore_tsdb: Connection test failed', DEBUG_DEVELOPER);
                    $error_msg = get_string('connectiontest_failed', 'logstore_tsdb');
                    $this->store_connection_error($test_config, $error_msg);
                    return $error_msg; // Return plain text - HTML will be shown via output_html()
                }

                debugging('logstore_tsdb: Connection test successful', DEBUG_DEVELOPER);

                // Clean up test connection
                unset($client);

                // Connection successful - save the setting
                self::$connection_error_html = null; // Clear any previous error
                return parent::write_setting($data);

            } catch (\moodle_exception $e) {
                $error = 'Moodle exception: ' . $e->getMessage();
                debugging('logstore_tsdb: ' . $error, DEBUG_DEVELOPER);
                error_log('logstore_tsdb: ' . $error);
                $error_msg = get_string('connectiontest_exception', 'logstore_tsdb', $e->getMessage());
                $this->store_connection_error($test_config, $error_msg);
                return $error_msg;
            } catch (\Exception $e) {
                $error = 'PHP exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
                debugging('logstore_tsdb: ' . $error, DEBUG_DEVELOPER);
                error_log('logstore_tsdb: ' . $error);
                $error_msg = get_string('connectiontest_exception', 'logstore_tsdb', $e->getMessage());
                $this->store_connection_error($test_config, $error_msg);
                return $error_msg;
            } catch (\Throwable $e) {
                $error = 'Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
                debugging('logstore_tsdb: ' . $error, DEBUG_DEVELOPER);
                error_log('logstore_tsdb: ' . $error);
                $error_msg = 'Fatal error during connection test: ' . $e->getMessage();
                $this->store_connection_error($test_config, $error_msg);
                return $error_msg;
            }
        }

        /**
         * Store connection error HTML for display via output_html().
         *
         * @param array $config The connection configuration
         * @param string $error_message The error message to display
         */
        private function store_connection_error($config, $error_message) {
            // Build HTML for connection details
            $html = html_writer::start_div('alert alert-danger', ['style' => 'margin: 10px 0;']);
            $html .= html_writer::tag('h4', get_string('connectionfailed_title', 'logstore_tsdb'));
            $html .= html_writer::tag('p', $error_message);
            
            // Display connection configuration
            $html .= html_writer::tag('p', html_writer::tag('strong', get_string('connectionfailed_details', 'logstore_tsdb')));
            $html .= html_writer::start_tag('ul');
            $html .= html_writer::tag('li', get_string('host', 'logstore_tsdb') . ': ' . s($config['host']));
            $html .= html_writer::tag('li', get_string('port', 'logstore_tsdb') . ': ' . s($config['port']));
            $html .= html_writer::tag('li', get_string('database', 'logstore_tsdb') . ': ' . s($config['database']));
            $html .= html_writer::tag('li', get_string('username', 'logstore_tsdb') . ': ' . s($config['username']));
            $html .= html_writer::tag('li', get_string('password', 'logstore_tsdb') . ': ' . str_repeat('*', strlen($config['password'])));
            $html .= html_writer::end_tag('ul');

            // Add action buttons using JavaScript to trigger form submission
            $html .= html_writer::start_div('', ['style' => 'margin-top: 15px;']);
            
            // Try Again button - submits form again
            $try_again_btn = html_writer::tag('button', get_string('connectionfailed_tryagain', 'logstore_tsdb'), [
                'type' => 'button',
                'class' => 'btn btn-primary',
                'onclick' => "if (this.form) { this.form.submit(); } return false;",
                'style' => 'margin-right: 10px;'
            ]);
            $html .= $try_again_btn;

            // Setup Later button - adds hidden field to bypass validation
            $setup_later_btn = html_writer::tag('button', get_string('connectionfailed_setuplater', 'logstore_tsdb'), [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'onclick' => "if (this.form) { " .
                             "var input = document.createElement('input'); " .
                             "input.type = 'hidden'; " .
                             "input.name = 'logstore_tsdb_setup_later'; " .
                             "input.value = '1'; " .
                             "this.form.appendChild(input); " .
                             "this.form.submit(); " .
                             "} return false;"
            ]);
            $html .= $setup_later_btn;
            
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();

            // Store in static property for output_html() to use
            self::$connection_error_html = $html;
        }

        /**
         * Override output_html to display connection error UI when validation fails.
         * This is where we can output HTML that won't be escaped.
         *
         * @param string $data The current value of the setting
         * @param string $query Search query to highlight
         * @return string HTML output
         */
        public function output_html($data, $query = '') {
            // Get the parent's HTML output first
            $output = parent::output_html($data, $query);
            
            // If there's a stored connection error, prepend it to the output
            if (self::$connection_error_html !== null) {
                $output = self::$connection_error_html . $output;
                // Clear the error after displaying it
                self::$connection_error_html = null;
            }
            
            return $output;
        }
        } // End of class_exists check
    }

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
        'localhost',
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
        'timescale_moodle',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_tsdb/username',
        get_string('username', 'logstore_tsdb'),
        get_string('username_help', 'logstore_tsdb'),
        'moodleuser',
        PARAM_TEXT
    ));

    // Use custom validation class for password field with fallback
    // This will test the connection when Save button is clicked
    if (class_exists('admin_setting_tsdb_connection_validate')) {
        try {
            debugging('logstore_tsdb: Creating password field with validation', DEBUG_DEVELOPER);
            $passwordsetting = new admin_setting_tsdb_connection_validate(
                'logstore_tsdb/password',
                get_string('password', 'logstore_tsdb'),
                get_string('password_help', 'logstore_tsdb'),
                ''
            );
            $settings->add($passwordsetting);
            debugging('logstore_tsdb: Password field with validation added successfully', DEBUG_DEVELOPER);
        } catch (\Exception $e) {
            // Fallback to standard password field if custom class fails
            $error = 'Failed to create custom password field: ' . $e->getMessage();
            debugging('logstore_tsdb: ' . $error, DEBUG_DEVELOPER);
            error_log('logstore_tsdb: ' . $error);
            
            $settings->add(new admin_setting_configpasswordunmask(
                'logstore_tsdb/password',
                get_string('password', 'logstore_tsdb'),
                get_string('password_help', 'logstore_tsdb') . ' (Validation disabled due to error)',
                ''
            ));
        }
    } else {
        // Class not defined - use standard password field
        debugging('logstore_tsdb: Custom validation class not available, using standard password field', DEBUG_DEVELOPER);
        $settings->add(new admin_setting_configpasswordunmask(
            'logstore_tsdb/password',
            get_string('password', 'logstore_tsdb'),
            get_string('password_help', 'logstore_tsdb'),
            ''
        ));
    }

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
