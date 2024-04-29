<?php

include_once($CFG->dirroot . "/course/renderer.php");

class theme_orca_core_course_renderer extends core_course_renderer {
    public function course_section_cm_list_item($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array()) {
        if($this->page->user_is_editing() || !in_array($course->format, ["topics", "certificates"])) {
            return parent::course_section_cm_list_item($course, $completioninfo, $mod, $sectionreturn, $displayoptions);
        }

        if (!$mod->is_visible_on_course_page()) {
            return '';
        }

        $rendertemplate = "theme_orca/course_section_cm_list_item";

        if($mod->sectionnum == 0) {
            $rendertemplate = "theme_orca/coursecat_toplevel_course";
        }

        return $this->output->render_from_template(
            $rendertemplate,
            array(
                "title" => $mod->get_formatted_name(),
                "url" => $mod->url
            )
        );
    }

    protected function coursecat_category(coursecat_helper $chelper, $coursecat, $depth) {
        if($this->page->user_is_editing()) {
            return parent::coursecat_category($chelper, $coursecat, $depth);
        }

        $rendertemplate = "theme_orca/course_section_cm_list_item";
        $renderdata = array(
            "title" => $coursecat->get_formatted_name(),
            "url" => new moodle_url('/course/index.php', array('categoryid' => $coursecat->id)),
            "categoryid" => $coursecat->id,
            "depth" => $depth,
            "showcourses" => $chelper->get_show_courses(),
            "type" => self::COURSECAT_TYPE_CATEGORY,
        );

        if($depth <= 1) {
            $classes = array();

            if (empty($coursecat->visible)) {
                $classes[] = 'dimmed_category';
            }

            if ($chelper->get_subcat_depth() > 0 && $depth >= $chelper->get_subcat_depth()) {
                $categorycontent = "";
                $classes[] = "notloaded";
                if ($coursecat->get_children_count() ||
                    ($chelper->get_show_courses() >= self::COURSECAT_SHOW_COURSES_COLLAPSED && $coursecat->get_courses_count())) {
                    $classes[] = "with_children";
                    $classes[] = "collapsed";
                }
            } else {
                $categorycontent = $this->coursecat_category_content($chelper, $coursecat, $depth);
                $classes[] = "loaded";
                if (!empty($categorycontent)) {
                    $classes[] = "with_children";
                    $this->categoryexpandedonload = true;
                }
            }

            // Make sure JS file to expand category content is included.
            $this->coursecat_include_js();

            $rendertemplate = "theme_orca/coursecat_category";

            $renderdata["classes"] = join(" ", $classes);
            $renderdata["content"] = $categorycontent;
        }

        return $this->output->render_from_template(
            $rendertemplate,
            $renderdata
        );
    }

    protected function coursecat_coursebox(coursecat_helper $chelper, $course, $additionalclasses = '') {
        if($this->page->user_is_editing()) {
            return parent::coursecat_coursebox($chelper, $course, $additionalclasses);
        }

        $renderdata = array(
            "title" => $chelper->get_course_formatted_name($course),
            "url" => new moodle_url('/course/view.php', ['id' => $course->id]),
            "courseid" => $course->id,
            "type" => self::COURSECAT_TYPE_COURSE
        );

        if ($this->page->pagetype === "course-category.ajax") {
            $rendertemplate = "theme_orca/course_section_cm_list_item";
        }
        else {
            $rendertemplate = "theme_orca/coursecat_toplevel_course";
        }

        return $this->output->render_from_template(
            $rendertemplate,
            $renderdata
        );
    }

    public function course_search_form($value = '', $format = 'plain') {
        //CAVE: to remove search form
        return "";
    }

    protected function coursecat_tree(coursecat_helper $chelper, $coursecat) {
        // Reset the category expanded flag for this course category tree first.
        $this->categoryexpandedonload = false;
        $categorycontent = $this->coursecat_category_content($chelper, $coursecat, 0);
        if (empty($categorycontent)) {
            return '';
        }

        // Start content generation
        $content = '';
        $attributes = $chelper->get_and_erase_attributes('course_category_tree clearfix');
        $content .= html_writer::start_tag('div', $attributes);

        //CAVE: to remove collapse all link
        //if ($coursecat->get_children_count()) {
        //    $classes = array(
        //        'collapseexpand', 'aabtn'
        //    );

        //    // Check if the category content contains subcategories with children's content loaded.
        //    if ($this->categoryexpandedonload) {
        //        $classes[] = 'collapse-all';
        //        $linkname = get_string('collapseall');
        //    } else {
        //        $linkname = get_string('expandall');
        //    }

        //    // Only show the collapse/expand if there are children to expand.
        //    $content .= html_writer::start_tag('div', array('class' => 'collapsible-actions'));
        //    $content .= html_writer::link('#', $linkname, array('class' => implode(' ', $classes)));
        //    $content .= html_writer::end_tag('div');
        //    $this->page->requires->strings_for_js(array('collapseall', 'expandall'), 'moodle');
        //}

        $content .= html_writer::tag('div', $categorycontent, array('class' => 'content'));

        $content .= html_writer::end_tag('div'); // .course_category_tree

        return $content;
    }

    public function course_category($category) {
        global $CFG;
        $usertop = core_course_category::user_top();
        if (empty($category)) {
            $coursecat = $usertop;
        } else if (is_object($category) && $category instanceof core_course_category) {
            $coursecat = $category;
        } else {
            $coursecat = core_course_category::get(is_object($category) ? $category->id : $category);
        }
        $site = get_site();
        $output = '';

        if ($coursecat->can_create_course() || $coursecat->has_manage_capability()) {
            // Add 'Manage' button if user has permissions to edit this category.
            $managebutton = $this->single_button(new moodle_url('/course/management.php',
                array('categoryid' => $coursecat->id)), get_string('managecourses'), 'get');
            $this->page->set_button($managebutton);
        }

        if (core_course_category::is_simple_site()) {
            // There is only one category in the system, do not display link to it.
            $strfulllistofcourses = get_string('fulllistofcourses');
            $this->page->set_title("$site->shortname: $strfulllistofcourses");
        } else if (!$coursecat->id || !$coursecat->is_uservisible()) {
            $strcategories = get_string('categories');
            $this->page->set_title("$site->shortname: $strcategories");
        } else {
            $strfulllistofcourses = get_string('fulllistofcourses');
            $this->page->set_title("$site->shortname: $strfulllistofcourses");

            //CAVE: to remove category selector
            // Print the category selector
            //$categorieslist = core_course_category::make_categories_list();
            //if (count($categorieslist) > 1) {
            //    $output .= html_writer::start_tag('div', array('class' => 'categorypicker'));
            //    $select = new single_select(new moodle_url('/course/index.php'), 'categoryid',
            //            core_course_category::make_categories_list(), $coursecat->id, null, 'switchcategory');
            //    $select->set_label(get_string('categories').':');
            //    $output .= $this->render($select);
            //    $output .= html_writer::end_tag('div'); // .categorypicker
            //}
        }

        // Print current category description
        $chelper = new coursecat_helper();
        if ($description = $chelper->get_category_formatted_description($coursecat)) {
            $output .= $this->box($description, array('class' => 'generalbox info'));
        }

        // Prepare parameters for courses and categories lists in the tree
        $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_AUTO)
                ->set_attributes(array('class' => 'category-browse category-browse-'.$coursecat->id));

        $coursedisplayoptions = array();
        $catdisplayoptions = array();
        $browse = optional_param('browse', null, PARAM_ALPHA);
        $perpage = optional_param('perpage', $CFG->coursesperpage, PARAM_INT);
        $page = optional_param('page', 0, PARAM_INT);
        $baseurl = new moodle_url('/course/index.php');
        if ($coursecat->id) {
            $baseurl->param('categoryid', $coursecat->id);
        }
        if ($perpage != $CFG->coursesperpage) {
            $baseurl->param('perpage', $perpage);
        }
        $coursedisplayoptions['limit'] = $perpage;
        $catdisplayoptions['limit'] = $perpage;
        if ($browse === 'courses' || !$coursecat->get_children_count()) {
            $coursedisplayoptions['offset'] = $page * $perpage;
            $coursedisplayoptions['paginationurl'] = new moodle_url($baseurl, array('browse' => 'courses'));
            $catdisplayoptions['nodisplay'] = true;
            $catdisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'categories'));
            $catdisplayoptions['viewmoretext'] = new lang_string('viewallsubcategories');
        } else if ($browse === 'categories' || !$coursecat->get_courses_count()) {
            $coursedisplayoptions['nodisplay'] = true;
            $catdisplayoptions['offset'] = $page * $perpage;
            $catdisplayoptions['paginationurl'] = new moodle_url($baseurl, array('browse' => 'categories'));
            $coursedisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'courses'));
            $coursedisplayoptions['viewmoretext'] = new lang_string('viewallcourses');
        } else {
            // we have a category that has both subcategories and courses, display pagination separately
            $coursedisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'courses', 'page' => 1));
            $catdisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'categories', 'page' => 1));
        }
        $chelper->set_courses_display_options($coursedisplayoptions)->set_categories_display_options($catdisplayoptions);
        // Add course search form.
        $output .= $this->course_search_form();

        // Display course category tree.
        $output .= $this->coursecat_tree($chelper, $coursecat);

        // Add action buttons
        $output .= $this->container_start('buttons');
        if ($coursecat->is_uservisible()) {
            $context = get_category_or_system_context($coursecat->id);
            if (has_capability('moodle/course:create', $context)) {
                // Print link to create a new course, for the 1st available category.
                if ($coursecat->id) {
                    $url = new moodle_url('/course/edit.php', array('category' => $coursecat->id, 'returnto' => 'category'));
                } else {
                    $url = new moodle_url('/course/edit.php',
                        array('category' => $CFG->defaultrequestcategory, 'returnto' => 'topcat'));
                }
                $output .= $this->single_button($url, get_string('addnewcourse'), 'get');
            }
            ob_start();
            print_course_request_buttons($context);
            $output .= ob_get_contents();
            ob_end_clean();
        }
        $output .= $this->container_end();

        return $output;
    }

    protected function frontpage_part($skipdivid, $contentsdivid, $header, $contents) {
        if (strval($contents) === '') {
            return '';
        }
        $output = html_writer::link('#' . $skipdivid,
            get_string('skipa', 'access', core_text::strtolower(strip_tags($header))),
            array('class' => 'skip-block skip aabtn'));

        // Wrap frontpage part in div container.
        $output .= html_writer::start_tag('div', array('id' => $contentsdivid));

        //CAVE: to remove the header "Available Courses"
        //$output .= $this->heading($header);

        $output .= $contents;

        // End frontpage part div container.
        $output .= html_writer::end_tag('div');

        $output .= html_writer::tag('span', '', array('class' => 'skip-block-to', 'id' => $skipdivid));
        return $output;
    }
}
