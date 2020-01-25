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

namespace local_srm;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/blocks/completion_progress/lib.php');

/**
 * Class    srm_table
 *
 * @author  Payam Yasaie <payam@yasaie.ir>
 * @since   2019-11-20
 *
 * @package local_srm
 */
class index_table extends \core_user\participants_table
{
    /** @var array $activities */
    protected $activities;

    /** @var array $calls */
    protected $calls;

    public function __construct($courseid, $currentgroup, $accesssince, $roleid, $enrolid, $status, $search, $bulkoperations, $selectall)
    {
        global $PAGE;

        parent::__construct($courseid, $currentgroup, $accesssince, $roleid, $enrolid, $status, $search, $bulkoperations, $selectall);

        $columns = [
            'fullname',
            'description',
            'timecreated',
            'groups',
            'lastassign',
            'zone',
            'lastaccess',
        ];

        $headers = [
            get_string('fullname'),
            'توضیحات آخرین تماس',
            'زمان آخرین تماس',
            get_string('groups'),
            get_string('lastassign', 'local_srm'),
            get_string('status'),
            get_string('lastcourseaccess'),
        ];

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->no_sorting('description');
        $this->no_sorting('groups');
        $this->no_sorting('zone');
        $this->no_sorting('lastassign');

        $this->column_style('fullname', 'width', '20%');
        $this->column_style('description', 'width', '30%');
        $this->column_style('groups', 'width', '15%');
        $this->column_style('timecreated', 'width', '20%');
        $this->column_style('lastassign', 'width', '20%');

        $this->activities = block_completion_progress_get_activities($courseid);

        $this->define_baseurl($PAGE->url);
    }


    public function col_zone($row)
    {
        $config = new \stdClass();

        $useractivities = block_completion_progress_filter_visibility($this->activities, $row->id, $this->course->id, []);
        $submissions = block_completion_progress_student_submissions($this->course->id, $row->id);
        $completions = block_completion_progress_completions($useractivities, $row->id, $this->course, $submissions);
        $incompletes = block_completion_progress_incomplete_count($useractivities, $completions, $config);
        $zone = block_completion_progress_zone_icon($incompletes);

        return $zone;
    }

    public function col_lastassign($row)
    {
        /** @var \core_renderer $OUTPUT */
        global $OUTPUT, $DB;

        $sql = <<<SQL
SELECT
    ma.id,
    ma.course,
    ma.name,
    mas.userid,
    mas.timecreated,
    mcm.id AS moduleid
FROM
    mdl_assign ma
    JOIN mdl_assign_submission mas ON ma.id = mas.assignment
        AND mas.status = 'submitted'
    JOIN mdl_course_modules mcm ON mcm.instance = ma.id
    JOIN mdl_modules mm ON mm.id = mcm.module
        AND mm.name = 'assign'
WHERE
    ma.course = ?
    AND mas.userid = ?
ORDER BY
    timecreated DESC
LIMIT 1
SQL;

        $assign = $DB->get_record_sql($sql, [$this->course->id, $row->id]);

        if ($assign) {
            $name = $OUTPUT->pix_icon('icon', $assign->name, 'assign') . $assign->name;
            $url = new \moodle_url('/mod/assign/view.php', ['id' => $assign->moduleid]);

            return \html_writer::link($url, $name);
        }

        return get_string('none');
    }

    protected function get_call($courseid, $userid)
    {
        global $DB;

        $sql = <<<SQL
SELECT *
FROM
    mdl_local_srm mls
WHERE
    mls.courseid = ?
    AND mls.userid = ?
ORDER BY
    timecreated DESC
LIMIT 1
SQL;

        return $DB->get_record_sql($sql, [$courseid, $userid]);
    }

    public function col_description($row)
    {
        $courseid = $this->course->id;
        $userid = $row->id;

        if (!isset($this->calls[$userid])) {
            $this->calls[$userid] = $this->get_call($courseid, $userid);
        }

        if ($this->calls[$userid]) {
            return $this->calls[$userid]->description;
        }

        return get_string('none');
    }

    public function col_timecreated($row)
    {
        $courseid = $this->course->id;
        $userid = $row->id;

        if (!isset($this->calls[$userid])) {
            $this->calls[$userid] = $this->get_call($courseid, $userid);
        }

        if ($this->calls[$userid]) {
            return userdate($this->calls[$userid]->timecreated);
        }

        return get_string('never');
    }

    public function col_fullname($data)
    {
        /** @var \core_renderer $OUTPUT */
        global $PAGE;

        $userpicture = new \user_picture($data);
        $url = new \moodle_url('/local/srm/student.php', ['courseid' => $this->course->id, 'userid' => $data->id]);

        $img = \html_writer::img($userpicture->get_url($PAGE), $data->lastname);
        $text = \html_writer::span(fullname($data), '' ,['style' => 'margin: 8px']);

        return \html_writer::link($url, $img . $text);
    }

}
