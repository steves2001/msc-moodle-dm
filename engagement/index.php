<?php
require('../../config.php');
require_once($CFG->dirroot.'/report/engagement/locallib.php');

$id = required_param('id',PARAM_INT);       // course id

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('report/engagement:view', $context);

// https://docs.moodle.org/dev/Page_API
$PAGE->set_url('/report/engagement/index.php', array('id'=>$id));
$PAGE->set_title(get_string('pluginname', 'report_engagement'));
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'report_engagement'));
echo $OUTPUT->box_start();

// Do some clever stuff here!!
$eng = new engagement($PAGE->url, array('email'=>'me@me.com','id'=>$id));
$eng->display();
if($eng->is_submitted()){
    $eng->store_tracking_info();
}
echo $OUTPUT->box_end();
echo $OUTPUT->box($eng->debugData);
//echo $OUTPUT->box(print_object($eng->get_data()));
echo $OUTPUT->footer();



