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
    private $debugOn = true; /**< Enables or disables debug messages for this class*/

    private $info; /**< Array structure containing course information */
    private $courseId; /**< The moodle course id from the database */
    private $trackedModules; /**< Array of trackedModule details */
    private $lecturerId = false; /**< The lecturer id from the database */
    private $group = 0; // EXTRA CODE TO DECIDE GROUP
    
/**
  * standard method called on creation of a moodle formslib inherited class
  * called as an alternative to a constructor.  data sent through the constructor
  * is accessed through $this->_customdata['element name']   
  */
  function definition() {
        global $CFG;
      
        $this->courseId = $this->_customdata['id'];  // Store the current course id 
        $this->get_course_info($this->courseId);     // Grab all the current course info
        
        // Make sure the date picker cannot go beyond the limit of the calendar
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $calOptionsTrue  = array('startyear' => date('Y')-1, 'stopyear' => $calendartype->get_max_year(), 'timezone'=>99 ,'optional' => true);
        $calOptionsFalse  = array('startyear' => date('Y')-1, 'stopyear' => $calendartype->get_max_year(), 'timezone'=>99 ,'optional' => false);
      
        // Start of the tracking form
        $mform =& $this->_form; // Don't forget the underscore!
      
        // Allow the lecturer to unsubscribe from the digest email
        $mform->addElement('advcheckbox', 'LecturerBox', 'Subscribe to the digest report','Emails will be sent to ' . $this->_customdata['email'] ,NULL , array(0, 1));
        
        //  Loop through each section
        foreach ($this->info->sections as $section=>$modules) {
                        
            $sectionInfo = $this->info->get_section_info($section);
            // https://docs.moodle.org/dev/lib/formslib.php_Form_Definition#Use_Fieldsets_to_group_Form_Elements
            $mform->addElement('header', 'Section' . $section, 'Section : ' . str_pad($section, 3, '0', STR_PAD_LEFT) . ' : ' . $sectionInfo->name );
            $mform->setExpanded('Section' . $section);

            // Display a date selector to allow tracking of one section
            $mform->addElement('advcheckbox', 'TrackSectionBox' . $section, 'Track all elements on the same date',NULL ,NULL , array(0, 1));
            $mform->addElement('date_selector', 'TrackSection' . $section, 'Section Tracking Date', $calOptionsFalse);

            // in each section loop through modules and create a form entry row
            foreach($modules as $module) {
                if(isset($this->info->cms[$module]->url)){
                    $mform->addElement('date_selector', 'module' . $module,  $this->info->cms[$module]->name, $calOptionsTrue);
                    $mform->disabledIf('module' . $module, 'TrackSectionBox' . $section, 'checked');
                } // End if
                
            } // End foreach modules

        } // End foreach sections
      
        //Add the standard form buttons
        $this->add_action_buttons($cancel = false, $submitlabel=null);
        // End of the tracking form
        

    }  // end of definition()

/**
  * builds the array data structure that models the structure of the sections and modules
  * in course, it is a simplification of the course information and the values from the database
  * it relies on class variables $info and $trackeModules to build class variable $trackingInfo.
  */
    private function build_tracking_info(){
        
        // Loop through each section in the course
        foreach ($this->info->sections as $section=>$modules) { 
            // Initial state for a section is not tracked with no trackdate and elementname = Section#
            $this->trackingInfo[$section]['tracked'] = 0;
            $this->trackingInfo[$section]['trackDate'] = 0;
            $this->trackingInfo[$section]['element'] = 'Section' . $section;
            
            $moduleCounter = 0; /*< Keeps track of number of modules in the section. */
            $trackedModuleCounter = 0; /*< Keeps track of number of modules with tracking info */
            $lastDateTracked = 0; /*< tracks the previous date track to see if all modules have same date*/
            
            // Loop through all modules in this section
            foreach($modules as $module) { 
                if($this->info->cms[$module]->url){  // If the module has a URL it can be tracked
                    $moduleCounter++;
                    
                    // Store the module descriptive name and record a unique module element name = module#
                    $this->trackingInfo[$section]['modules'][$module]['name'] = $this->info->cms[$module]->name;
                    $this->trackingInfo[$section]['modules'][$module]['element'] = 'module' . $module;
                    
                    
                    if($trackDate = $this->get_completion($module)){
                        // If there was module completion date in the database it is tracked
                        $this->trackingInfo[$section]['modules'][$module]['tracked'] = 1;
                        $this->trackingInfo[$section]['modules'][$module]['trackDate'] = $trackDate;
                        
                        if($lastDateTracked == 0) { 
                            // If this is the first module in the section
                            $lastDateTracked = $trackDate;
                            $trackedModuleCounter++;
                        } else {
                            // If the last module tracking date matches the current module date 
                            // increment the count of tracked modules with matching dates
                            if($lastDateTracked == $trackDate){
                                $trackedModuleCounter++;
                            }
                        }
                        
                    } else {
                        // If there wasn't a module completion date in the database it is not tracked
                        $this->trackingInfo[$section]['modules'][$module]['tracked'] = 0;
                        $this->trackingInfo[$section]['modules'][$module]['trackDate'] = time();
                    }
                    
                }  
            }

            if($moduleCounter == $trackedModuleCounter && $trackedModuleCounter > 1){
                // If all the modules in the section had the same date and there was 
                // more than one module. set the section as tracked using the module date.
                $this->trackingInfo[$section]['tracked'] = 1;
                $this->trackingInfo[$section]['trackDate'] = $lastDateTracked;
            }
            else{
                // All the modules in the section are not the same date so section is not tracked.
                $this->trackingInfo[$section]['tracked'] = 0;
                $this->trackingInfo[$section]['trackDate'] = time();
            }
        }
        //$this->debug_object($this->trackingInfo);
    }

/**
  * Builds a date array for the datepicker form element.
  * 
  * @param int $timeStamp a unix timestamp representing the date.
  * @param bool $enabled true for enabled, false disabled.
  * 
  * @return array with elements referenced by keys day, month, year, enabled.
  */
    private function build_date_array($timeStamp, $enabled){

        $date['day'] = date('j',$timeStamp);
        $date['month'] = date('n',$timeStamp);
        $date['year'] = date('Y',$timeStamp);
        $date['enabled'] = $enabled;
        
        return $date;
    }
    
/**
 * called after the form has been built and all data populated from last submission
 * it stores any submitted data, refreshes any class data required and updates the form values to
 * match what is stored in the database.
 */
    public function definition_after_data() {
        parent::definition_after_data();
        
        $this->get_tracking_info();       // grab any existing tracking information for the course
       

        
        // If the form was submitted
        if($this->is_submitted()){  
            $this->store_tracking_info(); // store the form data in the database
            $this->get_tracking_info();   // grab updated tracking information for the course
        }
        
        $this->remove_orphaned_modules(); // remove orphaned data in the database       
        $this->build_tracking_info();     // build the $trackingInfo array data
        
        $mform =& $this->_form;
        // If the lecturer has an entry in the trackng table
        if($this->lecturerId){
            $lecturerCheckBox =& $mform->getElement('LecturerBox');
            $lecturerCheckBox->setValue(1);
            
        }
        // loop through the sections
        foreach($this->trackingInfo as $section=>$sectionDetails){
            
            // Update section elements
            $sectionElement =& $mform->getElement('TrackSection' . $section);  // Get the section element
            $dateArray = $this->build_date_array($sectionDetails['trackDate'], 1);
            $sectionElement->setValue($dateArray); // Call the set value method it iterates through the array setting the value (DO NOT Use a timestamp)
            
            // If the section is tracked tick the section checkbox
            if($sectionDetails['tracked'] == 1){
                $sectionCheckBox =& $mform->getElement('TrackSectionBox' . $section);
                $sectionCheckBox->setValue(1);
            }
            
            // loop through the sections modules
            /*
            BUGFIX When accessing new course 
            
            Notice: Undefined index: modules in /homepages/43/d520356031/htdocs/clickandbuilds/Moodle/moodle/report/engagement/locallib.php on line 210
            Warning: Invalid argument supplied for foreach() in /homepages/43/d520356031/htdocs/clickandbuilds/Moodle/moodle/report/engagement/locallib.php on line 210
            */
            if(isset($sectionDetails['modules']))
            foreach($sectionDetails['modules'] as $module=>$moduleDetails ) {
                
                $moduleElement =& $mform->getElement('module' . $module);
                
                // Update its tracking date and check its enabled box if it's tracked
                
                if($moduleDetails['tracked'] == 1){
                    $moduleElement->setValue($this->build_date_array($moduleDetails['trackDate'], 1));
                } else {
                    //$moduleElement->setValue($dateArray, 0);
                    //$this->debug_object($moduleDetails['tracked']);
                } // End tracking if
                
            } // End module foreach section
           
        } // End section foreach section
    
    } // End of definition_after_data
    
/**
  * stores the tracking information in the database for later recall
  * 
  */  
    public function store_tracking_info(){
        global $DB;
        
        $formData = array();
        $formData = (array) $this->get_data();
        
        // Build lecturer record for emailing each week
        $lecturerRecord = new stdClass();
        $lecturerRecord->timemodified = time();
        $lecturerRecord->courseid = $this->courseId;
        $lecturerRecord->userid = $this->_customdata['userid'];
        $lecturerRecord->fullname = $this->_customdata['fullname'];
        $lecturerRecord->email = $this->_customdata['email'];
        
        //Check to see if there is an existing entry for this lecturer on this course
        if($this->lecturerId){
           //If the record exists update or delete it based on the form check box
           
            $lecturerRecord->id = $this->lecturerId;
            
            if($formData['LecturerBox'] == 0){
                $DB->delete_records('report_engagement_lecturers', array('courseid'=>$lecturerRecord->courseid, 'userid'=>$lecturerRecord->userid));
            } else {
                $DB->update_record('report_engagement_lecturers', $lecturerRecord);
            }
            
        }else{
           // If the record did not exist create a new record.
           $DB->insert_record('report_engagement_lecturers', $lecturerRecord); 
        }
        

        // For each section in the course
        foreach ($this->info->sections as $section=>$modules) {

            // For each module in the section
            foreach($modules as $module) {
                $trackingDate = 0; // Initial state is module is not tracked i.e. 0 date

                if($formData['TrackSectionBox' . $section] == 0){
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
                
                // If there is a tracking date we need to either update or create a new tracking record
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
                }else{
                    // If there is no tracking date check whether it was previously tracked and if it is delete the record.    
                    if($rowId = $this->is_tracked($module)){
                        $DB->delete_records('report_engagement', array('courseid'=>$this->courseId, 'moduleid'=>$module, 'groupid' => $this->group));
                        //$this->debug_object($module);
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
                
                return $trackedMod->id;
            }
        }
        return false;
    }
/**
  * A simple utility function to check if a module was previously tracked.
  * 
  * @param $module int The id number of the module to check
  * 
  * @return int unix time stamp with the tracking date or 0 if not tracked
  */
    private function get_completion($module) {
        // Loop through the already tracked modules 
        foreach($this->trackedModules as $trackedMod){
            if($trackedMod->moduleid == $module){
                
                return $trackedMod->completeby;
            }
        }
        return 0;
    } 
        
    
/**
  * checks for orphaned data e.g. user has deleted a module in the course
  * but tracking information is still in the database
  * 
  */  
    private function remove_orphaned_modules(){
        global $DB;
        
        //$this->debug_object('remove_orphaned_modules');
        
        //loop through all modules in the tracking database
         foreach($this->trackedModules as $trackedMod){
             
            //if stored module is not in course structure
            if( !isset($this->info->cms[$trackedMod->moduleid]->url) ){  // using isset for improved performance https://bugs.php.net/bug.php?id=38812
                
                // add the module to the delete list or delete immediately?
                $this->debug_object('Deleting Module' . $trackedMod->moduleid); 
                $DB->delete_records('report_engagement', array('courseid'=>$this->courseId, 'moduleid'=>$trackedMod->moduleid, 'groupid'=>$this->group));
            } // end if module not present
         
         } // end foreach tracked modules
    
    } // end remove_orphaned_modules
    
/**
  * populates the $trackedModules array from the database table
  * report_engagment based on the course id.
  * 
  */     
    private function get_tracking_info(){
        global $DB;
        $sql = '';
        $sql .= 'SELECT id, courseid, moduleid, groupid, completeby';
        $sql .= ' FROM {report_engagement}';
        $sql .= ' WHERE courseid = ' . $this->courseId . ' AND groupid = ' . $this->group;
        
        $this->trackedModules = $DB->get_records_sql($sql);
        
        $this->lecturerId = $DB->get_field('report_engagement_lecturers', 'id', array ('courseid'=>$this->courseId, 'userid'=>$this->_customdata['userid']));
        
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
 * @param  int $cmid the id number of the module in the database.
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
        $rs = $DB->get_records_sql($sql); 

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
    
       
        
        
        return $info_string;  // return the data in html list string format

    } // end of course_list()
    
/**
  * A utility function that outputs a formatted dump of the object passed to it.
  * @param object $obj object of any type for output.
  */ 
    private function debug_object($obj){
        
        if($this->debugOn){
            ob_start();  // start output buffering
                print_object($obj);  // pretty print the info from the object
                $this->debugData .= '<kbd>' . ob_get_contents() . '</kbd>'; // wrap a kbd tag to display it nice in the browser
            ob_end_clean();  // end buffering and clear the buffer
        }
        
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
