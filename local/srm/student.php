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
 * @package   local_srm
 * @copyright 2009 Payam Yasaie <payam@yasaie.ir>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @var core_renderer $OUTPUT
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$context = context_course::instance($courseid, MUST_EXIST);

require_capability('local/srm:report', $context);

$urlparams = array();
$urlparams['courseid'] = $courseid;
$urlparams['userid'] = $userid;
$baseurl = new moodle_url('/local/srm/student.php', $urlparams);
$parenturl = new moodle_url('/local/srm/index.php', $urlparams);

if ($delete) {
    $params = $urlparams;
    $params['id'] = $delete;
    $DB->delete_records('local_srm', $params);
}

$PAGE->set_course($course);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_srm'));
$PAGE->set_heading(get_string('pluginname', 'local_srm'));
$PAGE->navbar->add(get_string('pluginname', 'local_srm'), $parenturl);
$PAGE->navbar->add(get_string('students'), $baseurl);

echo $OUTPUT->header();

echo '<div class="userprofile">';

$headerinfo = array('heading' => fullname($user), 'user' => $user);
echo $OUTPUT->context_header($headerinfo, 2);

$renderer = $PAGE->get_renderer('core_user', 'myprofile');
$tree = new local_srm\tree($user, $course);

$tree->contact();
$tree->report();
$tree->notes();
$tree->access();
$tree->detail_report();
$tree->event_form();
$tree->completion_progress();
$tree->attendance();
$tree->gamification();
$tree->call_form();

$tree->sort_categories();

echo $renderer->render($tree);

echo '</div>';  // Userprofile class.

$srm_table = new \local_srm\srm_table($userid, $courseid);
$srm_table->out(15, 0);

echo $OUTPUT->footer();
