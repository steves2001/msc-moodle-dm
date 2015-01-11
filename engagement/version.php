<?php


defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2015011100;            // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2013110500;            // Requires this Moodle version
$plugin->component = 'report_engagement';   // Full name of the plugin (used for diagnostics)
$plugin->cron      = 3000;                   // Do not execute this plugin's cron more often than every 50 minutes.
