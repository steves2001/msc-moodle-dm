<?php

namespace report_engagement\event;

/**
 * Event for when some content in engagement report is viewed.
 *
 * @package    report_engagement
 * @copyright  2014 Stephen Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_viewed extends \core\event\content_viewed {

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        if (!empty($this->other['url'])) {
            return new \moodle_url($this->other['url']);
        }
        return new \moodle_url('report/engagement/index.php', array('id' => $this->courseid));
    }
}

