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

/**
 * Class    srm_table
 *
 * @author  Payam Yasaie <payam@yasaie.ir>
 * @since   2019-11-20
 *
 * @package local_srm
 */
class srm_table extends \table_sql
{

    public function __construct($userid, $courseid)
    {
        parent::__construct(__CLASS__);

        $columns = [
            'userid',
            'student_fullname',
            'status',
            'description',
            'timecreated',
            'modfier_fullname',
            'edit'
        ];

        $headers = [
            get_string('userid', 'local_srm'),
            get_string('fullname'),
            get_string('status'),
            get_string('call_description', 'local_srm'),
            get_string('timecreated', 'local_srm'),
            get_string('modifier', 'local_srm'),
            get_string('edit')
        ];

        $fields = [
            's1.id',
            'userid',
            'courseid',
            'status',
            's1.description',
            's1.timecreated',
            'CONCAT(modifier.firstname, " ", modifier.lastname) AS modfier_fullname' ,
            'CONCAT(student.firstname, " ", student.lastname) AS student_fullname' ,
        ];

        $this->sql = (object)[
            'fields' => implode(',', $fields),
            'from' => '{local_srm} as s1, {user} as student, {user} as modifier',
            'where' => 'userid = ? 
                and courseid = ? 
                and modifier.id = s1.usermodified 
                and student.id = s1.userid',
            'params' => [$userid, $courseid]
        ];

        $this->baseurl = new \moodle_url('/local/srm/student.php', compact('userid', 'courseid'));

        $this->set_attribute('class', 'generaltable srm-table');

        $this->sort_default_column = 'timecreated';
        $this->sort_default_order = SORT_DESC;

        $this->define_columns($columns);
        $this->define_headers($headers);
    }

    public function col_timecreated($row)
    {
        return userdate($row->timecreated);
    }

    public function col_edit($row)
    {
        global $OUTPUT;

        $params = [
            'courseid' => $row->courseid,
            'userid' => $row->userid,
            'delete' => $row->id
        ];
        $url = new \moodle_url('/local/srm/student.php', $params);

        return \html_writer::link($url, $OUTPUT->pix_icon('t/delete', get_string('delete')));
    }
}
