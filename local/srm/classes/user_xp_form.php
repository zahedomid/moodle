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

use block_xp\form\user_xp;

defined('MOODLE_INTERNAL') || die();



/**
 * @author     Payam Yasaie <payam@yasaie.ir>
 * @copyright  2019 Payam Yasaie
 * @package    local_golestan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_xp_form extends user_xp
{

    public function definition()
    {
        $mform =& $this->_form;
        $mform->setDisableShortforms(true);

        $mform->addElement('hidden', '_qf__block_xp_form_user_xp');
        $mform->setDefault('_qf__block_xp_form_user_xp', 1);

        $mform->addElement('hidden', 'action');
        $mform->setDefault('action', 'edit');

        $mform->addElement('text', 'xp', get_string('total', 'block_xp'));
        $mform->setType('xp', PARAM_INT);
        $mform->setDefault('xp', $this->_customdata->user_state->get_xp());

        $this->add_action_buttons(false, get_string('save'));
    }

}
