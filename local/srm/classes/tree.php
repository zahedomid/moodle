<?php


namespace local_srm;

use core_user\output\myprofile\tree as base_tree;
use core_user\output\myprofile\category;
use core_user\output\myprofile\node;

defined('MOODLE_INTERNAL') || die();

class tree extends base_tree
{
    /** @var \stdClass $user */
    protected $user;

    /** @var \stdClass $course */
    protected $course;

    /** @var \course_modinfo|null */
    protected $mods;

    /** @var \context_course $context */
    protected $context;

    public function __construct($user, $course)
    {
        $this->user = $user;
        $this->course = $course;
        $this->context = \context_course::instance($course->id);
        $this->mods = get_fast_modinfo($course);
    }

    public function contact()
    {
        $contactcategory = new category('contact', get_string('userdetails'));

        $this->add_category($contactcategory);

        $url = new \moodle_url('/user/editadvanced.php', array('id' => $this->user->id, 'course' => $this->course->id,
            'returnto' => 'profile'));
        $node = new node('contact', 'editprofile', get_string('editmyprofile'), null, $url,
            null, null, 'editprofile');
        $this->add_node($node);

        $categories = profile_get_user_fields_with_data_by_category($this->user->id);
        foreach ($categories as $categoryid => $fields) {
            foreach ($fields as $formfield) {
                if (!$formfield->is_empty()) {
                    $node = new node('contact', 'custom_field_' . $formfield->field->shortname,
                        format_string($formfield->field->name), null, null, $formfield->display_data());
                    $this->add_node($node);
                }
            }
        }
    }

    public function notes()
    {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/notes/lib.php');

        $notescategory = new category('notes', get_string('notes', 'notes'));

        $this->add_category($notescategory);

        $courseid = $this->course->id;
        $userid = $this->user->id;
        $context = $this->context;

        $strsitenotes = get_string('sitenotes', 'notes');
        $strcoursenotes = get_string('coursenotes', 'notes');
        $strpersonalnotes = get_string('personalnotes', 'notes');

        $addid = has_capability('moodle/notes:manage', $context) ? $courseid : 0;
        $view = has_capability('moodle/notes:view', $context);
        $fullname = format_string($this->course->fullname, true, array('context' => $context));

        ob_start();
        note_print_notes(
            '',
            $addid,
            $view,
            0,
            $userid,
            NOTES_STATE_SITE,
            0
        );
        $sitenotes = ob_get_clean();
        $node = new node('notes', 'sitenotes', $strsitenotes, null, null,
            $sitenotes);
        $this->add_node($node);

        ob_start();
        note_print_notes(
            '',
            $addid,
            $view,
            $courseid,
            $userid,
            NOTES_STATE_PUBLIC,
            0
        );
        $coursenotes = ob_get_clean();
        $node = new node('notes', 'coursenotes', $strcoursenotes . ' (' . $fullname . ')', null, null,
            $coursenotes);
        $this->add_node($node);

        ob_start();
        note_print_notes(
            '',
            $addid,
            $view,
            $courseid,
            $userid,
            NOTES_STATE_DRAFT,
            $USER->id
        );
        $personalnotes = ob_get_clean();
        $node = new node('notes', 'personalnotes', $strpersonalnotes, null, null,
            $personalnotes);
        $this->add_node($node);
    }

    public function access()
    {
        global $USER, $DB;

        $loginactivitycategory = new category('loginactivity', get_string('loginactivity'));

        $this->add_category($loginactivitycategory);

        // Last access.
        $string = get_string('lastcourseaccess');
        if ($lastaccess = $DB->get_record('user_lastaccess', array('userid' => $this->user->id, 'courseid' => $this->course->id))) {
            $datestring = userdate($lastaccess->timeaccess) . "&nbsp; (" . format_time(time() - $lastaccess->timeaccess) . ")";
        } else {
            $datestring = get_string("never");
        }

        $node = new node('loginactivity', 'lastaccess', $string, null, null,
            $datestring);
        $this->add_node($node);

        // Last ip.
        if (has_capability('moodle/user:viewlastip', \context_user::instance($USER->id))) {
            if ($this->user->lastip) {
                $iplookupurl = new \moodle_url('/iplookup/index.php', array('ip' => $this->user->lastip, 'user' => $this->user->id));
                $ipstring = \html_writer::link($iplookupurl, $this->user->lastip);
            } else {
                $ipstring = get_string("none");
            }
            $node = new node('loginactivity', 'lastip', get_string('lastip'), null, null,
                $ipstring);
            $this->add_node($node);
        }
    }

    public function report()
    {
        $reportcategory = new category('reports', get_string('reports'));

        $this->add_category($reportcategory);

        $pluginswithfunction = get_plugins_with_function('myprofile_navigation', 'lib.php');

        foreach ($pluginswithfunction['report'] as $function) {
            $function($this, $this->user, 0, $this->course);
        }
    }

    public function detail_report()
    {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/report/outline/locallib.php');

        $course = $this->course;
        $user = $this->user;

        $reportcategory = new category('detail_report', 'مطالب تالارها');
        $this->add_category($reportcategory);

        $url = new \moodle_url('/mod/forum/user.php', [
            'id' => $user->id,
            'course' => $course->id
        ]);

        $forums = $this->mods->get_instances_of('forum');
        $libfile = "$CFG->dirroot/mod/forum/lib.php";
        require_once($libfile);

        foreach ($forums as $mod) {
            $instance = $DB->get_record("$mod->modname", array("id" => $mod->instance));
            $output = forum_user_outline($course, $user, $mod, $instance);

            if ($output) {
                $output->info = \html_writer::link($url, $output->info);
            }

            ob_start();
            report_outline_print_row($mod, $instance, $output);
            $row = ob_get_clean();

            $node = new node('detail_report', 'forum_report' . $mod->instance, '', null, null,
                $row);
            $this->add_node($node);
        }
    }

    public function call_form()
    {
        global $DB, $USER;

        $callcategory = new category('call_form', 'تماس جدید');

        $this->add_category($callcategory);

        $url = new \moodle_url('/local/srm/student.php', [
            'courseid' => $this->course->id,
            'userid' => $this->user->id,
        ]);
        $customdata = new \stdClass();
        $customdata->context = $this->context;

        $form = new call_form($url, $customdata);
        ob_start();
        $form->display();
        $output = ob_get_clean();

        $node = new node('call_form', 'call_form', '', null, null,
            $output);
        $this->add_node($node);

        if ($data = $form->get_data()) {
            $dataobject = new \stdClass();
            $dataobject->userid = $this->user->id;
            $dataobject->courseid = $this->course->id;
            $dataobject->usermodified = $USER->id;
            $dataobject->status = $data->call_status;
            $dataobject->description = $data->call_description;
            $dataobject->timecreated = time();

            $DB->insert_record('local_srm', $dataobject);

            if (
                isset($data->call_alert)
                and $data->call_alert
                and has_capability('local/srm:notice', $this->context)
            ) {
                $text = get_string('notice_message', 'local_srm', \html_writer::link($url, fullname($this->user)))
                    . '<br>' . $dataobject->description;

                $message = new \core\message\message();
                $message->component = 'local_srm';
                $message->name = 'notice_quiting';
                $message->userfrom = 1;
                $message->subject = get_string('quit_alert', 'local_srm');
                $message->fullmessage = $text;
                $message->fullmessageformat = FORMAT_MARKDOWN;
                $message->fullmessagehtml = "<p>$text</p>";
                $message->smallmessage = $text;
                $message->notification = 1;
//                $message->replyto = $message->userfrom->email;
                $message->courseid = $this->course->id; // This is required in recent versions, use it from 3.2 on https://tracker.moodle.org/browse/MDL-47162

                $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
                $teachers = get_users_from_role_on_context($role, $this->context);

                foreach ($teachers as $teacher) {
                    $message->userto = $teacher->userid;

                    $messageid = message_send($message);
                }

            }
        }
    }

    public function event_form()
    {
        global $CFG;

        require_once($CFG->dirroot . '/calendar/event_form.php');

        $callcategory = new category('event_form', 'یادآوری جدید');

        $this->add_category($callcategory);

        $url = new \moodle_url('/calendar/event.php');

        $customdata = new \stdClass();
        $customdata->course = $this->course;

        $form = new event_form($url, $customdata);
        ob_start();
        $form->display();
        $output = ob_get_clean();

        $node = new node('event_form', 'event_form', '', null, null,
            $output);
        $this->add_node($node);
    }

    public function completion_progress()
    {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/blocks/completion_progress/lib.php');

        $course = $this->course;
        $user = $this->user;
        $config = new \stdClass();
        $config->orderby = 'orderbycourse';
        $config->longbars = 'wrap';

        $completionprogresscategory = new category('completion_progress', 'وضعیت تکمیل');

        $this->add_category($completionprogresscategory);

        // Check if any activities/resources have been created.
        $exclusions = block_completion_progress_exclusions($course->id);
        $activities = block_completion_progress_get_activities($course->id, $config);
        $activities = block_completion_progress_filter_visibility($activities, $user->id, $course->id, $exclusions);

        $submissions = block_completion_progress_student_submissions($course->id, $user->id);
        $completions = block_completion_progress_completions($activities, $user->id, $course, $submissions);

        $output = block_completion_progress_bar(
            $activities,
            $completions,
            $config,
            $user->id,
            $course->id,
            0
        );

        $node = new node('completion_progress', 'completion_progress', '', null, null,
            $output);
        $this->add_node($node);


        // Organise access to JS.
        $jsmodule = array(
            'name' => 'block_completion_progress',
            'fullpath' => '/blocks/completion_progress/module.js',
            'requires' => array(),
            'strings' => array(),
        );
        $arguments = array(array(0), array($user->id));
        $PAGE->requires->js_init_call('M.block_completion_progress.setupScrolling', array(), false, $jsmodule);
        $PAGE->requires->js_init_call('M.block_completion_progress.init', $arguments, false, $jsmodule);

    }


    public function gamification()
    {
        global $CFG, $PAGE, $USER;

        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/xp/block_xp.php');

        $current_user = $USER;

        $gamificationcategory = new category('gamification', 'تجربیات');

        $this->add_category($gamificationcategory);

        /** @var \block_xp\local\block\course_block $xp */
        $xp = new \block_xp();
        $xp->page = $PAGE;
        $USER = $this->user;
        $content = $xp->get_content();
        $USER = $current_user;
        $text = "<div class='block block_xp'>" . $content->text . '</div>';

        ob_start();
        $world = \block_xp\di::get('course_world_factory')->get_world($this->course->id);
        $state = $world->get_store()->get_state($this->user->id);

        $url = new \moodle_url('/blocks/xp/index.php/report/' . $this->course->id, ['userid' => $this->user->id]);

        $customdata = new \stdClass();
        $customdata->user_state = $state;

        $form = new user_xp_form((string)$url, $customdata);
        $form->display();
        $text .= \html_writer::div(ob_get_clean(), '', ['style' => 'margin-top: 20px']);

        $node = new node('gamification', 'gamification', '', null, null,
            $text);
        $this->add_node($node);

    }

    public function attendance()
    {
        global $DB, $CFG, $PAGE;

        $user = $this->user;
        $course = $this->course;

        require_once($CFG->dirroot . '/mod/attendance/locallib.php');

        $attendances = $this->mods->get_instances_of('attendance');

        $PAGE->requires->js_call_amd('local_srm/attendance', 'init');

        $colors = [
            'attendance-red',
            'attendance-blue',
            'attendance-green'
        ];

        if ($attendances) {
            $out_array = [];

            foreach ($attendances as $cm_info) {

                $cm = get_coursemodule_from_id('attendance', $cm_info->id, $course->id, false, MUST_EXIST);
                $attrecord = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

                $pageparams = new \mod_attendance_report_page_params();
                $pageparams->init($cm);
                $pageparams->startdate = 0;
                $pageparams->enddate = 0;
                $att = new \mod_attendance_structure($attrecord, $cm, $course, null, $pageparams);

                $sessions = $att->get_filtered_sessions();
                $statuses = $att->get_statuses();

                $head = [];
                $foot = [];
                foreach ($sessions as $session) {
                    $log = $att->get_session_log($session->id);

                    if (isset($log[$user->id])) {
                        $user_log = $log[$user->id];
                        $statusid = $user_log->statusid;
                        $status = $statuses[$statusid];

                        $head[] = '<span class="' . $colors[(int)$status->grade] . '" data-id="' . $user_log->id . '">' . $status->acronym . '</span>';

                        $foot[] = '<ul id="attendance-detail-' . $user_log->id . '">';
                        $foot[] = '<li>' . strip_tags($session->description) . '</li>';
                        $foot[] = '<li>' . userdate($session->sessdate) . '</li>';
                        $foot[] = '<li>' . $status->description . '</li>';
                        $foot[] = '</ul>';
                    }
                }

                if ($head) {
                    $output = '<div class="attendance-bar">';

                    $output .= '<div class="attendance-names">';
                    $output .= implode($head);
                    $output .= '</div>';

                    $output .= '<div class="attendance-details">';
                    $output .= '<ul style="display: block"><li>برای نمایش اطلاعات ماوس را حرکت دهید.</li></ul>';
                    $output .= implode($foot);
                    $output .= '</div>';

                    $output .= '</div>';

                    $out_array[] = $output;
                }
            }

            if ($out_array) {
                $attendancecategory = new category('attendance', 'وضعیت حضور');

                $this->add_category($attendancecategory);

                foreach ($out_array as $text) {
                    $node = new node('attendance', 'attendance', '', null, null,
                        $text);
                    $this->add_node($node);
                }
            }
        }

    }
}