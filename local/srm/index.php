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
require_once($CFG->dirroot . '/user/lib.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$filtersapplied = optional_param_array('unified-filters', [], PARAM_NOTAGS);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_capability('local/srm:report', $context);

$urlparams = array();
$urlparams['courseid'] = $courseid;
$baseurl = new moodle_url('/local/srm/index.php', $urlparams);

$PAGE->set_course($course);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_srm'));
$PAGE->set_heading(get_string('pluginname', 'local_srm'));

$groupid = false;
$lastaccess = false;
$roleid = false;
$enrolid = false;
$status = false;
$searchkeywords = null;

foreach ($filtersapplied as $filter) {
    $filtervalue = explode(':', $filter, 2);
    $value = null;
    if (count($filtervalue) == 2) {
        $key = clean_param($filtervalue[0], PARAM_INT);
        $value = clean_param($filtervalue[1], PARAM_INT);
    } else {
        // Search string.
        $key = USER_FILTER_STRING;
        $value = clean_param($filtervalue[0], PARAM_TEXT);
    }

    switch ($key) {
        case USER_FILTER_ENROLMENT:
            $enrolid = $value;
            break;
        case USER_FILTER_GROUP:
            $groupid = $value;
            $hasgroupfilter = true;
            break;
        case USER_FILTER_LAST_ACCESS:
            $lastaccess = $value;
            break;
        case USER_FILTER_ROLE:
            $roleid = $value;
            break;
        case USER_FILTER_STATUS:
            // We only accept active/suspended statuses.
            if ($value == ENROL_USER_ACTIVE || $value == ENROL_USER_SUSPENDED) {
                $status = $value;
            }
            break;
        default:
            // Search string.
            $searchkeywords[] = $value;
            break;
    }
}
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('core_user');
echo $renderer->unified_filter($course, $context, $filtersapplied, $baseurl);

$table = new \local_srm\index_table($courseid, $groupid, $lastaccess, $roleid, $enrolid, $status, $searchkeywords, false, false);
$table->out(25, 1);

echo $OUTPUT->footer();