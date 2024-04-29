<?php

include_once($CFG->dirroot . "/course/format/topics/renderer.php");

class theme_orca_format_topics_renderer extends format_topics_renderer {
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        if($this->page->user_is_editing()) {
            return parent::section_header($section, $course, $onsectionpage, $sectionreturn);
        }
        
        // \todo\future Hint hidden sections to admin users with a special style. For now admin
        // users can see the difference only if editing is on.
        
        $classes = "";
        if (!$section->uservisible && !$section->visible) {
            $classes .= " hidden";
        }

        $hide_title = $section->section == 0 && empty($section->name);
        if(!$hide_title) {
            $classes .= " with_children collapsed";
        
        }
        
        $summary = $this->format_summary_text($section);
        
        // This is a dirty hack, but after editing a category summary it is not empty anymore and 
        // gets at least the following html assigned by Moodle, which we simply consider empty.
        $summary_is_empty = empty($summary) ||
            $summary === '<div class="no-overflow"><p dir="ltr" style="text-align: left;"><br></p></div>' ||
            $summary === '<div class="no-overflow"><br></div>';

        return $this->output->render_from_template(
            "theme_orca/course_section_header",
            array(
                "title" => $this->section_title_without_link($section, $course),
                "hide_title" => $hide_title,
                "url" => course_get_url($section->course, $section->section, array('navigation' => true)),
                "classes" => $classes,
                "as_header" => $summary_is_empty,
                "content" => $summary,
                "id" => "section-".$section->section,
                "role" => "region",
                "labelledby" => "sectionid-{$section->id}-title",
                "sectionid" => $section->section,
                "sectionreturnid" => $sectionreturn
            )
        );
    }
}
