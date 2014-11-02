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
    
    
    public  $debugData = ''; /**< Internal string to hold debug data NOT FOR PRODUCTION USE*/
    
    private $info; /**< Array structure containing course information */
    private $courseId; /**< The moodle course id from the database */
    private $trackedModules; /**< Array of trackedModule details */
    private $group = 0; // EXTRA CODE TO DECIDE GROUP
    private $fullyTrackedSections = array();    
    
/**
  * standard method called on creation of a moodle formslib inherited class
  * called as an alternative to a constructor.  data sent through the constructor
  * is accessed through $this->_customdata['element name']   
  */
  function definition() {
        global $CFG;
        
        
        $this->courseId = $this->_customdata['id'];  // Store the currrent course id 
        $this->get_course_info($this->courseId);     // Grab all the current course info
        $this->get_tracking_info($this->courseId);   // Grab any existing tracking information for the course

        
        // Make sure the date picker cannot go beyond the limit of the calendar
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $calOptionsTrue  = array('startyear' => date('Y'), 'stopyear' => $calendartype->get_max_year(), 'timezone'=>99 ,'optional' => true);
      
        // Start of the tracking form
        $mform =& $this->_form; // Don't forget the underscore! 
      
           
       
        // 
        // remove orphaned data in the database
       
            $this->remove_orphaned_modules($this->courseId);
      
        
        //  Loop through each section
        foreach ($this->info->sections as $section=>$modules) {
            
            // https://docs.moodle.org/dev/lib/formslib.php_Form_Definition#Use_Fieldsets_to_group_Form_Elements
            $mform->addElement('header', 'Section' . $section, 'Section : ' . $section);
            $mform->setExpanded('Section' . $section);
            // Display a date selector to allow tracking of one section
            $mform->addElement('date_selector', 'TrackSection' . $section, 'Track all elements on the same date ', $calOptionsTrue);
            // track the number of trackable modules in the section
            $moduleCounter = 0;
            $trackedModuleCounter = 0;
            $lastDateTracked = 0;

            // in each section loop through modules and create a form entry row
            foreach($modules as $module) {
                if($this->info->cms[$module]->url){
                    $moduleCounter++;
                    $mform->addElement('date_selector', 'module' . $module, $module . " " . $this->info->cms[$module]->name, $calOptionsTrue);
                    
                    if($completeBy = $this->get_completion($module)) {
                        
                        $mform->setDefault('module' . $module, $completeBy);
                        
                        if($lastDateTracked == 0) {
                            $lastDateTracked = $completeBy;
                            $trackedModuleCounter++;
                        } else {
                            if($lastDateTracked == $completeBy){
                                $trackedModuleCounter++;
                            }
                        }
                    }
                    
                    $mform->disabledIf('module' . $module, 'TrackSection' . $section .'[enabled]', 'checked');

                } // End if
                $this->debug_object('Module Counter ' . $moduleCounter . 'Tracked Module Counter' . $trackedModuleCounter);
                
            } // End foreach modules
            if($moduleCounter == $trackedModuleCounter && $trackedModuleCounter > 0){
                // All modules in the section have the same date and are tracked so set the 
                // date for the section to the same date 
                // $mform->setDefault('TrackSection' . $section, $lastDateTracked);
                $this->fullyTrackedSections['TrackSection' . $section] = $lastDateTracked;
                $this->debug_object('Last Date Tracked ' . $lastDateTracked);
            }
        } // End foreach sections
      
        //Add the standard form buttons
        $this->add_action_buttons();
        // End of the tracking form
        
    }  // end of definition()

    
    private function build_date_array($timeStamp, $enabled){

        $date['day'] = date('j',$timeStamp);
        $date['month'] = date('n',$timeStamp);
        $date['year'] = date('Y',$timeStamp);
        $date['enabled'] = $enabled;
        
        return $date;
    }
/**
 * This is called after the form has been built and all data populated from last submission
 */
    public function definition_after_data() {
        parent::definition_after_data();
    
        $mform =& $this->_form;
        foreach($this->fullyTrackedSections as $sectionName => $sectionDate){
            $sectionElement =& $mform->getElement($sectionName);  // Get the section element
            
            // Call the set value method it iterates through the array setting the value (DO NOT Use a timestamp)
            $sectionElement->setValue($this->build_date_array($sectionDate, 1));  
            $this->debug_object($sectionElement);
        } // End of foreach section
    
    } // End of definition_after_data

    
    //DO: Store Module Tracking Info (Course Id)
/**
  * stores the tracking information in the database for later recall
  * 
  */  
    public function store_tracking_info(){
        global $DB;
        
        $formData = array();
        $formData = (array) $this->get_data();
        
        // For each section in the course
        foreach ($this->info->sections as $section=>$modules) {

            // For each module in the section
            foreach($modules as $module) {
                $trackingDate = 0; // Initial state is module is not tracked i.e. 0 date

                if($formData['TrackSection' . $section] == 0){
                    // If the section tracking is not selected individually process modules
                    if($this->info->cms[$module]->url)
                    if($formData['module' . $module] != 0){
                        // if there is a tracking date associated with the module
                        
                        $trackingDate = $formData['module' . $module];
                        
                    } // End module date checking if

                } else {
                    // Else store each module with the section tracking date

                    $trackingDate = $formData['TrackSection' . $section];
                    
                } // End section date checking if
                
                if($trackingDate != 0) {
                    
                    if($this->info->cms[$module]->url){
                        
                        $record = new stdClass();
                        $record->timemodified = time();
                        $record->courseid = $this->courseId;
                        $record->moduleid = $module;
                        $record->groupid = $this->group;
                        $record->completeby = $trackingDate;
                        
                        if($rowId = $this->is_tracked($module)){
                            // if a tracking record already exists sql update
             
                            $record->id = $rowId;
                            $DB->update_record('report_engagement', $record);

                        } else {
                            // if a tracking record does not exist sql insert
                            
                            $DB->insert_record('report_engagement', $record);
                            
                        }
                    }
                }
                
            }
        }
    }
 
/**
  * A simple utility function to check if a module was previously tracked.
  * 
  * @param $module int The id number of the module to check
  */
    private function is_tracked($module) {
        // Loop through the already tracked modules 
        foreach($this->trackedModules as $trackedMod){
            if($trackedMod->moduleid == $module){
                //$this->debug_object($trackedMod);
                return $trackedMod->id;
            }
        }
        return false;
    }
/**
  * A simple utility function to check if a module was previously tracked.
  * 
  * @param $module int The id number of the module to check
  */
    private function get_completion($module) {
        // Loop through the already tracked modules 
        foreach($this->trackedModules as $trackedMod){
            if($trackedMod->moduleid == $module){
                //$this->debug_object($trackedMod);
                return $trackedMod->completeby;
            }
        }
        return false;
    }
        
    //DO: Remove Orphand Modules ()
/**
  * checks for orphaned data e.g. user has deleted a module in the course
  * but tracking information is still in the database
  * 
  * @param $id numeric moodle course id.
  */  
    private function remove_orphaned_modules($id){
        $this->debug_object('remove_orphaned_modules');
    }
    
    
/**
  * populates the $trackedModules array from the database table
  * report_engagment based on the course id.
  * 
  * @param $id numeric moodle course id.
  */     
    private function get_tracking_info($id){
        global $DB;
        $sql = '';
        $sql .= 'SELECT id, courseid, moduleid, groupid, completeby';
        $sql .= ' FROM {report_engagement}';
        $sql .= ' WHERE courseid = ' . $id . ' AND groupid = ' . $this->group;
        
        $this->trackedModules = $DB->get_records_sql($sql);
        //$this->debug_object($this->trackedModules);
        
    } // end of get_tracking_info()
    
/**
  * populates the $info object based on the Moodle internal course id.
  * @param $id numeric moodle course id. 
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
        $sql  = "SELECT {log}.id AS 'logid', module, FROM_UNIXTIME(time) AS 'accessed', userid, username";
        $sql .= ' FROM {log} INNER JOIN {user} ON {log}.userid = {user}.id';
        $sql .= ' WHERE cmid = ' . $cmid . ' AND course = ' . $this->courseId; 
                
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
  */ 
    private function debug_object($obj){

        ob_start();  // start output buffering
        print_object($obj);  // pretty print the info from the object
        $this->debugData .= '<kbd>' . ob_get_contents() . '</kbd>'; // wrap a kbd tag to display it nice in the browser
        ob_end_clean();  // end buffering and clear the buffer
    
        
    } // end of debug_object()
    

} // end of engagement class


/***************************************************************************************************************************
 * REDUNDANT EXPERIMENTAL CODE FOR LATER REMOVAL
 * 
 */

//    function validation($data, $files) {
//        $errors= array();
//        $errors = parent::validation($data, $files);
//        return $errors;
//    }


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
