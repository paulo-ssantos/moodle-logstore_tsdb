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
 * Languages configuration for the logstore_tsdb plugin.
 *
 * @package   logstore_tsdb
 * @copyright Antônio Neto <antoniocarolino.neto@ucsal.edu.br>
 *            Henrique Viana <henrique.viana@ucsal.edu.br>
 *            Luís Carvalho <luisguilherme.carvalho@ucsal.edu.br>
 *            Paulo Santos <paulovitor.santos@ucsal.edu.br>
 *            Yuri Gomes <yurijesus.gomes@ucsal.edu.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'TimescaleDB log store';

// Settings.
$string['tsdb_type'] = 'Database Type';
$string['tsdb_type_help'] = 'Select the type of time-series database to use (Only TimescaleDB supported for now)';

$string['host'] = 'Database Host';
$string['host_help'] = 'The hostname or IP address of the database server';

$string['port'] = 'Database Port';
$string['port_help'] = 'The port number for the database connection (default: 5433 for TimescaleDB)';

$string['database'] = 'Database Name';
$string['database_help'] = 'The name of the database to store logs';

$string['username'] = 'Database Username';
$string['username_help'] = 'The username for database authentication';

$string['password'] = 'Database Password';
$string['password_help'] = 'The password for database authentication';

$string['databasetable'] = 'Database Table';
$string['databasetable_help'] = 'The name of the table where logs will be stored';

$string['writemode'] = 'Write Mode';
$string['writemode_help'] = 'Choose between synchronous or asynchronous write mode';
$string['writemode_sync'] = 'Synchronous (immediate write)';
$string['writemode_async'] = 'Asynchronous (buffered write)';

$string['buffersize'] = 'Buffer Size';
$string['buffersize_help'] = 'Number of log entries to buffer before writing (only applies to async mode)';

$string['flushinterval'] = 'Flush Interval';
$string['flushinterval_help'] = 'Time interval in seconds to flush buffered logs (only applies to async mode)';

// Test settings page.
$string['testsettings'] = 'Test TimescaleDB Connection';
$string['currentsettings'] = 'Current Configuration';
$string['notconfigured'] = 'Not configured';
$string['connectiontest'] = 'Connection Test';
$string['connectiontest_success'] = 'Successfully connected to TimescaleDB!';
$string['connectiontest_failed'] = 'Failed to establish connection to TimescaleDB';
$string['connectiontest_exception'] = 'Connection error: {$a}';
$string['connectionfailed_title'] = 'TimescaleDB Connection Failed';
$string['connectionfailed_details'] = 'Connection Details:';
$string['connectionfailed_tryagain'] = 'Try Again';
$string['connectionfailed_setuplater'] = 'Setup Later';
$string['postgresversion'] = 'PostgreSQL Version: {$a}';
$string['timescaledb_found'] = 'TimescaleDB extension found (version {$a})';
$string['timescaledb_notfound'] = 'TimescaleDB extension not installed (plugin will work with standard PostgreSQL)';
$string['tabletest'] = 'Table Access Test';
$string['tabletest_success'] = 'Table "{$a->table}" is accessible and contains {$a->count} events';
$string['tabletest_failed'] = 'Cannot access table "{$a->table}": {$a->error}';
$string['tabletest_notexists'] = 'Table "{$a}" does not exist in TimescaleDB. Use "Initialize TimescaleDB" to create it.';
$string['buffertest'] = 'Buffer Status';
$string['currentbuffersize'] = 'Current buffer size: {$a} events';
$string['asyncmode_enabled'] = 'Async mode enabled (buffer size: {$a->buffersize}, flush interval: {$a->flushinterval}s)';
$string['syncmode_enabled'] = 'Sync mode enabled (immediate writes)';
$string['backtosettings'] = 'Back to Settings';
$string['dbtable'] = 'Database Table Name';
$string['close'] = 'Close';

// Initialization controls.
$string['initialize_tsdb'] = 'Initialize TimescaleDB';
$string['initialize_tsdb_button'] = 'Initialize TimescaleDB (create table and indexes)';
$string['initialize_tsdb_success'] = 'TimescaleDB initialized. Table "{$a->table}" accessible with {$a->count} events.';
$string['initialize_tsdb_failed'] = 'Failed to initialize TimescaleDB: {$a}';

// Error messages.
$string['error_connection'] = 'Failed to connect to TimescaleDB server at {$a->host}:{$a->port}';
$string['error_missingconfig'] = 'Missing required configuration: {$a}';

$string['serverurl'] = 'TSDB Server URL';