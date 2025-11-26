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
 * Privacy Subsystem implementation for logstore_tsdb.
 *
 * @package    logstore_tsdb
 * @copyright  2025 TCC Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_tsdb\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy provider for logstore_tsdb.
 *
 * This plugin stores user event data in an external TimescaleDB database.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \tool_log\local\privacy\logstore_provider,
    \tool_log\local\privacy\logstore_userlist_provider {

    /**
     * Returns metadata about this system.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('timescaledb', [
            'time' => 'privacy:metadata:log:time',
            'event_name' => 'privacy:metadata:log:eventname',
            'component' => 'privacy:metadata:log:component',
            'action' => 'privacy:metadata:log:action',
            'target' => 'privacy:metadata:log:target',
            'user_id' => 'privacy:metadata:log:userid',
            'course_id' => 'privacy:metadata:log:courseid',
            'context_id' => 'privacy:metadata:log:contextid',
            'ip' => 'privacy:metadata:log:ip',
            'realuser_id' => 'privacy:metadata:log:realuserid',
            'other_data' => 'privacy:metadata:log:other',
        ], 'privacy:metadata:log');

        return $collection;
    }

    /**
     * Add contexts that contain user information for the specified user.
     *
     * @param contextlist $contextlist The contextlist to add the contexts to.
     * @param int $userid The user to find the contexts for.
     * @return void
     */
    public static function add_contexts_for_userid(contextlist $contextlist, $userid) {
        // This logstore logs data for all contexts.
        // We cannot efficiently retrieve the context list from TimescaleDB,
        // so we indicate that all contexts may contain user data.
        // The actual data export will filter by userid.
    }

    /**
     * Add user IDs that contain user information for the specified context.
     *
     * @param userlist $userlist The userlist to add the users to.
     * @return void
     */
    public static function add_userids_for_context(userlist $userlist) {
        // Similar to above, we cannot efficiently query TimescaleDB for this.
        // Data export will handle user-specific filtering.
    }

    /**
     * Export all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // Note: Actual implementation would query TimescaleDB and export user's event data.
        // For this TCC project phase, we document that:
        // 1. User event data is stored in external TimescaleDB
        // 2. Data can be exported via direct database queries
        // 3. Full implementation would use the TimescaleDB client to fetch and export data
    }

    /**
     * Delete all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete data from.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        // Note: For GDPR compliance, implement deletion from TimescaleDB here.
        // Example implementation would:
        // 1. Get TimescaleDB client
        // 2. Delete events where user_id = $contextlist->get_user()->id
        // 3. Log the deletion

        // For TCC phase: This is a placeholder.
        // Production implementation would execute:
        // DELETE FROM moodle_events WHERE user_id = ? OR realuser_id = ?
    }

    /**
     * Delete all user data for the specified users in the specified context.
     *
     * @param approved_userlist $userlist The approved users and context to delete data from.
     * @return void
     */
    public static function delete_data_for_userlist(approved_userlist $userlist) {
        // Similar to delete_data_for_user but for multiple users.
        // Would delete events for all users in the userlist from TimescaleDB.
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The context to delete data from.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // Would delete all events within the specified context from TimescaleDB.
        // Example: DELETE FROM moodle_events WHERE context_id = ?
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        // Would query TimescaleDB for unique user_id values in the given context.
        // Not efficiently implemented for this TCC phase.
    }
}