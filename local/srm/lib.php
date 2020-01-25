<?php

/**
 * Quick fix for Moodle 2.9
 *
 * @param settings_navigation $navigation
 * @param course_context      $context
 * @return void
 */
function local_srm_extend_settings_navigation(settings_navigation $navigation, $context) {
    local_srm_extends_settings_navigation($navigation, $context);
}

function local_srm_extends_settings_navigation(settings_navigation $navigation, $context)
{
    global $CFG;
    // If not in a course context, then leave.
    if ($context == null || $context->contextlevel != CONTEXT_COURSE) {
        return;
    }

    // Front page has a 'frontpagesettings' node, other courses will have 'courseadmin' node.
    if (null == ($courseadminnode = $navigation->get('courseadmin'))) {
        // Keeps us off the front page.
        return;
    }
    if (null == ($useradminnode = $courseadminnode->get('users'))) {
        return;
    }

    if (has_capability('local/srm:report', $context)) {
        $url = new moodle_url($CFG->wwwroot . '/local/srm/index.php', array('courseid' => $context->instanceid));
        $courseadminnode->add(get_string('pluginname', 'local_srm'), $url,
            navigation_node::TYPE_SETTING, null, 'srm', new pix_icon('i/admin', ''));
    }
}