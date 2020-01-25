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
 * Completion Progress block common configuration and helper functions
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/completionlib.php');

// Global defaults.
const DEFAULT_COMPLETIONPROGRESS_WRAPAFTER = 16;
const DEFAULT_COMPLETIONPROGRESS_LONGBARS = 'squeeze';
const DEFAULT_COMPLETIONPROGRESS_SCROLLCELLWIDTH = 25;
const DEFAULT_COMPLETIONPROGRESS_COURSENAMETOSHOW = 'shortname';
const DEFAULT_COMPLETIONPROGRESS_SHOWINACTIVE = 0;
const DEFAULT_COMPLETIONPROGRESS_PROGRESSBARICONS = 1;
const DEFAULT_COMPLETIONPROGRESS_ORDERBY = 'orderbytime';
const DEFAULT_COMPLETIONPROGRESS_SHOWPERCENTAGE = 0;
const DEFAULT_COMPLETIONPROGRESS_ACTIVITIESINCLUDED = 'activitycompletion';

/**
 * Finds submissions for a user in a course
 *
 * @param int    courseid ID of the course
 * @param int    userid   ID of user in the course
 *
 * @return array Course module IDS submissions
 */
function block_completion_progress_student_submissions($courseid, $userid)
{
    global $DB;

    $submissions = array();
    $params = array('courseid' => $courseid, 'userid' => $userid);

    // Queries to deliver instance IDs of activities with submissions by user.
    $queries = array(
        'assign' => "SELECT c.id
                       FROM {assign_submission} s, {assign} a, {modules} m, {course_modules} c
                      WHERE s.userid = :userid
                        AND s.latest = 1
                        AND s.status = 'submitted'
                        AND s.assignment = a.id
                        AND a.course = :courseid
                        AND m.name = 'assign'
                        AND m.id = c.module
                        AND c.instance = a.id",
        'workshop' => "SELECT DISTINCT c.id
                         FROM {workshop_submissions} s, {workshop} w, {modules} m, {course_modules} c
                        WHERE s.authorid = :userid
                          AND s.workshopid = w.id
                          AND w.course = :courseid
                          AND m.name = 'workshop'
                          AND m.id = c.module
                          AND c.instance = w.id",
    );

    foreach ($queries as $moduletype => $query) {
        $results = $DB->get_records_sql($query, $params);
        foreach ($results as $cmid => $obj) {
            $submissions[] = $cmid;
        }
    }

    return $submissions;
}

/**
 * Finds submissions for users in a course
 *
 * @param int    courseid   ID of the course
 *
 * @return array Mapping of userid-cmid pairs for submissions
 */
function block_completion_progress_course_submissions($courseid)
{
    global $DB;

    $submissions = array();
    $params = array('courseid' => $courseid);

    // Queries to deliver instance IDs of activities with submissions by user.
    $queries = array(
        'assign' => "SELECT " . $DB->sql_concat('s.userid', "'-'", 'c.id') . "
                       FROM {assign_submission} s, {assign} a, {modules} m, {course_modules} c
                      WHERE s.latest = 1
                        AND s.status = 'submitted'
                        AND s.assignment = a.id
                        AND a.course = :courseid
                        AND m.name = 'assign'
                        AND m.id = c.module
                        AND c.instance = a.id",
        'workshop' => "SELECT " . $DB->sql_concat('s.authorid', "'-'", 'c.id') . "
                         FROM {workshop_submissions} s, {workshop} w, {modules} m, {course_modules} c
                        WHERE s.workshopid = w.id
                          AND w.course = :courseid
                          AND m.name = 'workshop'
                          AND m.id = c.module
                          AND c.instance = w.id",
    );

    foreach ($queries as $moduletype => $query) {
        $results = $DB->get_records_sql($query, $params);
        foreach ($results as $mapping => $obj) {
            $submissions[] = $mapping;
        }
    }

    return $submissions;
}

/**
 * Returns the alternate links for teachers
 *
 * @return array URLs and associated capabilities, per activity
 */
function block_completion_progress_modules_with_alternate_links()
{
    global $CFG;

    $alternatelinks = array(
        'assign' => array(
            'url' => '/mod/assign/view.php?id=:cmid&action=grading',
            'capability' => 'mod/assign:grade',
        ),
        'feedback' => array(
            // Breaks if anonymous feedback is collected.
            'url' => '/mod/feedback/show_entries.php?id=:cmid&do_show=showoneentry&userid=:userid',
            'capability' => 'mod/feedback:viewreports',
        ),
        'lesson' => array(
            'url' => '/mod/lesson/report.php?id=:cmid&action=reportdetail&userid=:userid',
            'capability' => 'mod/lesson:viewreports',
        ),
        'quiz' => array(
            'url' => '/mod/quiz/report.php?id=:cmid&mode=overview',
            'capability' => 'mod/quiz:viewreports',
        ),
    );

    if ($CFG->version > 2015111604) {
        $alternatelinks['assign']['url'] = '/mod/assign/view.php?id=:cmid&action=grade&userid=:userid';
    }

    return $alternatelinks;
}

/**
 * Returns the activities with completion set in current course
 *
 * @param int    courseid   ID of the course
 * @param int    config     The block instance configuration
 * @param string forceorder An override for the course order setting
 *
 * @return array Activities with completion settings in the course
 */
function block_completion_progress_get_activities($courseid, $config = null, $forceorder = null)
{
    $modinfo = get_fast_modinfo($courseid, -1);
    $sections = $modinfo->get_sections();
    $activities = array();
    foreach ($modinfo->instances as $module => $instances) {
        $modulename = get_string('pluginname', $module);
        foreach ($instances as $index => $cm) {
            if (
                $cm->completion != COMPLETION_TRACKING_NONE && (
                    $config == null || (
                        !isset($config->activitiesincluded) || (
                            $config->activitiesincluded != 'selectedactivities' ||
                            !empty($config->selectactivities) &&
                            in_array($module . '-' . $cm->instance, $config->selectactivities))))
            ) {
                $activities[] = array(
                    'type' => $module,
                    'modulename' => $modulename,
                    'id' => $cm->id,
                    'instance' => $cm->instance,
                    'name' => format_string($cm->name),
                    'expected' => $cm->completionexpected,
                    'section' => $cm->sectionnum,
                    'position' => array_search($cm->id, $sections[$cm->sectionnum]),
                    'url' => method_exists($cm->url, 'out') ? $cm->url->out() : '',
                    'context' => $cm->context,
                    'icon' => $cm->get_icon_url(),
                    'available' => $cm->available,
                );
            }
        }
    }

    // Sort by first value in each element, which is time due.
    if ($forceorder == 'orderbycourse' || ($config && $config->orderby == 'orderbycourse')) {
        usort($activities, 'block_completion_progress_compare_events');
    } else {
        usort($activities, 'block_completion_progress_compare_times');
    }

    return $activities;
}

/**
 * Used to compare two activities/resources based on order on course page
 *
 * @param array $a array of event information
 * @param array $b array of event information
 *
 * @return <0, 0 or >0 depending on order of activities/resources on course page
 */
function block_completion_progress_compare_events($a, $b)
{
    if ($a['section'] != $b['section']) {
        return $a['section'] - $b['section'];
    } else {
        return $a['position'] - $b['position'];
    }
}

/**
 * Used to compare two activities/resources based their expected completion times
 *
 * @param array $a array of event information
 * @param array $b array of event information
 *
 * @return <0, 0 or >0 depending on time then order of activities/resources
 */
function block_completion_progress_compare_times($a, $b)
{
    if (
        $a['expected'] != 0 &&
        $b['expected'] != 0 &&
        $a['expected'] != $b['expected']
    ) {
        return $a['expected'] - $b['expected'];
    } else if ($a['expected'] != 0 && $b['expected'] == 0) {
        return -1;
    } else if ($a['expected'] == 0 && $b['expected'] != 0) {
        return 1;
    } else {
        return block_completion_progress_compare_events($a, $b);
    }
}

/**
 * @author  Payam Yasaie <payam@yasaie.ir>
 * @since   2019-10-15
 *
 * @param     $activities
 * @param     $userid
 * @param     $courseid
 * @param     $exclusions
 * @param int $module
 *
 * @return array
 * @throws coding_exception
 * @throws moodle_exception
 */
function block_completion_progress_filter_visibility($activities, $userid, $courseid, $exclusions, $module = 0)
{
    global $CFG;
    $filteredactivities = array();
    $modinfo = get_fast_modinfo($courseid, $userid);
    $coursecontext = CONTEXT_COURSE::instance($courseid);

    // Keep only activities that are visible.
    foreach ($activities as $index => $activity) {

        $coursemodule = $modinfo->cms[$activity['id']];

        # filter modules if was set
        if ($module and $coursemodule->modname != $module) {
            continue;
        }

        // Check visibility in course.
        if (!$coursemodule->visible && !has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
            continue;
        }

        // Check availability, allowing for visible, but not accessible items.
        if (!empty($CFG->enableavailability)) {
            if (has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
                $activity['available'] = true;
            } else {
                if (isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo)) {
                    continue;
                }
                $activity['available'] = $coursemodule->available;
            }
        }

        // Check visibility by grouping constraints (includes capability check).
        if (!empty($CFG->enablegroupmembersonly)) {
            if (isset($coursemodule->uservisible)) {
                if ($coursemodule->uservisible != 1 && empty($coursemodule->availableinfo)) {
                    continue;
                }
            } else if (!groups_course_module_visible($coursemodule, $userid)) {
                continue;
            }
        }

        // Check for exclusions.
        if (in_array($activity['type'] . '-' . $activity['instance'] . '-' . $userid, $exclusions)) {
            continue;
        }

        // Save the visible event.
        $filteredactivities[] = $activity;
    }
    return $filteredactivities;
}

/**
 * Checked if a user has completed an activity/resource
 *
 * @param array $activities The activities with completion in the course
 * @param int   $userid The user's id
 * @param int   $course The course instance
 * @param array $submissions Submissions by the user
 *
 * @return array   an describing the user's attempts based on module+instance identifiers
 */
function block_completion_progress_completions($activities, $userid, $course, $submissions)
{
    $completions = array();
    $completion = new completion_info($course);
    $cm = new stdClass();

    foreach ($activities as $activity) {
        /** @var object $cm */
        $cm->id = $activity['id'];
        $activitycompletion = $completion->get_data($cm, true, $userid);
        $completions[$activity['id']] = $activitycompletion->completionstate;
        if ($completions[$activity['id']] === COMPLETION_INCOMPLETE && in_array($activity['id'], $submissions)) {
            $completions[$activity['id']] = 'submitted';
        }
    }

    return $completions;
}

/**
 * Draws a progress bar
 *
 * @param array    $activities The activities with completion in the course
 * @param array    $completions The user's completion of course activities
 * @param stdClass $config The blocks instance configuration settings
 * @param int      $userid The user's id
 * @param int      $courseid The course id
 * @param int      instance     The block instance (to identify it on page)
 * @param bool     $simple Controls whether instructions are shown below a progress bar
 *
 * @return string  Progress Bar HTML content
 */
function block_completion_progress_bar($activities, $completions, $config, $userid, $courseid, $instance, $simple = false)
{
    /** @var core_renderer $OUTPUT */
    global $OUTPUT, $CFG, $USER;
    $content = '';
    $now = time();
    $usingrtl = right_to_left();
    $numactivities = count($activities);
    $dateformat = get_string('strftimedate', 'langconfig');
    $alternatelinks = block_completion_progress_modules_with_alternate_links();

    // Get colours and use defaults if they are not set in global settings.
    $colournames = array(
        'completed_colour' => 'completed_colour',
        'submittednotcomplete_colour' => 'submittednotcomplete_colour',
        'notCompleted_colour' => 'notCompleted_colour',
        'futureNotCompleted_colour' => 'futureNotCompleted_colour'
    );
    $colours = array();
    foreach ($colournames as $name => $stringkey) {
        $colours[$name] = get_config('block_completion_progress', $name) ?: get_string('block_completion_progress', $stringkey);
    }

    // Get relevant block instance settings or use defaults.
    $useicons = isset($config->progressBarIcons) ? $config->progressBarIcons : DEFAULT_COMPLETIONPROGRESS_PROGRESSBARICONS;
    $orderby = isset($config->orderby) ? $config->orderby : DEFAULT_COMPLETIONPROGRESS_ORDERBY;
    $defaultlongbars = get_config('block_completion_progress', 'defaultlongbars') ?: DEFAULT_COMPLETIONPROGRESS_LONGBARS;
    $longbars = isset($config->longbars) ? $config->longbars : $defaultlongbars;
    $displaynow = $orderby == 'orderbytime';
    $showpercentage = isset($config->showpercentage) ? $config->showpercentage : DEFAULT_COMPLETIONPROGRESS_SHOWPERCENTAGE;
    $rowoptions = array();
    $rowoptions['style'] = '';
    $content .= HTML_WRITER::start_div('barContainer');

    // Determine the segment width.
    $wrapafter = get_config('block_completion_progress', 'wrapafter') ?: DEFAULT_COMPLETIONPROGRESS_WRAPAFTER;
    if ($wrapafter <= 1) {
        $wrapafter = 1;
    }
    if ($numactivities <= $wrapafter) {
        $longbars = 'squeeze';
    }
    if ($longbars == 'wrap') {
        $rows = ceil($numactivities / $wrapafter);
        if ($rows <= 1) {
            $rows = 1;
        }
        $cellwidth = floor(100 / ceil($numactivities / $rows));
        $cellunit = '%';
        $celldisplay = 'inline-block';
        $displaynow = false;
    }
    if ($longbars == 'scroll') {
        $cellwidth = DEFAULT_COMPLETIONPROGRESS_SCROLLCELLWIDTH;
        $cellunit = 'px';
        $celldisplay = 'inline-block';
        $rowoptions['style'] .= 'white-space: nowrap;';
        $leftpoly = HTML_WRITER::tag('polygon', '', array('points' => '30,0 0,15 30,30', 'class' => 'triangle-polygon'));
        $rightpoly = HTML_WRITER::tag('polygon', '', array('points' => '0,0 30,15 0,30', 'class' => 'triangle-polygon'));
        $content .= HTML_WRITER::tag('svg', $leftpoly, array('class' => 'left-arrow-svg', 'height' => '30', 'width' => '30'));
        $content .= HTML_WRITER::tag('svg', $rightpoly, array('class' => 'right-arrow-svg', 'height' => '30', 'width' => '30'));
    }
    if ($longbars == 'squeeze') {
        $cellwidth = $numactivities > 0 ? floor(100 / $numactivities) : 1;
        $cellunit = '%';
        $celldisplay = 'table-cell';
    }

    // Determine where to put the NOW indicator.
    $nowpos = -1;
    if ($orderby == 'orderbytime' && $longbars != 'wrap' && $displaynow == 1 && !$simple) {

        // Find where to put now arrow.
        $nowpos = 0;
        while ($nowpos < $numactivities && $now > $activities[$nowpos]['expected'] && $activities[$nowpos]['expected'] != 0) {
            $nowpos++;
        }
        $rowoptions['style'] .= 'margin-top: 25px;';
        $nowstring = get_string('now_indicator', 'block_completion_progress');
        $leftarrowimg = $OUTPUT->pix_icon('left', $nowstring, 'block_completion_progress', array('class' => 'nowicon'));
        $rightarrowimg = $OUTPUT->pix_icon('right', $nowstring, 'block_completion_progress', array('class' => 'nowicon'));
    }

    // Determine links to activities.
    for ($i = 0; $i < $numactivities; $i++) {
        if ($userid != $USER->id &&
            array_key_exists($activities[$i]['type'], $alternatelinks) &&
            has_capability($alternatelinks[$activities[$i]['type']]['capability'], $activities[$i]['context'])
        ) {
            $substitutions = array(
                '/:courseid/' => $courseid,
                '/:eventid/' => $activities[$i]['instance'],
                '/:cmid/' => $activities[$i]['id'],
                '/:userid/' => $userid,
            );
            $link = $alternatelinks[$activities[$i]['type']]['url'];
            $link = preg_replace(array_keys($substitutions), array_values($substitutions), $link);
            $activities[$i]['link'] = $CFG->wwwroot . $link;
        } else {
            $activities[$i]['link'] = $activities[$i]['url'];
        }
    }

    // Start progress bar.
    $content .= HTML_WRITER::start_div('barRow', $rowoptions);
    $counter = 1;
    $incompletes = 0;

    foreach ($activities as $activity) {
        $complete = $completions[$activity['id']];

        // A cell in the progress bar.
        $showinfojs = 'M.block_completion_progress.showInfo(' . $instance . ',' . $userid . ',' . $activity['id'] . ');';
        $celloptions = array(
            'class' => 'progressBarCell',
            'ontouchstart' => $showinfojs . ' return false;',
            'onmouseover' => $showinfojs,
            'style' => 'display:' . $celldisplay . '; width:' . $cellwidth . $cellunit . ';background-color:');
        if ($complete === 'submitted') {
            $celloptions['style'] .= $colours['submittednotcomplete_colour'] . ';';
            $cellcontent = $OUTPUT->pix_icon('blank', '', 'block_completion_progress');

        } else if ($complete == COMPLETION_COMPLETE || $complete == COMPLETION_COMPLETE_PASS) {
            $celloptions['style'] .= $colours['completed_colour'] . ';';
            $cellcontent = $OUTPUT->pix_icon($useicons == 1 ? 'tick' : 'blank', '', 'block_completion_progress');

        } else if (
            $complete == COMPLETION_COMPLETE_FAIL ||
//            (!isset($config->orderby) || $config->orderby == 'orderbytime') &&
            (isset($activity['expected']) && $activity['expected'] > 0 && $activity['expected'] < $now)
        ) {
            $celloptions['style'] .= $colours['notCompleted_colour'] . ';';
            $cellcontent = $OUTPUT->pix_icon($useicons == 1 ? 'cross' : 'blank', '', 'block_completion_progress');
            $incompletes++;

        } else {
            $celloptions['style'] .= $colours['futureNotCompleted_colour'] . ';';
            $cellcontent = $OUTPUT->pix_icon('blank', '', 'block_completion_progress');
        }
        if (!empty($activity['available']) || $simple) {
            $celloptions['onclick'] = 'document.location=\'' . $activity['link'] . '\';';
        } else if (!empty($activity['link'])) {
            $celloptions['style'] .= 'cursor: not-allowed;';
        }
        if ($longbars != 'wrap' && $counter == 1) {
            $celloptions['class'] .= ' firstProgressBarCell';
        }
        if ($longbars != 'wrap' && $counter == $numactivities) {
            $celloptions['class'] .= ' lastProgressBarCell';
        }

        // Place the NOW indicator.
        if ($nowpos >= 0) {
            if ($nowpos == 0 && $counter == 1) {
                $nowcontent = $usingrtl ? $rightarrowimg . $nowstring : $leftarrowimg . $nowstring;
                $cellcontent .= HTML_WRITER::div($nowcontent, 'nowDiv firstNow');
            } else if ($nowpos == $counter) {
                if ($nowpos < $numactivities / 2) {
                    $nowcontent = $usingrtl ? $rightarrowimg . $nowstring : $leftarrowimg . $nowstring;
                    $cellcontent .= HTML_WRITER::div($nowcontent, 'nowDiv firstHalfNow');
                } else {
                    $nowcontent = $usingrtl ? $nowstring . $leftarrowimg : $nowstring . $rightarrowimg;
                    $cellcontent .= HTML_WRITER::div($nowcontent, 'nowDiv lastHalfNow');
                }
            }
        }

        $counter++;
        $content .= HTML_WRITER::div($cellcontent, null, $celloptions);

    }
    $content .= HTML_WRITER::end_div();
    $content .= HTML_WRITER::end_div();

    // Add the percentage below the progress bar.
    if ($showpercentage == 1 && !$simple) {
        $progress = block_completion_progress_percentage($activities, $completions);
        $percentagecontent = get_string('progress', 'block_completion_progress') . ': ' . $progress . '%';
        $percentageoptions = array('class' => 'progressPercentage');
        $content .= HTML_WRITER::tag('div', $percentagecontent, $percentageoptions);
    }

    // Add the info box below the table.
    $divoptions = array('class' => 'progressEventInfo',
        'id' => 'progressBarInfo' . $instance . '-' . $userid . '-info');
    $content .= HTML_WRITER::start_tag('div', $divoptions);
    if (!$simple) {
        $content .= get_string('mouse_over_prompt', 'block_completion_progress');
        $content .= ' ';
        $attributes = array(
            'class' => 'accesshide',
            'onclick' => 'M.block_completion_progress.showAll(' . $instance . ',' . $userid . ')'
        );
        $content .= HTML_WRITER::link('#', get_string('showallinfo', 'block_completion_progress'), $attributes);
    }
    $content .= HTML_WRITER::end_tag('div');

    // Add hidden divs for activity information.
    $stringincomplete = get_string('completion-n', 'completion');
    $stringcomplete = get_string('completed', 'completion');
    $stringpassed = get_string('completion-pass', 'completion');
    $stringfailed = get_string('completion-fail', 'completion');
    $stringsubmitted = get_string('submitted', 'block_completion_progress');
    foreach ($activities as $activity) {
        $completed = $completions[$activity['id']];
        $divoptions = array('class' => 'progressEventInfo',
            'id' => 'progressBarInfo' . $instance . '-' . $userid . '-' . $activity['id'],
            'style' => 'display: none;');
        $content .= HTML_WRITER::start_tag('div', $divoptions);

        $text = '';
        $text .= html_writer::empty_tag('img',
            array('src' => $activity['icon'], 'class' => 'moduleIcon', 'alt' => '', 'role' => 'presentation'));
        $text .= s(format_string($activity['name']));
        if (!empty($activity['link']) && (!empty($activity['available']) || $simple)) {
            $content .= $OUTPUT->action_link($activity['link'], $text);
        } else {
            $content .= $text;
        }
        $content .= HTML_WRITER::empty_tag('br');
        $altattribute = '';
        if ($completed == COMPLETION_COMPLETE) {
            $content .= $stringcomplete . '&nbsp;';
            $icon = 'tick';
            $altattribute = $stringcomplete;
        } else if ($completed == COMPLETION_COMPLETE_PASS) {
            $content .= $stringpassed . '&nbsp;';
            $icon = 'tick';
            $altattribute = $stringpassed;
        } else if ($completed == COMPLETION_COMPLETE_FAIL) {
            $content .= $stringfailed . '&nbsp;';
            $icon = 'cross';
            $altattribute = $stringfailed;
        } else {
            $content .= $stringincomplete . '&nbsp;';
            $icon = 'cross';
            $altattribute = $stringincomplete;
            if ($completed === 'submitted') {
                $content .= '(' . $stringsubmitted . ')&nbsp;';
                $altattribute .= '(' . $stringsubmitted . ')';
            }
        }

        $content .= $OUTPUT->pix_icon($icon, $altattribute, 'block_completion_progress', array('class' => 'iconInInfo'));
        $content .= HTML_WRITER::empty_tag('br');
        if ($activity['expected'] != 0) {
            $content .= HTML_WRITER::start_tag('div', array('class' => 'expectedBy'));
            $content .= get_string('time_expected', 'block_completion_progress') . ': ';
            $content .= userdate($activity['expected'], $dateformat, $CFG->timezone);
            $content .= HTML_WRITER::end_tag('div');
        }
        $content .= HTML_WRITER::end_tag('div');
    }

    return $content;
}

/**
 * Calculates an overall percentage of progress
 *
 * @param array $activities The possible events that can occur for modules
 * @param array $completions The user's attempts on course activities
 *
 * @return int  Progress value as a percentage
 */
function block_completion_progress_percentage($activities, $completions)
{
    $completecount = 0;

    foreach ($activities as $activity) {
        if (
            $completions[$activity['id']] == COMPLETION_COMPLETE ||
            $completions[$activity['id']] == COMPLETION_COMPLETE_PASS
        ) {
            $completecount++;
        }
    }

    $progressvalue = $completecount == 0 ? 0 : $completecount / count($activities);

    return (int)round($progressvalue * 100);
}

/**
 * Checks whether the current page is the My home page.
 *
 * @return bool True when on the My home page.
 */
function block_completion_progress_on_site_page()
{
    global $SCRIPT, $COURSE;

    return $SCRIPT === '/my/index.php' || $COURSE->id == 1;
}

/**
 * Finds gradebook exclusions for students in a course
 *
 * @param int $courseid The ID of the course containing grade items
 *
 * @return array of exclusions as activity-user pairs
 */
function block_completion_progress_exclusions($courseid)
{
    global $DB;

    $query = "SELECT g.id, " . $DB->sql_concat('i.itemmodule', "'-'", 'i.iteminstance', "'-'", 'g.userid') . " as exclusion
               FROM {grade_grades} g, {grade_items} i
              WHERE i.courseid = :courseid
                AND i.id = g.itemid
                AND g.excluded <> 0";
    $params = array('courseid' => $courseid);
    $results = $DB->get_records_sql($query, $params);
    $exclusions = array();
    foreach ($results as $key => $value) {
        $exclusions[] = $value->exclusion;
    }
    return $exclusions;
}

/**
 * Determines whether a user is a member of a given group or grouping
 *
 * @param string $group The group or grouping identifier starting with 'group-' or 'grouping-'
 * @param int    $courseid The ID of the course containing the block instance
 *
 * @return boolean value indicating membership
 */
function block_completion_progress_group_membership($group, $courseid, $userid)
{
    if ($group === '0') {
        return true;
    } else if ((substr($group, 0, 6) == 'group-') && ($groupid = intval(substr($group, 6)))) {
        return groups_is_member($groupid, $userid);
    } else if ((substr($group, 0, 9) == 'grouping-') && ($groupingid = intval(substr($group, 9)))) {
        return array_key_exists($groupingid, groups_get_user_groups($courseid, $userid));
    }

    return false;
}

/**
 * @author  Payam Yasaie <payam@yasaie.ir>
 * @since   2019-10-15
 *
 * @param $courseid
 *
 * @return array
 * @throws coding_exception
 * @throws moodle_exception
 */
function block_completion_progress_mods_list($courseid)
{
    $modinfo = get_fast_modinfo($courseid);
    $mods = [];

    foreach ($modinfo->get_cms() as $cm) {
        if (
            !isset($mods[$cm->modname])
            and $cm->completion == COMPLETION_ENABLED
            and $cm->visible == 1
        ) {
            $mods[$cm->modname] = get_string('pluginname', $cm->modname);
        }
    }

    return $mods;
}


/**
 * @author  Payam Yasaie <payam@yasaie.ir>
 * @since   2019-10-28
 *
 * @param $activities
 * @param $completions
 * @param $config
 *
 * @return int
 */
function block_completion_progress_incomplete_count($activities, $completions, $config)
{
    $now = time();
    $incompletes = 0;

    foreach ($activities as $activity) {
        $complete = $completions[$activity['id']];
        if (
            (
                !isset($config->count_module)
                or !$config->count_module
                or $config->count_module == $activity['type']
            )
            and (
                $complete == COMPLETION_COMPLETE_FAIL ||
                (
                    isset($activity['expected'])
                    && $activity['expected'] > 0
                    && $activity['expected'] < $now
                    && $complete != COMPLETION_COMPLETE
                )
            )
        ) {
            $incompletes++;
        }
    }

    return $incompletes;
}

function block_completion_progress_zone_icon($incompletes)
{
    /** @var core_renderer $OUTPUT */
    global $OUTPUT;

    $icons = [
        'green' => $OUTPUT->pix_icon('tick', get_string('greenzone', 'block_completion_progress'), 'block_completion_progress', ['class' => 'big-img']),
        'yellow' => $OUTPUT->pix_icon('exclamation', get_string('yellowzone', 'block_completion_progress'), 'block_completion_progress', ['class' => 'big-img']),
        'orange' => $OUTPUT->pix_icon('bell', get_string('orangezone', 'block_completion_progress'), 'block_completion_progress', ['class' => 'big-img']),
        'red' => $OUTPUT->pix_icon('cross', get_string('redzone', 'block_completion_progress'), 'block_completion_progress', ['class' => 'big-img'])
    ];

    $names = array_keys($icons);
    $name = isset($names[$incompletes]) ? $names[$incompletes] : $incompletes;

    return isset($icons[$name]) ? $icons[$name] : end($icons);
}

/**
 * @author  Payam Yasaie <payam@yasaie.ir>
 * @since   2019-10-14
 *
 * @param $incompletes
 *
 * @return string
 * @throws coding_exception
 */
function block_completion_progress_zone_status($incompletes)
{
    global $PAGE;
    # Alert user of their progress in 4 zone (perfect, warning, alert, danger)
    $content = '';
    $zone = new \stdClass();
    $zone->count = $incompletes;

    $icon = block_completion_progress_zone_icon($zone->count);
    $header = html_writer::tag('h5', "$icon%text%$icon");

    switch ($zone->count) {
        case 0:
            $content .= html_writer::start_div('text-center alert alert-success popup');
            $content .= str_replace('%text%', get_string('perfect', 'block_completion_progress'), $header);
            $content .= html_writer::tag('p', 'شما در وضعیت مطلوب قرار دارید');
            break;
        case 1:
            $content .= html_writer::start_div('text-center alert alert-warning popup');
            $content .= str_replace('%text%', get_string('warning', 'block_completion_progress'), $header);
            $zone->name = get_string('yellowzone', 'block_completion_progress');
            $content .= html_writer::tag('p', get_string('youareinthezone', 'block_completion_progress', $zone));
            break;
        case 2:
            $content .= html_writer::start_div('text-center alert alert-alert popup');
            $content .= str_replace('%text%', get_string('alert', 'block_completion_progress'), $header);
            $zone->name = get_string('orangezone', 'block_completion_progress');
            $content .= html_writer::tag('p', get_string('youareinthezone', 'block_completion_progress', $zone));
            break;
        default:
            $content .= html_writer::start_div('text-center alert alert-danger popup');
            $content .= str_replace('%text%', get_string('danger', 'block_completion_progress'), $header);
            $zone->name = get_string('redzone', 'block_completion_progress');
            $content .= html_writer::tag('p', get_string('youareinthezone', 'block_completion_progress', $zone));
            break;
    }
    $content .= html_writer::end_div();

    $PAGE->requires->js_call_amd('block_completion_progress/popup', 'init');

    return $content;
}

function block_completion_progress_users_zones($activities, $course, $config)
{
    $users = enrol_get_course_users($course->id, 1);
    $content = '';
    $zones = [
        'green' => 0,
        'yellow' => 0,
        'orange' => 0,
        'red' => 0
    ];

    foreach ($users as $user) {

        if (!isset($config->count_roles) or block_completion_progress_has_any_role($user, $config->count_roles, context_course::instance($course->id))) {
            $submissions = block_completion_progress_student_submissions($course->id, $user->id);
            $completions = block_completion_progress_completions($activities, $user->id, $course, $submissions);
            $incompletes = block_completion_progress_incomplete_count($activities, $completions, $config);

            switch ($incompletes) {
                case 0:
                    $zones['green']++;
                    break;
                case 1:
                    $zones['yellow']++;
                    break;
                case 2:
                    $zones['orange']++;
                    break;
                default:
                    $zones['red']++;
                    break;
            }
        }

    }

    $content .= html_writer::start_div('alert alert-info');

    foreach ($zones as $zone => $count) {
        $icon = block_completion_progress_zone_icon($zone);
        $zone = get_string("{$zone}zone", 'block_completion_progress');
        $attr = (object)compact('zone', 'count');
        $text = get_string('usersinthezone', 'block_completion_progress', $attr);
        $content .= html_writer::tag('p', "$icon $text", ['style' => 'font-size: .8em']);
    }

    $content .= html_writer::end_div();

    return $content;
}

function block_completion_progress_has_any_role($user, $roles, $context)
{
    $result = false;

    foreach ($roles as $role) {
        if (user_has_role_assignment($user->id, $role, $context->id)) {
            $result = true;
        }
    }

    return $result;
}