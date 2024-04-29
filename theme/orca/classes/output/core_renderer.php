<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_orca\output;

use moodle_url;


defined('MOODLE_INTERNAL') || die;


// Map idnumber of course categories to custom urls in the navbar.
// \todo Correct urls.
const CATEGORY_URLS = [
    'oka' => '/../searchresults?learningResourceType=["https%3A%2F%2Fw3id.org%2Fkim%2Fhcrt%2Fcourse"]',
    'osa' => '/../searchresults?learningResourceType=["https%3A%2F%2Fw3id.org%2Fkim%2Fhcrt%2Fassessment"]'
];


/**
 * Render specialized ORCA features.
 *
 * @package   theme_orca
 * @copyright 2021 metromorph softworks GmbH <devel@metromorph.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \core_renderer {
    // \todo\future Generalize for configurable certificates.
    /**
     * Dropdown menu making download of certificate or report selectable.
     */
    public function certificate_component($courseid)
    {
        global $USER, $DB;
        
        $url = function ($doctype) use ($courseid) {
            return (new moodle_url('/course/format/certificates/view.php', ['type'=>$doctype, 'id'=>$courseid, 'format'=>'pdf']))->out();
        };
        
        $course = $DB->get_record('course', ['id' => $courseid]);
        $progress = \format_certificates\certificate::progress($USER->id, $courseid);
        $urls = [];
        
        // \todo\think The certificate is not shown if not achieved. Maybe find out how to disable 
        // the certificate option instead?
        // \todo\future\hack The certificate style and the requirents to achieve one is hardcoded 
        // in format_certificates to the WINT-Check for now. We show it only for course identified
        // by 'wintcheck'.
        if ($course->idnumber==='wintcheck' && $progress->done===$progress->total) {
            $urls[$url('certificate')] = "Zertifikat";
        }
        
        $urls[$url('report')] = "Ergebnisreport";
        
        return $this->render(new \url_select($urls, '', array('' => 'Bescheinigungen...')));
    }
    
    /**
     * ORCA customized breadcrump navigation.
     * 
     * @return string HTML navbar output.
     */
    public function navbar() {
        $items = $this->page->navbar->get_items();
        // We dont want the first dashboard/website item in the breadcrumps.
        array_shift($items);
        
        $breadcrumbs = [];
        foreach ($items as $item) {
            switch ($item->type) {
                case \navigation_node::TYPE_ROOTNODE:
                    // Hide first "Course" item.
                    continue 2;
                    
                // INFO I commented these lines below because are override 2 categories (oka, orsa) with wrong URLS
                // case \navigation_node::TYPE_CATEGORY:
                //     $cat = \core_course_category::get($item->action->get_param('categoryid'));
                //     if (array_key_exists($cat->idnumber, CATEGORY_URLS)) {
                //         $item->action = new moodle_url(CATEGORY_URLS[$cat->idnumber]);
                //     }
            }
            $breadcrumbs[] = $this->render($item);
        }

        // generate breadcrumbs
        $divider = '<span class="divider">&nbsp/&nbsp</span>';
        $list_items = '<li>'. join($divider .'</li><li>', $breadcrumbs) .'</li>';

        // first breadcrumb
        $first = '<li><span itemscope="" itemtype="http://data-vocabulary.org/Breadcrumb"><a itemprop="url" href="/moodle"><span itemprop="title">Start</span></a></span></li>';
        $list_items = $first . $divider . $list_items;

        return "<ul class=\"breadcrumb\">$list_items</ul>";
    }
    
    // \todo Docstring.
    public function course_header() {
        global $CFG;
        $cid = $this->page->course->id;
        
        // \todo\think This makes the theme fully dependent on format_certificates and will not 
        // work without it.
        if(file_exists($CFG->dirroot . '/course/format/certificates/version.php')){
            if (\format_certificates\certificate::is_certificate_course($cid)) {
                return parent::course_header() . $this->certificate_component($cid);
            }
            else {
                return parent::course_header();
            }
        }else{
            return parent::course_header();
        }
    }

}
