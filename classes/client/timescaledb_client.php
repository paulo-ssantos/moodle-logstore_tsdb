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
 * TimescaleDB client for logstore_tsdb.
 *
 * @package   logstore_tsdb
 * @copyright Antônio Neto <antoniocarolino.neto@ucsal.edu.br>
 *            Henrique Viana <henrique.viana@ucsal.edu.br>
 *            Luís Carvalho <luisguilherme.carvalho@ucsal.edu.br>
 *            Paulo Santos <paulovitor.santos@ucsal.edu.br>
 *            Yuri Gomes <yurijesus.gomes@ucsal.edu.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_tsdb\client;

defined('MOODLE_INTERNAL') || die();

/**
 * Client for connecting to and interacting with TimescaleDB.
 *
 * @package    logstore_tsdb
 * @copyright  Antônio Neto <antoniocarolino.neto@ucsal.edu.br>
 *             Henrique Viana <henrique.viana@ucsal.edu.br>
 *             Luís Carvalho <luisguilherme.carvalho@ucsal.edu.br>
 *             Paulo Santos <paulovitor.santos@ucsal.edu.br>
 *             Yuri Gomes <yurijesus.gomes@ucsal.edu.br>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timescaledb_client {

    /** @var resource PostgreSQL connection resource */
    protected $connection;

    /** @var array Configuration */
    protected $config;

    /** @var string Table name */
    protected $tablename;

    /** @var int Number of connection retries */
    protected $maxretries = 3;

    /** @var int Retry delay in microseconds */
    protected $retrydelay = 1000000; // 1 second

    /** @var bool Is connected */
    protected $connected = false;

    /** @var array Allowed ORDER BY columns for security */
    protected $allowedorderby = ['time', 'eventname', 'component', 'userid', 'courseid'];

    /**
     * Constructor.
     *
     * @param array $config Configuration array
     * @throws \moodle_exception If required config is missing
     */
    public function __construct(array $config) {
        // Validate required configuration.
        $required = ['host', 'port', 'database', 'username', 'password', 'dbtable'];
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new \moodle_exception(
                    'error_missingconfig',
                    'logstore_tsdb',
                    '',
                    $key
                );
            }
        }

        // Store config (business logic params like writemode, buffersize are optional and not used here).
        $this->config = $config;
        $this->tablename = $config['dbtable'];
        $this->connect();
    }

    /**
     * Establish connection to TimescaleDB.
     *
     * @throws \moodle_exception If connection fails
     */
    protected function connect() {
        $connstring = sprintf(
            "host=%s port=%s dbname=%s user=%s password=%s connect_timeout=10",
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['username'],
            $this->config['password']
        );

        $retries = 0;
        $lasterror = null;

        while ($retries < $this->maxretries) {
            // Suppress warnings to handle errors gracefully.
            $this->connection = @pg_connect($connstring);

            if ($this->connection !== false) {
                $this->connected = true;
                debugging('TimescaleDB connection established', DEBUG_DEVELOPER);
                return;
            }

            $lasterror = pg_last_error();
            $retries++;

            if ($retries < $this->maxretries) {
                debugging("TimescaleDB connection failed, retry $retries/$this->maxretries", DEBUG_DEVELOPER);
                usleep($this->retrydelay);
            }
        }

        // All retries failed.
        $this->connected = false;
        throw new \moodle_exception(
            'error_connection',
            'logstore_tsdb',
            '',
            (object)['host' => $this->config['host'], 'port' => $this->config['port']],
            $lasterror
        );
    }

    /**
     * Write single datapoint to TimescaleDB.
     *
     * @param array $datapoint Formatted datapoint
     * @return bool Success
     */
    public function write_point(array $datapoint) {
        return $this->write_points([$datapoint]);
    }

    /**
     * Write multiple datapoints to TimescaleDB (batch insert).
     *
     * Pure data access method - writes directly to database without business logic.
     * Buffering and write mode decisions are handled by the calling layer (store.php).
     *
     * @param array $datapoints Array of datapoints
     * @return bool Success
     */
    public function write_points(array $datapoints) {
        if (!$this->connected) {
            debugging('TimescaleDB not connected, skipping write', DEBUG_DEVELOPER);
            return false;
        }

        if (empty($datapoints)) {
            return true;
        }

        // Direct write to database - no buffering or mode logic at this layer.
        return $this->write_to_database($datapoints);
    }

    /**
     * Write datapoints directly to database.
     *
     * @param array $datapoints Array of datapoints
     * @return bool Success
     */
    protected function write_to_database(array $datapoints) {
        if (empty($datapoints)) {
            return true;
        }

        try {
            // Begin transaction for batch insert.
            pg_query($this->connection, 'BEGIN');

            // Prepare INSERT statement.
            $sql = "
                INSERT INTO {$this->tablename} (
                    time, eventname, component, action, target, crud,
                    edulevel, anonymous, courseid, contextid, contextlevel,
                    contextinstanceid, userid, relateduserid, realuserid,
                    objectid, objecttable, ip, origin, other
                ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20)
            ";

            $successcount = 0;
            foreach ($datapoints as $datapoint) {
                $params = [
                    $this->format_timestamp($datapoint['timestamp']),
                    $datapoint['tags']['eventname'] ?? null,
                    $datapoint['tags']['component'] ?? null,
                    $datapoint['tags']['action'] ?? null,
                    $datapoint['tags']['target'] ?? null,
                    $datapoint['tags']['crud'] ?? null,
                    $datapoint['tags']['edulevel'] ?? 0,
                    $datapoint['fields']['anonymous'] ?? 0,
                    $datapoint['tags']['courseid'] ?? 0,
                    $datapoint['fields']['contextid'] ?? 0,
                    $datapoint['fields']['contextlevel'] ?? 0,
                    $datapoint['fields']['contextinstanceid'] ?? 0,
                    $datapoint['fields']['userid'] ?? 0,
                    $datapoint['fields']['relateduserid'] ?? null,
                    $datapoint['fields']['realuserid'] ?? null,
                    $datapoint['fields']['objectid'] ?? null,
                    $datapoint['fields']['objecttable'] ?? null,
                    $datapoint['fields']['ip'] ?? null,
                    $datapoint['fields']['origin'] ?? 'web',
                    $this->format_other($datapoint['fields']['other'] ?? null),
                ];

                $result = pg_query_params($this->connection, $sql, $params);

                if ($result !== false) {
                    $successcount++;
                } else {
                    debugging('Error inserting event: ' . pg_last_error($this->connection), DEBUG_DEVELOPER);
                }
            }

            // Commit transaction.
            pg_query($this->connection, 'COMMIT');

            debugging("TimescaleDB: Wrote $successcount/" . count($datapoints) . " events", DEBUG_DEVELOPER);

            return $successcount > 0;

        } catch (\Exception $e) {
            // Rollback on error.
            pg_query($this->connection, 'ROLLBACK');
            debugging('Error in batch insert: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }



    /**
     * Execute raw SQL query.
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return resource|false Query result
     */
    public function query($sql, array $params = []) {
        if (!$this->connected) {
            return false;
        }

        if (empty($params)) {
            return pg_query($this->connection, $sql);
        } else {
            return pg_query_params($this->connection, $sql, $params);
        }
    }

    /**
     * Get count of events.
     *
     * @param string $where Optional WHERE clause
     * @param array $params Query parameters
     * @return int Count
     */
    public function count_events($where = '', array $params = []) {
        $sql = "SELECT COUNT(*) FROM {$this->tablename}";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }

        $result = $this->query($sql, $params);
        if ($result === false) {
            return 0;
        }

        $row = pg_fetch_row($result);
        return (int)$row[0];
    }

    /**
     * Get events with optional filtering.
     *
     * @param string $where WHERE clause
     * @param array $params Query parameters
     * @param string $orderby ORDER BY clause (must be a valid column name)
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Array of events
     */
    public function get_events($where = '', array $params = [], $orderby = 'time DESC', $limit = 100, $offset = 0) {
        // Validate and sanitize orderby to prevent SQL injection.
        $orderby = $this->validate_orderby($orderby);

        $sql = "SELECT * FROM {$this->tablename}";

        if (!empty($where)) {
            $sql .= " WHERE $where";
        }

        $sql .= " ORDER BY $orderby";
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $result = $this->query($sql, $params);
        if ($result === false) {
            return [];
        }

        $events = [];
        while ($row = pg_fetch_assoc($result)) {
            $events[] = $row;
        }

        return $events;
    }

    /**
     * Get event statistics.
     *
     * @param string $starttime Start timestamp
     * @param string $endtime End timestamp
     * @return array|false Statistics
     */
    public function get_statistics($starttime = null, $endtime = null) {
        $starttime = $starttime ?? date('Y-m-d H:i:s', strtotime('-24 hours'));
        $endtime = $endtime ?? date('Y-m-d H:i:s');

        $sql = "SELECT * FROM get_event_statistics($1, $2)";
        $result = $this->query($sql, [$starttime, $endtime]);

        if ($result === false) {
            return false;
        }

        return pg_fetch_assoc($result);
    }

    /**
     * Validate ORDER BY clause to prevent SQL injection.
     *
     * @param string $orderby ORDER BY clause
     * @return string Validated ORDER BY clause
     */
    protected function validate_orderby($orderby) {
        // Default safe value.
        $default = 'time DESC';

        if (empty($orderby)) {
            return $default;
        }

        // Parse column and direction.
        $parts = explode(' ', trim($orderby));
        $column = $parts[0];
        $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';

        // Validate column is in allowed list.
        if (!in_array($column, $this->allowedorderby)) {
            debugging("Invalid ORDER BY column: $column. Using default.", DEBUG_DEVELOPER);
            return $default;
        }

        // Validate direction.
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        return "$column $direction";
    }

    /**
     * Format timestamp for PostgreSQL.
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted timestamp
     */
    protected function format_timestamp($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Format 'other' field as JSONB.
     *
     * @param mixed $other Data to encode
     * @return string|null JSON string or null
     */
    protected function format_other($other) {
        if (empty($other)) {
            return null;
        }

        if (is_array($other) || is_object($other)) {
            return json_encode($other);
        }

        if (is_string($other)) {
            // Already JSON.
            return $other;
        }

        return null;
    }

    /**
     * Test connection.
     *
     * @return bool Is connected
     */
    public function is_connected() {
        if (!$this->connected || !$this->connection) {
            return false;
        }

        $result = pg_query($this->connection, 'SELECT 1');
        return $result !== false;
    }

    /**
     * Get database version.
     *
     * @return string|false Version string
     */
    public function get_version() {
        $result = $this->query('SELECT version()');
        if ($result === false) {
            return false;
        }

        $row = pg_fetch_row($result);
        return $row[0];
    }

    /**
     * Get TimescaleDB version.
     *
     * @return string|false Version string
     */
    public function get_timescaledb_version() {
        $result = $this->query("SELECT extversion FROM pg_extension WHERE extname = 'timescaledb'");
        if ($result === false) {
            return false;
        }

        $row = pg_fetch_row($result);
        return $row ? $row[0] : false;
    }

    /**
     * Close connection.
     */
    public function close() {
        if ($this->connected && $this->connection) {
            pg_close($this->connection);
            $this->connected = false;
            debugging('TimescaleDB connection closed', DEBUG_DEVELOPER);
        }
    }

    /**
     * Destructor - ensure connection is closed.
     */
    public function __destruct() {
        $this->close();
    }
}