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
 * Privacy provider tests.
 *
 * @package    mod_scheduler
 * @copyright  2018 Renaat Debleu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_privacy\local\metadata\collection;
use core_privacy\local\request\deletion_criteria;
use mod_scheduler\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/scheduler/locallib.php');

/**
 * Privacy provider tests class.
 *
 * @package    mod_scheduler
 * @copyright  2018 Renaat Debleu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_scheduler_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {
    /** @var stdClass The student object. */
    protected $student;

    /** @var stdClass The teacher object. */
    protected $teacher;

    /** @var stdClass The scheduler object. */
    protected $scheduler;

    /** @var stdClass The course object. */
    protected $course;

    /**
     * {@inheritdoc}
     */
    protected function setUp() {
        $this->resetAfterTest();

        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname'=>'editingteacher'], '*', MUST_EXIST);
        role_assign($teacherrole->id, $teacher->id, context_course::instance($course->id));
        
        $plugingenerator = $generator->get_plugin_generator('mod_scheduler');
        $options = [];
        $options['slottimes'] = [];
        $options['slotstudents'] = [];
        for ($c = 0; $c < 4; $c++) {
            $options['slottimes'][$c] = time() + ($c + 1) * DAYSECS;
            $options['slotstudents'][$c] = [$generator->create_user()->id];
        }
        $options['slottimes'][4] = time() + 10 * DAYSECS;
        $options['slottimes'][5] = time() + 11 * DAYSECS;
        $options['slotstudents'][5] = [$student->id, $generator->create_user()->id];
        $scheduler = $generator->create_module('scheduler', ['course' => $course->id], $options);

        $this->student = $student;
        $this->teacher = $teacher;
        $this->scheduler = $scheduler;
        $this->course = $course;
    }

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata() {
        $collection = new collection('mod_scheduler');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(2, $itemcollection);

        $table = reset($itemcollection);
        $this->assertEquals('scheduler_slots', $table->get_name());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('schedulerid', $privacyfields);
        $this->assertArrayHasKey('starttime', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);

        $this->assertEquals('privacy:metadata:scheduler_slots', $table->get_summary());
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {
        $cm = get_coursemodule_from_instance('scheduler', $this->scheduler->id);
        $contextlist = provider::get_contexts_for_userid($this->student->id);
        $this->assertCount(1, $contextlist);
        $contextforuser = $contextlist->current();
        $cmcontext = context_module::instance($cm->id);
        $this->assertEquals($cmcontext->id, $contextforuser->id);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_for_context() {
        $cm = get_coursemodule_from_instance('scheduler', $this->scheduler->id);
        $cmcontext = context_module::instance($cm->id);
        $this->export_context_data_for_user($this->student->id, $cmcontext, 'mod_scheduler');
        $writer = \core_privacy\local\request\writer::with_context($cmcontext);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $cm = get_coursemodule_from_instance('scheduler', $this->scheduler->id);
        $cmcontext = context_module::instance($cm->id);
        \mod_scheduler\privacy\provider::delete_data_for_all_users_in_context($cmcontext);
        $this->assertTrue($DB->count_records('scheduler_slots') === 0);
        $this->assertTrue($DB->count_records('scheduler_appointment') === 0);
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user_() {
        global $DB;
        $cm = get_coursemodule_from_instance('scheduler', $this->scheduler->id);
        $cmcontext = context_module::instance($cm->id);
        $list = new core_privacy\tests\request\approved_contextlist($this->student, 'mod_scheduler', [$cmcontext->id]);
        $this->assertNotEmpty($list);
        $this->assertTrue($DB->count_records('scheduler_appointment') === 6);
        \mod_scheduler\privacy\provider::delete_data_for_user($list);
        $this->assertTrue($DB->count_records('scheduler_slots') === 6);
        $this->assertTrue($DB->count_records('scheduler_appointment') === 5);
    }
}
