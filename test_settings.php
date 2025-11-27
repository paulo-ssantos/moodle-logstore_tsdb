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
 * Database connection settings test for the logstore_tsdb plugin.
 *
 * @package   logstore_tsdb
 * @copyright Antônio Neto <antoniocarolino.neto@ucsal.edu.br>
 *            Henrique Viana <henrique.viana@ucsal.edu.br>
 *            Luís Carvalho <luisguilherme.carvalho@ucsal.edu.br>
 *            Paulo Santos <paulovitor.santos@ucsal.edu.br>
 *            Yuri Gomes <yurijesus.gomes@ucsal.edu.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security checks.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up the page.
$pageurl = new moodle_url('/admin/tool/log/store/tsdb/test_settings.php');
$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('testsettings', 'logstore_tsdb'));
$PAGE->set_heading(get_string('testsettings', 'logstore_tsdb'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testsettings', 'logstore_tsdb'));

// Gather configuration.
$config = [
    'tsdb_type' => get_config('logstore_tsdb', 'tsdb_type'),
    'host' => get_config('logstore_tsdb', 'host'),
    'port' => get_config('logstore_tsdb', 'port'),
    'database' => get_config('logstore_tsdb', 'database'),
    'username' => get_config('logstore_tsdb', 'username'),
    'password' => get_config('logstore_tsdb', 'password'),
    'dbtable' => get_config('logstore_tsdb', 'dbtable'),
    'writemode' => get_config('logstore_tsdb', 'writemode'),
    'buffersize' => get_config('logstore_tsdb', 'buffersize'),
    'flushinterval' => get_config('logstore_tsdb', 'flushinterval'),
];

// Display current configuration (without password).
echo $OUTPUT->box_start('generalbox');
echo html_writer::tag('h3', get_string('currentsettings', 'logstore_tsdb'));
echo html_writer::start_tag('dl', ['class' => 'row']);

$configdisplay = [
    'tsdb_type' => $config['tsdb_type'] ?: get_string('notconfigured', 'logstore_tsdb'),
    'host' => $config['host'] ?: get_string('notconfigured', 'logstore_tsdb'),
    'port' => $config['port'] ?: get_string('notconfigured', 'logstore_tsdb'),
    'database' => $config['database'] ?: get_string('notconfigured', 'logstore_tsdb'),
    'username' => $config['username'] ?: get_string('notconfigured', 'logstore_tsdb'),
    'password' => !empty($config['password']) ? '********' : get_string('notconfigured', 'logstore_tsdb'),
    'dbtable' => $config['dbtable'] ?: get_string('notconfigured', 'logstore_tsdb'),
    'writemode' => $config['writemode'] ?: get_string('notconfigured', 'logstore_tsdb'),
    'buffersize' => $config['buffersize'] ?: get_string('notconfigured', 'logstore_tsdb'),
    'flushinterval' => $config['flushinterval'] ?: get_string('notconfigured', 'logstore_tsdb'),
];

foreach ($configdisplay as $key => $value) {
    echo html_writer::tag('dt', get_string($key, 'logstore_tsdb'), ['class' => 'col-sm-3']);
    echo html_writer::tag('dd', $value, ['class' => 'col-sm-9']);
}

echo html_writer::end_tag('dl');
echo $OUTPUT->box_end();

// Check if required configuration is present.
$missingconfig = [];
$required = ['host', 'port', 'database', 'username', 'password', 'dbtable'];
foreach ($required as $key) {
    if (empty($config[$key])) {
        $missingconfig[] = $key;
    }
}

if (!empty($missingconfig)) {
    echo $OUTPUT->notification(
        get_string('error_missingconfig', 'logstore_tsdb', implode(', ', $missingconfig)),
        'notifyproblem'
    );
    echo $OUTPUT->footer();
    exit;
}

// Test connection.
echo $OUTPUT->box_start('generalbox');
echo html_writer::tag('h3', get_string('connectiontest', 'logstore_tsdb'));

try {
    // Load the client class.
    require_once($CFG->dirroot . '/admin/tool/log/store/tsdb/classes/client/timescaledb_client.php');
    
    // Create client instance.
    $client = new \logstore_tsdb\client\timescaledb_client($config);
    
    // Test if connected.
    if ($client->is_connected()) {
        echo $OUTPUT->notification(
            get_string('connectiontest_success', 'logstore_tsdb'),
            'notifysuccess'
        );
        
        // Get PostgreSQL version.
        $pgversion = $client->get_version();
        if ($pgversion) {
            echo html_writer::tag('p', get_string('postgresversion', 'logstore_tsdb', $pgversion));
        }
        
        // Check TimescaleDB extension.
        $tsdbversion = $client->get_timescaledb_version();
        if ($tsdbversion) {
            echo $OUTPUT->notification(
                get_string('timescaledb_found', 'logstore_tsdb', $tsdbversion),
                'notifysuccess'
            );
        } else {
            echo $OUTPUT->notification(
                get_string('timescaledb_notfound', 'logstore_tsdb'),
                'notifywarning'
            );
        }
        
        // Test table access.
        echo html_writer::tag('h4', get_string('tabletest', 'logstore_tsdb'));
        
        try {
            $count = $client->count_events();
            echo $OUTPUT->notification(
                get_string('tabletest_success', 'logstore_tsdb', [
                    'table' => $config['dbtable'],
                    'count' => $count
                ]),
                'notifysuccess'
            );
        } catch (Exception $e) {
            echo $OUTPUT->notification(
                get_string('tabletest_failed', 'logstore_tsdb', [
                    'table' => $config['dbtable'],
                    'error' => $e->getMessage()
                ]),
                'notifywarning'
            );
        }
        
        // Close connection.
        $client->close();
        
    } else {
        echo $OUTPUT->notification(
            get_string('connectiontest_failed', 'logstore_tsdb'),
            'notifyproblem'
        );
    }
    
} catch (\moodle_exception $e) {
    echo $OUTPUT->notification(
        get_string('connectiontest_exception', 'logstore_tsdb', $e->getMessage()),
        'notifyproblem'
    );
} catch (Exception $e) {
    echo $OUTPUT->notification(
        get_string('connectiontest_exception', 'logstore_tsdb', $e->getMessage()),
        'notifyproblem'
    );
}

echo $OUTPUT->box_end();

// Add close page button.
echo html_writer::tag('div', 
    html_writer::tag('button', 
        get_string('close', 'core'),
        [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'onclick' => 'window.close();'
        ]
    ),
    ['class' => 'mt-2']
);

echo $OUTPUT->footer();