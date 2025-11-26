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
 * Scheduled task to flush buffered events to TimescaleDB.
 *
 * @package    logstore_tsdb
 * @copyright  2025 TCC Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_tsdb\task;

/**
 * Task to periodically flush buffered events.
 *
 * This ensures that events buffered in asynchronous mode are written
 * to TimescaleDB even if the buffer size threshold is not reached.
 */
class buffer_flush extends \core\task\scheduled_task {

    /**
     * Get the task name for the admin interface.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('taskbufferflush', 'logstore_tsdb');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute() {
        $manager = get_log_manager();

        // Access the protected 'stores' property using reflection.
        // We need writers, not readers. Readers are for querying logs.
        try {
            $reflection = new \ReflectionClass($manager);
            $property = $reflection->getProperty('stores');
            $property->setAccessible(true);
            $stores = $property->getValue($manager);
        } catch (\ReflectionException $e) {
            mtrace('Error accessing log stores via reflection: ' . $e->getMessage());
            return;
        }

        if (empty($stores)) {
            mtrace('No log stores found.');
            return;
        }

        foreach ($stores as $pluginname => $store) {
            // Check if this is our TSDB store.
            if ($pluginname === 'logstore_tsdb' && $store instanceof \logstore_tsdb\log\store) {
                mtrace('Flushing buffer for logstore_tsdb...');

                try {
                    // The store's dispose() method flushes the buffer.
                    $store->dispose();
                    mtrace('Buffer flushed successfully.');
                } catch (\Exception $e) {
                    mtrace('Error flushing buffer: ' . $e->getMessage());
                    error_log('[LOGSTORE_TSDB] Scheduled task flush error: ' . $e->getMessage());
                    // Continue with other stores even if this one fails.
                }
            }
        }
    }
}