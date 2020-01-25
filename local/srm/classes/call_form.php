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

require_once($CFG->libdir . '/formslib.php');

/**
 * @author     Payam Yasaie <payam@yasaie.ir>
 * @copyright  2019 Payam Yasaie
 * @package    local_golestan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class call_form extends \moodleform
{

    protected $status = [
        'موفق',
        'عدم پاسخگویی',
        'تماس مجدد'
    ];

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition()
    {
        $mform =& $this->_form;

        $status = array_combine($this->status, $this->status);
        $mform->addElement('select', 'call_status', get_string('status'), $status);
        $mform->addRule('call_status', get_string('required'), 'required');

        $mform->addElement('textarea', 'call_description', get_string('call_description', 'local_srm'), ['style' => 'width:100%']);
        $mform->addRule('call_description', get_string('required'), 'required');

        if (has_capability('local/srm:notice', $this->_customdata->context)) {
            $mform->addElement('checkbox', 'call_alert', get_string('quit_alert', 'local_srm'));
        }

        $this->add_action_buttons(false, get_string('save'));
    }

    public function validation($data, $files)
    {
        $retval = array();

        if (!in_array($data['call_status'], $this->status)) {
            $retval[] = 'status';
        }

        return $retval;
    }



}
