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
class event_form extends \event_form
{

    public function definition()
    {
        global $USER;

        $mform =& $this->_form;

        // Add some hidden fields
        $mform->addElement('hidden', 'eventtype');
        $mform->setType('eventtype', PARAM_ALPHA);
        $mform->setDefault('eventtype', 'user');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', $this->_customdata->course->id);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->setDefault('userid', $USER->id);

        $mform->addElement('hidden', 'modulename');
        $mform->setType('modulename', PARAM_INT);
        $mform->setDefault('modulename', '');

        $mform->addElement('hidden', 'instance');
        $mform->setType('instance', PARAM_INT);
        $mform->setDefault('instance', 0);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_INT);

        $mform->addElement('hidden', '_qf__event_form');
        $mform->setDefault('_qf__event_form', 1);

        // Normal fields
        $mform->addElement('text', 'name', get_string('eventname','calendar'), 'size="50"');
        $mform->addRule('name', get_string('required'), 'required');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('date_time_selector', 'timestart', get_string('date'));
        $mform->addRule('timestart', get_string('required'), 'required');

        $this->add_action_buttons(false, get_string('save'));
    }
}
