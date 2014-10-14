<?php
/**
 * This file contains functions used by the engagement reports
 *
 * @package    report
 * @subpackage engagement
 * @copyright  2014 onwards Stephen Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Added as part of log access
if (!defined('REPORT_LOG_MAX_DISPLAY')) {
    define('REPORT_LOG_MAX_DISPLAY', 150); // days
}

require_once(dirname(__FILE__).'/lib.php');


class engagement {
    // Use get_fast_modinfo to create an object to manipulate rather than keeping querying the table
    
    private $info;
    
    public function __construct($courseId){
        $this->get_course_info($courseId);
    }
    
    
    private function get_course_info($id){
        global $DB;

        $course = $DB->get_record('course', array('id' => $id));
        $this->info = get_fast_modinfo($course);
    }

// retrieve the appropriate module information for each module id sent
/*function eng_get_module_info($module_list){
    
    global $DB;
    
    $sql = "SELECT id, course, module FROM {log}";
    
    $rs = $DB->get_records_sql($sql);   
    
    return $module_detail_array;
}*/

    public function course_list() {
        global $CFG, $DB;
        // this block grabs data from the log file
        //$sql = "SELECT id, course, module FROM {log}";
        //$courses = $DB->get_records_sql($sql);
        //$remotecoursecount = count($courses);  
    
        //$info = eng_get_course_info($id);
    
        $info_string = '';
    
        foreach ($this->info->cms as $modDesc){
            if($modDesc->url){
                $info_string .= '<li>' . $modDesc->name . $modDesc->url->get_path() . $modDesc->url->get_param('id') .'</li>' ;
            }
        }
    
        $info_string = '<ul>' . $info_string . '</ul>';
    
        $info_string = $info_string . $this->debug_object($this->info);
    
        return $info_string;  // return the data in html list string format

    }

    private function debug_object($obj){

        ob_start();  // start output buffering
        print_object($obj);  // pretty print the info from the object
        $debug_string = '<kbd>' . ob_get_contents() . '</kbd>'; // wrap a kbd tag to display it nice in the browser
        ob_end_clean();  // end buffering and clear the buffer
    
        return $debug_string;
    }

}

    /**
     * Send a message from one user to another using events_trigger
     *
     * @param object $touser
     * @param object $fromuser
     * @param string $name
     * @param string $subject
     * @param string $message
     */
 /*   protected function notify($touser, $fromuser, $name='courserequested', $subject, $message) {
        $eventdata = new stdClass();
        $eventdata->component         = 'moodle';
        $eventdata->name              = $name;
        $eventdata->userfrom          = $fromuser;
        $eventdata->userto            = $touser;
        $eventdata->subject           = $subject;
        $eventdata->fullmessage       = $message;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        $eventdata->notification      = 1;
        message_send($eventdata);
    }
*/
