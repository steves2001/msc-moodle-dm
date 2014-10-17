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
    private $courseId;
    
    public function __construct($course){
        $this->courseId = $course;
        $this->get_course_info($course);
    }
    
/**
  * populates the $info object based on the Moodle internal course id.
  * @param $id numeric course id. 
  */
    private function get_course_info($id){
        global $DB;

        $course = $DB->get_record('course', array('id' => $id));
        $this->info = get_fast_modinfo($course);
    }


/**
  * produces a HTML listof modules in the course and accesses to those modules.
  * Uses class variables $info and $courseId
  * @return string HTML format list of modules and accesses
  */ 
    public function course_list() {
        global $CFG, $DB;

        $modAccessList; // A list of accesses for that course module
        $info_string = '';
    
        foreach ($this->info->cms as $modDesc){
            if($modDesc->url){
               
                
                // https://docs.moodle.org/dev/Data_manipulation_API
                // Build an SQL string to retrieve log entries linked to modules in the current course.
                $sql  = "SELECT {log}.id AS 'logid', module, FROM_UNIXTIME(time) AS 'accessed', userid, username ";
                $sql .= 'FROM {log} INNER JOIN {user} ON {log}.userid = {user}.id ';
                $sql .= 'WHERE cmid = ' . $modDesc->url->get_param('id') . ' AND course = ' . $this->courseId; //DEBUG $info_string .= '<p>' . $sql . '</p>';
                
                // Query the Moodle database and return an array of rows.
                $rs = $DB->get_records_sql($sql); // DEBUG $info_string .= $this->debug_object($rs);
                
                // Populate an ordered list of accesses
                $modAccessList = '';
                foreach($rs as $index => $row){
                    $modAccessList .= '<li value="'. $index . '">' . $row->accessed . ' | ' . $row->userid . ' | ' . $row->username . '</li>';
                }
                //DEBUG $info_string .= '<li>' . $modDesc->name . $modDesc->url->get_path() . $modDesc->url->get_param('id') .'<ol>' . $modAccessList . '</ol></li>' ;
                
                //Build the output string
                $info_string .= '<li>' . $modDesc->name .'<ol>' . $modAccessList . '</ol></li>' ;
                
            }
        }
    
        $info_string = '<ul>' . $info_string . '</ul>';
    
        //DEBUG $info_string = $info_string . $this->debug_object($this->info);
    
        return $info_string;  // return the data in html list string format

    }
/**
  * A utility function that outputs a formatted dump of the object passed to it.
  * @param $obj object of any type for outputs.
  * @return string HTML formatted string of object properties.
  */ 
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
