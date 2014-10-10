<?php
/**
 * This file contains functions used by the engagement reports
 *
 * @package    report
 * @subpackage engagement
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Added as part of log access
if (!defined('REPORT_LOG_MAX_DISPLAY')) {
    define('REPORT_LOG_MAX_DISPLAY', 150); // days
}

require_once(dirname(__FILE__).'/lib.php');

// Use get_fast_modinfo to create an object to manipulate rather than keeping querying the table
function eng_get_course_info($id){
    global $DB;
    
    
    $course = $DB->get_record('course', array('id' => $id));
    $info = get_fast_modinfo($course);

    return $info;
}

function eng_course_list($id)
{
    global $CFG, $DB;
    // this block grabs data from the log file
    //$sql = "SELECT id, course, module FROM {log}";
    //$courses = $DB->get_records_sql($sql);
    //$remotecoursecount = count($courses);  
    
    $info = eng_get_course_info($id);
    
    /* Start of debugging code
    ob_start();  // start output buffering

    print_object($info);  // pretty print the info from the object
    $info_string = '<kbd>' . ob_get_contents() . '</kbd>'; // wrap a kbd tag to display it nice in the browser
    
    ob_end_clean();  // end buffering and clear the buffer
     end of debugging code */
    $info_string = '';
    
    foreach ($info->cms as $modDesc){
        if($modDesc->url){
            $info_string = '<li>' . $modDesc->url->get_path() . $modDesc->url->get_param('id') .'</li>' . $info_string;
        }
    }
    
    $info_string = '<ul>' . $info_string . '</ul>';
    
    return $info_string;  // return the data in html list string format

    //return  serialize(get_fast_modinfo($course));
    //print_object($info);
    //return $remotecoursecount;
}



// Forget this it is for moodle 2.7
//function test_log_manager() {
 
 //   $logmanager = get_log_manager();
 //   $readers = $logmanager->get_readers();
 //   $reader = reset($readers);
 //   $reader_list = 'Readers :';
//   for each ($reader_type in array_keys($readers)){
//        $reader_list = $reader_list + $reader_type;
        
 //   }
   
//    return $reader_list;
   
    // If reader is not a sql_internal_reader and not legacy store then don't show graph.
    //if (!($reader instanceof \core\log\sql_internal_reader) && !($reader instanceof logstore_legacy\log\store)) {
    //    return array();
   //}
 
//}
// end of forget this
