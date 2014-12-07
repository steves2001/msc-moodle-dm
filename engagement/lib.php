<?php
defined('MOODLE_INTERNAL') || die;

/**
 * This function is called by moodles internal cron script
 * it is used to collect user data and communicate with them
 * 
 */

function report_engagement_cron(){
    $access = date("G") * 60 + date("i");
    $go = 14 * 60 + 25;
    $diff = abs($access - $go);
    
    if ($diff > 2) return;
    
    global $DB;
    
    $sql_tracked_users =    
        
"SELECT {log}.id, {log}.time, {log}.cmid, {log}.userid, {user}.firstname, {user}.lastname, {user}.email, 
 DATE_FORMAT(FROM_UNIXTIME(MAX({log}.time)),\"%d %m %y\") as accessed,
 DATE_FORMAT(FROM_UNIXTIME({report_engagement}.completeby),\"%d %m %y\") as date_due,
 TIMESTAMPDIFF (day, FROM_UNIXTIME({report_engagement}.completeby), CURDATE())  AS diff
 FROM {enrol} 
 INNER JOIN {user_enrolments} ON {user_enrolments}.enrolid = {enrol}.id 
 INNER JOIN {user} ON {user}.id = {user_enrolments}.userid 
 INNER JOIN {log} ON {log}.userid = {user}.id
 INNER JOIN {report_engagement} on {log}.cmid = {report_engagement}.moduleid
 WHERE {log}.course = 4 
 AND   (TIMESTAMPDIFF (day, FROM_UNIXTIME({report_engagement}.completeby), CURDATE()) BETWEEN 1 AND 14)
 GROUP BY {log}.userid, {log}.cmid";

    $tracked_users = $DB->get_records_sql($sql_tracked_users);

    $debugData = count($tracked_users) .  "\n";
    foreach($tracked_users as $index => $row){
            $debugData .=  $index . " : " . $row->time . ", " . $row->email . "\n";
    }
   
        
    mail('steves2001@gmail.com','Engagement Report', $debugData ); 
    
}



/**
 * This function extends the course navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_engagement_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/engagement:view', $context)) {
        $url = new moodle_url('/report/engagement/index.php', array('id'=>$course->id));
        $navigation->add(get_string('pluginname', 'report_engagement'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

/**
 * This function extends the course navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $user
 * @param stdClass $course The course to object for the report

function report_engagement_extend_navigation_user($navigation, $user, $course) {
    if (report_engagement_can_access_user_report($user, $course)) {
        $url = new moodle_url('/report/engagement/user.php', array('id'=>$user->id, 'course'=>$course->id, 'mode'=>'engagement'));
        $navigation->add(get_string('engagementreport'), $url);
        $url = new moodle_url('/report/engagement/user.php', array('id'=>$user->id, 'course'=>$course->id, 'mode'=>'complete'));
        $navigation->add(get_string('completereport'), $url);
    }
}
 */
/**
 * Is current user allowed to access this report
 *
 * @private defined in lib.php for performance reasons
 *
 * @param stdClass $user
 * @param stdClass $course
 * @return bool
 */
function report_engagement_can_access_user_report($user, $course) {
    global $USER;

    $coursecontext = context_course::instance($course->id);
    $personalcontext = context_user::instance($user->id);

    if (has_capability('report/engagement:view', $coursecontext)) {
        return true;
    }

    if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext)) {
        if ($course->showreports and (is_viewing($coursecontext, $user) or is_enrolled($coursecontext, $user))) {
            return true;
        }

    } else if ($user->id == $USER->id) {
        if ($course->showreports and (is_viewing($coursecontext, $USER) or is_enrolled($coursecontext, $USER))) {
            return true;
        }
    }

    return false;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function report_engagement_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                    => get_string('page-x', 'pagetype'),
        'report-*'             => get_string('page-report-x', 'pagetype'),
        'report-engagement-*'     => get_string('page-report-engagement-x',  'report_engagement'),
        'report-engagement-index' => get_string('page-report-engagement-index',  'report_engagement'),
        'report-engagement-user'  => get_string('page-report-engagement-user',  'report_engagement')
    );
    return $array;
}
