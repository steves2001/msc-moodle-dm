<?php
/**
 * This file contains functions used by the engagement reports
 *
 * @package    report
 * @subpackage engagement
 * @copyright  2014 onwards Stephen Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// prevent a direct page call
defined('MOODLE_INTERNAL') || die;

// Added as part of log access
if (!defined('REPORT_LOG_MAX_DISPLAY')) {
    define('REPORT_LOG_MAX_DISPLAY', 150); // days
}

require_once(dirname(__FILE__).'/lib.php');

require_once("$CFG->libdir/formslib.php"); // Required for building a moodle form
 


class engagement extends moodleform {
    
    private $info;
    private $courseId;
    
    
/**
  * standard method called on creation of a moodle formslib inherited class
  * called as an alternative to a constructor.  data sent through the constructor
  * is accessed through $this->_customdata['element name']   
  */
  function definition() {
        global $CFG;
        
        $this->courseId = $this->_customdata['id'];  // Store the currrent course id 
        $this->get_course_info($this->courseId);     // Grab all the current course info
        
        $formRow = array();                          // An array of form elements for a row (module)
      
        // Start of the tracking form
        $mform =& $this->_form; // Don't forget the underscore! 
        
        //  Loop through each section
        foreach ($this->info->sections as $section=>$modules) {
            // https://docs.moodle.org/dev/lib/formslib.php_Form_Definition#Use_Fieldsets_to_group_Form_Elements
            $mform->addElement('header', 'Section' . $section, 'Section : ' . $section);
            $mform->setExpanded('Section' . $section);
            
            // in each section loop through modules and create a form entry row
            foreach($modules as $module) {
                if($this->info->cms[$module]->url){
                    unset($formRow);
                    $formRow = array();
                    $formRow[] =& $mform->createElement('advcheckbox', 'module' . $module, $this->info->cms[$module]->name, null, array('group' => $module), array(0, $module));
                    $formRow[] =& $mform->createElement('date_selector', 'completeBy', 'Complete task by');

                    // https://docs.moodle.org/dev/lib/formslib.php_Form_Definition#addGroup
                    $mform->addGroup($formRow, $module, $this->info->cms[$module]->name, array(' '), false);
                } // End if
            } // End foreach modules
            
        } // End foreach sections
      
        //Add the standard form buttons
        $this->add_action_buttons();
        // End of the tracking form
        
    }  // end of definition()
    
    
/**
  * populates the $info object based on the Moodle internal course id.
  * @param $id numeric course id. 
  */
    private function get_course_info($id){
        global $DB;

        $course = $DB->get_record('course', array('id' => $id));
        $this->info = get_fast_modinfo($course);
    } // end of get_course_info()
    
    
/**
 * produces an ordered list of the logs for the specfied module
 * uses class variable $courseId
 * 
 * @param  $cmid int the id number of the module in the database.
 * 
 * @return string ordered list of module accesses.
 * 
 **/
    private function get_module_logs($cmid) {
        global $DB;
        
        $modAccessList = ''; // A list of accesses for that course module

        // https://docs.moodle.org/dev/Data_manipulation_API
        // Build an SQL string to retrieve log entries linked to modules in the current course.
        $sql  = "SELECT {log}.id AS 'logid', module, FROM_UNIXTIME(time) AS 'accessed', userid, username ";
        $sql .= 'FROM {log} INNER JOIN {user} ON {log}.userid = {user}.id ';
        $sql .= 'WHERE cmid = ' . $cmid . ' AND course = ' . $this->courseId; 
                
        // Query the Moodle database and return an array of rows.
        $rs = $DB->get_records_sql($sql); // DEBUG $info_string .= $this->debug_object($rs);

        // Populate an ordered list of accesses
        foreach($rs as $index => $row){
            $modAccessList .= '<li value="'. $index . '">' . $row->accessed . ' | ' . $row->userid . ' | ' . $row->username . '</li>';
        }
        
        return '<ol>' . $modAccessList . '</ol>';
    } // end of get_module_logs()

    
/**
  * produces a HTML listof modules in the course and accesses to those modules.
  * Uses class variables $info and $courseId
  * 
  * @return string HTML format list of modules and accesses
  */ 
    public function course_list() {
        global $CFG, $DB;

        //$modulesbysection = $this->info->sections;
        $info_string = '';
        
        //foreach($this->info->section as section=>modules)
            
    
        foreach ($this->info->cms as $modDesc){
            if($modDesc->url){
              
                //Build the output string
                $info_string .= '<li>' . $modDesc->name . $this->get_module_logs( $modDesc->url->get_param('id') ) .'</li>' ;
                
            }
        }
    
        $info_string = '<ul>' . $info_string . '</ul>';
    
        //$info_string = $info_string . $this->debug_object($this->info->sections);
        
        
        return $info_string;  // return the data in html list string format

    } // end of course_list()
    
    
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
    } // end of debug_object()
    

} // end of engagement class


/***************************************************************************************************************************
 * REDUNDANT EXPERIMENTAL CODE FOR LATER REMOVAL
 * 
 */


/** *PRACTICE FORM

class simplehtml_form extends moodleform {
 
    function definition() {
        global $CFG;
 
        $mform =& $this->_form; // Don't forget the underscore! 
        $mform->addElement('hidden','id','');
        $mform->setDefault('id',$this->_customdata['id']);
        
        $mform->setType('id', PARAM_INT);
        // Adding a textbox with some validation
        $mform->addElement('text', 'email', 'emailt', 'maxlength="100" size="25" ');
        $mform->setType('email', PARAM_NOTAGS);
        $mform->addRule('email', 'missingemail', 'required', null, 'server');
        // Set default value by using a passed parameter
        $mform->setDefault('email',$this->_customdata['email']);
        
        //Adding a checkbox
        $mform->addElement('checkbox', 'ratingtime', 'label 1', 'label 2');
        $mform->addElement('advcheckbox', 'module', 'Label Left', 'Label Right', array('group' => 1), array(0, 50));
        //Add the standard form buttons
        $this->add_action_buttons();
        
    }                           // Close the function
}                               // Close the class

*/


/**
     * Send a message from one user to another using events_trigger
     *
     * @param object $touser
     * @param object $fromuser
     * @param string $name
     * @param string $subject
     * @param string $message
     */

 /** *MESSAGING CODE TO BE LOOKED AT LATER
     protected function notify($touser, $fromuser, $name='courserequested', $subject, $message) {
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
