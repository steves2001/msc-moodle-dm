<?php
// This file is part of the engan#gment reporting tool
//  
//  Version 0.4a
//  
//  Author: Stephen Smith
//  
//  you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, either version 3 of the License, or
//  (at your option) any later version.

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

//  Authenticate before using the page
$id          = optional_param('id', 0, PARAM_INT);// Course ID
$host_course = optional_param('host_course', '', PARAM_PATH);// Course ID

if (empty($host_course)) {
    $hostid = $CFG->mnet_localhost_id;
    if (empty($id)) {
        $site = get_site();
        $id = $site->id;
    }
} else {
    list($hostid, $id) = explode('/', $host_course);
}


if ($hostid == $CFG->mnet_localhost_id) {
    $course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

} else {
    $course_stub       = $DB->get_record('mnet_log', array('hostid'=>$hostid, 'course'=>$id), '*', true);
    $course->id        = $id;
    $course->shortname = $course_stub->coursename;
    $course->fullname  = $course_stub->coursename;
}


require_login($course);

$context = context_course::instance($course->id);

require_capability('report/engagement:view', $context);

// Display the report
echo $OUTPUT->header();
echo $OUTPUT->heading("Hello World");
echo $OUTPUT->box_start();
// Do some clever stuff here!!
echo $OUTPUT->box_end();
echo $OUTPUT->footer();