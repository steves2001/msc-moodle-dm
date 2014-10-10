<?php
require('../../config.php');
require_once($CFG->dirroot.'/report/engagement/locallib.php');

$id = required_param('id',PARAM_INT);       // course id

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

$PAGE->set_url('/report/engagement/index.php', array('id'=>$id));
$PAGE->set_pagelayout('report');

require_login($course);
$context = context_course::instance($course->id);
require_capability('report/engagement:view', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading("Hello World");
echo $OUTPUT->box_start();
// Do some clever stuff here!!
//echo $OUTPUT->box(format_string(eng_course_list($id)));
echo $OUTPUT->box(eng_course_list($id));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();



