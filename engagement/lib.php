<?php
defined('MOODLE_INTERNAL') || die;
require_once('twitteroauth/twitteroauth.php'); // Abraham Williams Twitter REST API 

/**
 * This function checks a day of the week and an hour against the
 * system time returning true if they match
 * 
 * @param int $dueDay range 0-6 day of the week 0 = Sunday.
 * @param int $dueHour range 0-23 hour of the day.
 * 
 * @return bool true if day and hour match else false.
 */
function report_due($dueDay = 0, $dueHour = 10){
    
    $currentDay     = date("w"); /**< the systems day of the week 0 = Sunday */
    $currentHour    = date("G"); /**< the systems hour of the day 0-23 */
    
    if($dueDay == $currentDay && $dueHour == $currentHour) 
        return true;

    return false;
}

/**
 * This function is called by moodles internal cron script
 * it is used to collect user data and communicate with them
 * 
 */

function report_engagement_cron(){
    mtrace( "****************** Hi this is the engagement report ******************");
    $mail = true;               /**< Boolean set to true to enable emailing */
    $twitter = true;            /**< Boolean set to true to enable twitter DM */
    $restrict = true;          /**< Boolean set to true to restrict script execution to a single attempt a day */

    $tables['lecturer'] = 'report_engagement_lecturers';
    $tables['tracking'] = 'report_engagement';
    
    $student = array();         /**< Array of students and the modules they have missed */
    $lecturer = array();        /**< Array of lecturer details */
    $courseLecturer = array();  /**< Array of courses and lecturer ids */
    
    
    // Limit the script running to a fixed time of day
    if ($restrict && !report_due(0,12)) return;

    
    global $DB;
    
    $sql_tracked_modules = 
        
"SELECT moduleid, completeby
 FROM {report_engagement}
 WHERE {report_engagement}.courseid = ?" . 
" AND   (TIMESTAMPDIFF (day, FROM_UNIXTIME({report_engagement}.completeby), CURDATE()) BETWEEN 1 AND 14)";
    
    $sql_course_users = 
        
"SELECT {user}.`id`, `username`, `firstname`, `lastname`, `email`, `aim`, `lastlogin`, `lastaccess` 
 FROM {user} 
 INNER JOIN {user_enrolments} ON {user}.id = {user_enrolments}.userid 
 INNER JOIN {enrol} ON {user_enrolments}.enrolid = {enrol}.id
 WHERE {enrol}.courseid = ?";
    
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
 WHERE {log}.course = ?" .
" AND   (TIMESTAMPDIFF (day, FROM_UNIXTIME({report_engagement}.completeby), CURDATE()) BETWEEN 1 AND 14)
 GROUP BY {log}.userid, {log}.cmid";
    

    /* Get the lecturer details */
    
    $rs = $DB->get_recordset($tables['lecturer']);
    
    foreach ($rs as $record) {

        /* populate the the lecturer array key is the lecturer id */
        if(!isset($lecturer[$record->userid])){
            $lecturer[$record->userid]['fullname'] = $record->fullname;
            $lecturer[$record->userid]['email']    = $record->email;
        }

        /* if there are no lectuers assigned to the course set the count to 0 */
        if( !isset($courseLecturer[$record->courseid]['count']) ){
            $courseLecturer[$record->courseid]['count'] = 0;
        }
        
        /* add the lecturer id to the list of lecturers tracking that course */
        $courseLecturer[$record->courseid]['lecturers'][$courseLecturer[$record->courseid]['count']] = $record->userid;
        $courseLecturer[$record->courseid]['count']++;
    }
    //print_r($courseLecturer);
    $rs->close();
    /* loop through the courses that are tracked */
    foreach($courseLecturer as $course => $lecturerList){
       
        $info = get_fast_modinfo($course);
        $courseInfo = $info->get_course();

        $records = NULL;
        
        /* Get tracked modules */
        $records = $DB->get_records_sql($sql_tracked_modules, array($course));
        //print("Course " . $course . "\n");
        //print_r($records);
        /* if there are tracked records */
        if(!empty($records)){
        
            $tracked_modules = array();
        
            foreach($records as $index => $row){
                $tracked_modules[$index] = $row->completeby;
            
            } /* end foreach */
        
            $records = NULL;
    
            /* Get all users on the course and add tracked modules to each user*/
            $records = $DB->get_records_sql($sql_course_users, array($course));
            
            $student = NULL;
            
            foreach($records as $index => $row){
        
                /* if the user is not a lecturer */
                if( !isset($lecturer[$index]) ){
                    $student[$index]["username"] = $row->username;
                    $student[$index]["firstname"] = $row->firstname;
                    $student[$index]["name"] = $row->firstname .  " " . $row->lastname;
                    $student[$index]["twitter"] = $row->aim;
                    $student[$index]["email"] = $row->email;
                    $student[$index]["modules"] = $tracked_modules; 
                } /* end if user is not a lecturer */
        
            } /* end for each user on course */ 
        
            $records = NULL;
            /* if there were any students on the course */
            if(isset($student)){
                
                /* Get user log entries for tracked modules */
                $records = $DB->get_records_sql($sql_tracked_users, array($course));
                foreach($records as $index => $row){
                    unset($student[$row->userid]["modules"][$row->cmid]);
                } /* end log entry loop */
        
                $records = NULL;
                /* end get user log entries */
        
                /* set up twitter authentication info */
                require_once('config.php');  /* Twitter Account Credentials PRIVATE */
        
                $connection = new TwitterOAuth($DM_CFG->twit_consumer_key, 
                                   $DM_CFG->twit_consumer_secret, 
                                   $DM_CFG->twit_oauth_token, 
                                   $DM_CFG->twit_oauth_token_secret);
   
                $digestData = "Student Report\n\n";

                /* for each student on the course */    
                foreach($student as $index => $row){
        
                    $summaryData = "";/**< String containing student summary info */
                    $lateCount   = 0; /**< Numeric count of how many items a student has missed */
                    $emailData   = "";/**< String to hold the students email message */
                    $missedData  = "\n" . $courseInfo->fullname . "\n"; /**< String to hold the info on what the student missed */
                    
                    $summaryData .= "\n" . $row['name'] . ' <' . $row['email'] . '> has not accessed the following on Moodle : ';
                    
                    /* loop through identifying missed modules */
                    foreach($row['modules'] as $module => $due) {
                        
                        $sectionInfo = $info->get_section_info($info->cms[$module]->sectionnum);
                    
                        $missedData .= "\n     " . $info->cms[$module]->name 
                                . " in section " . $info->cms[$module]->sectionnum 
                                . " " . $sectionInfo->name
                                . " should have been completed by : " 
                                . date("d-m-y", $due);
                    
                        $lateCount++;
                            
                    } /* end of module checking loop */

                    
                    /* if the student is overdue on work */
                    if($lateCount > 0){
                        /* Build digest email for lecturer */
                        $digestData .= $summaryData . "\n " . $missedData . "\n";
                    }        
                    
                    /* if the student has a twitter account */
                    if($row["twitter"] != ""){
                
                        /* if the student is overdue on work */
                        if($lateCount > 0){
            
                            /* Build a twitter direct message*/
                            $options = array("screen_name" => $row["twitter"], 
                            "text" => "Hi " . $row["firstname"] . ", you have missed " 
                            . $lateCount . " activities on Moodle in the last two weeks. Please check your email messages.");
            
                            /* build an email message to detail the missed work */
                            $emailData = "Hi " 
                            . $row["firstname"] 
                            . ",\n\nYou seem to have missed some work on Moodle in the last two weeks. "
                            . "To catch up you need to complete the following:\n" 
                            . $missedData . "\n";
                    
                            /* send email to student */
                            $mail ? mail($row["email"],"Missed coursework", $emailData, 'From: ' . 'moodle@stephensmith.me.uk' . "\r\n") : print_r($emailData);
                
                        }else{
                
                            /* Build a twitter direct message congratulating the student on being up-to-date */
                            $options = array("screen_name" => $row["twitter"], 
                            "text" => "Hi " . $row["firstname"] . ", you have completed all your activities on Moodle in the last two weeks. well done!");
                        }
                    
                        /* Send twitter direct message */
                        $twitter ? $connection->post('direct_messages/new', $options): print('Twitter DM to : ' . $options['screen_name'] . " : " . $options['text'] . "\n");

                    } /* end of twitter account if */
                
                } /* end of the for each student loop */
    
                /* send digest email to each lecturer */
                foreach($lecturerList['lecturers'] as $lecturer_id) {
                    $mail ? mail($lecturer[$lecturer_id]['email'],'Engagement Report', $digestData, 'From: ' . 'noreply@computing-moodle.co.uk' . "\r\n" ) : print($lecturer[$lecturer_id]['email'] . "\n" . $digestData);
                }
            
            } /* end if the if checking if there are students to be tracked on the course */
            
        } /* end of the if relating to whether we have any records to track */
        
    } /* end of course loop */
    
} /*end of the crontab function */



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
