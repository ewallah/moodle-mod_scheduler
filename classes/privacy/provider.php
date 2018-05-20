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
 * Privacy main class.
 *
 * @package mod_scheduler
 * @author  Renaat Debleu <rdebleu@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scheduler\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\helper;
use \core_privacy\local\request\deletion_criteria;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\transform;

/**
 * Privacy main class.
 *
 * @package mod_scheduler
 * @author  Renaat Debleu <rdebleu@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {

    /**
     * Returns information about how scheduler stores its data.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $arr = ['teacherid' => 'privacy:metadata:scheduler_slot:teacherid',
                'schedulerid' => 'privacy:metadata:scheduler_slot:schedulerid',
                'starttime' => 'privacy:metadata:scheduler_slot:starttime',
                'duration' => 'privacy:metadata:scheduler_slot:duration',
                'appointmentlocation' => 'privacy:metadata:scheduler_slot:appointmentlocation',
                'reuse' => 'privacy:metadata:scheduler_slot:reuse',
                'timemodified' => 'privacy:metadata:scheduler_slot:timemodified',
                'notes' => 'privacy:metadata:scheduler_slot:notes',
                'exclusivity' => 'privacy:metadata:scheduler_slot:exclusivity',
                'emaildate' => 'privacy:metadata:scheduler_slot:emaildate',
                'hideuntil' => 'privacy:metadata:scheduler_slot:hideuntil'];
        $collection->add_database_table('scheduler_slots', $arr, 'privacy:metadata:scheduler_slots');
        $arr = ['studentid' => 'privacy:metadata:scheduler_appointment:studentid',
                'slotid' => 'privacy:metadata:scheduler_appointment:slotid',
                'attended' => 'privacy:metadata:scheduler_appointment:attended',
                'grade' => 'privacy:metadata:scheduler_appointment:grade',
                'appointmentnote' => 'privacy:metadata:scheduler_appointment:appointmentnote',
                'teachernote' => 'privacy:metadata:scheduler_appointment:teachernote',
                'studentnote' => 'privacy:metadata:scheduler_appointment:studentnote',
                'timecreated' => 'privacy:metadata:scheduler_appointment:timecreated',
                'timemodified' => 'privacy:metadata:scheduler_appointment:timemodified'];
        $collection->add_database_table('scheduler_appointment', $arr, 'privacy:metadata:scheduler_appointment');
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int           $userid       The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();
        $sql = "SELECT DISTINCT ctx.id FROM {scheduler} l
                  JOIN {modules} m ON m.name = :name
                  JOIN {course_modules} cm ON cm.instance = l.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulelevel
             LEFT JOIN {scheduler_slots} la ON la.schedulerid = l.id
             LEFT JOIN {scheduler_appointment} sa ON sa.slotid = la.id
                 WHERE la.teacherid = :userid1 OR sa.studentid = :userid2";
        $params = ['name' => 'scheduler', 'modulelevel' => CONTEXT_MODULE, 'userid1' => $userid, 'userid2' => $userid];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $contexts = $contextlist->get_contexts();
        foreach ($contexts as $context) {
            $contextdata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $contextdata);
            $data = [];
            $sql = "SELECT s.* FROM {scheduler_slots} s
                JOIN {scheduler} l ON l.id = s.schedulerid
                JOIN {modules} m ON m.name = :name
                JOIN {course_modules} cm ON cm.instance = l.id AND cm.module = m.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulelevel
                WHERE ctx.id = :id AND s.teacherid = :userid";
            $params = ['name' => 'scheduler',  'modulelevel' => CONTEXT_MODULE, 'id' => $context->id, 'userid' => $user->id];
            $recordset = $DB->get_recordset_sql($sql, $params);
            foreach ($recordset as $record) {
                $data[] = [
                    'teacherid' => $record->teacherid,
                    'schedulerid' => $record->schedulerid,
                    'starttime' => transform::datetime($record->starttime),
                    'duration' => $record->duration,
                    'appointmentlocation' => $record->appointmentlocation,
                    'reuse' => transform::yesno($record->reuse),
                    'timemodified' => transform::datetime($record->timemodified),
                    'notes' => format_text($record->notes, $record->notesformat),
                    'exclusivity' => $record->exclusivity,
                    'emaildate' => transform::datetime($record->emaildate),
                    'hideuntil' => transform::datetime($record->hideuntil)];
            }
            $sql = "SELECT s.* FROM {scheduler_appointment} s
                JOIN {scheduler_slots} sl ON sl.id = s.slotid
                JOIN {scheduler} l ON l.id = sl.schedulerid
                JOIN {modules} m ON m.name = :name
                JOIN {course_modules} cm ON cm.instance = l.id AND cm.module = m.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulelevel
                WHERE ctx.id = :id AND s.studentid = :userid";
            $recordset = $DB->get_recordset_sql($sql, $params);
            foreach ($recordset as $record) {
                $data[] = [
                    'studentid' => $record->studentid,
                    'slotid' => $record->slotid,
                    'attended' => transform::yesno($record->attended),
                    'grade' => $record->grade,
                    'appointmentnote' => format_text($record->appointmentnote, $record->appointmentnoteformat),
                    'teachernote' => format_text($record->teachernote, $record->teachernoteformat),
                    'studentnote' => format_text($record->studentnote, $record->studentnoteformat),
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified)];
            }
            $recordset->close();
            if (!empty($data)) {
                writer::with_context($context)->export_related_data([], 'slots', (object) ['slots' => array_values($data)]);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT l.id FROM {scheduler} l
                JOIN {modules} m ON m.name = :name
                JOIN {course_modules} cm ON cm.instance = l.id AND cm.module = m.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulelevel
                WHERE ctx.id = :id";
        $params = ['name' => 'scheduler',  'modulelevel' => CONTEXT_MODULE, 'id' => $context->id];
        if ($recs = $DB->get_records_sql($sql, $params)) {
            foreach ($recs as $rec) {
                if ($slots = $DB->get_records('scheduler_slots', ['schedulerid' => $rec->id])) {
                    foreach ($slots as $slot) {
                        $DB->delete_records('scheduler_appointment', ['slotid' => $slot->id]);
                    }
                    $DB->delete_records('scheduler_slots', ['schedulerid' => $rec->id]);
                }
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $contexts = $contextlist->get_contexts();
        foreach ($contexts as $context) {
            $sql = "SELECT l.id FROM {scheduler} l
                    JOIN {modules} m ON m.name = :name
                    JOIN {course_modules} cm ON cm.instance = l.id AND cm.module = m.id
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulelevel
                    WHERE ctx.id = :id";
            $params = ['name' => 'scheduler',  'modulelevel' => CONTEXT_MODULE, 'id' => $context->id];
            if ($recs = $DB->get_records_sql($sql, $params)) {
                foreach ($recs as $rec) {
                    if ($slots = $DB->get_records('scheduler_slots', ['schedulerid' => $rec->id])) {
                        foreach ($slots as $slot) {
                            $DB->delete_records('scheduler_appointment', ['slotid' => $slot->id, 'studentid' => $userid]);
                        }
                    }
                }
            }
        }
    }
}
