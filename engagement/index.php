<?php
// This file is part of the engan#gment reporting tool
//  
//  Version 0.1a
//  
//  Author: Stephen Smith
//  
//  you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, either version 3 of the License, or
//  (at your option) any later version.

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Display the report
echo $OUTPUT->header();
echo $OUTPUT->heading("Hello World");
echo $OUTPUT->box_start();

echo $OUTPUT->box_end();
echo $OUTPUT->footer();