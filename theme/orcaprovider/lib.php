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

/**
 * Theme functions.
 *
 * @package    theme_orcaprovider
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
use enrol_lti\helper;
use enrol_lti\local\ltiadvantage\entity\application_registration;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\tool_launch_service;
use Packback\Lti1p3\LtiMessageLaunch;
/**
 * Post process the CSS tree.
 *
 * @param string $tree The CSS tree.
 * @param theme_config $theme The theme config object.
 */
function theme_orcaprovider_css_tree_post_processor($tree, $theme) {
    $prefixer = new theme_orcaprovider\autoprefixer($tree);
    $prefixer->prefix();
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_orcaprovider_get_extra_scss($theme) {
    $content = '';
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');

    // Sets the background image, and its settings.
    if (!empty($imageurl)) {
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl'); background-size: cover;";
        $content .= ' }';
    }

    // Always return the background image with the scss when we have it.
    return !empty($theme->settings->scss) ? $theme->settings->scss . ' ' . $content : $content;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_orcaprovider_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage')) {
        $theme = theme_config::load('orcaprovider');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_orcaprovider_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/orcaprovider/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/orcaprovider/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_orcaprovider', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - maybe new installs etc.
        $scss .= file_get_contents($CFG->dirroot . '/theme/orcaprovider/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get compiled css.
 *
 * @return string compiled css
 */
function theme_orcaprovider_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/orcaprovider/style/moodle.css');
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_orcaprovider_get_pre_scss($theme) {
    global $CFG;

    $scss = '';
    $configurable = [
        // Config key => [variableName, ...].
        'brandcolor' => ['primary'],
    ];

    // Prepend variables first.
    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    // Prepend pre-scss.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

 /**
     * Generates $ncourses courses
     *
     * @param  int $ncourses The number of courses to be generated.
     * @param  array $params Course params
     * @return null
     */function generate_courses($ncourses, array $params = []) {

        $params = $params + [
            'startdate' => mktime(0, 0, 0, 10, 24, 2022),
            'enddate' => mktime(0, 0, 0, 2, 24, 2023),
        ];

        for ($i = 0; $i < $ncourses; $i++) {
            $name = 'Moodle_' . random_string(10);
            $courseparams = array('shortname' => $name, 'fullname' => $name) + $params;
            orca_create_course($courseparams);
        }
    }

     /**
     * Create a test course
     * @param array|stdClass $record
     * @param array $options with keys:
     *      'createsections'=>bool precreate all sections
     * @return stdClass course record
     */
    function orca_create_course($record=null, array $options=null) {
        global $DB, $CFG;
        require_once("$CFG->dirroot/course/lib.php");

      
        $i = 0;
       $enrolinstances = $DB->get_records_sql('SELECT tool.id AS toolid FROM mdl_course
       JOIN {enrol} as enrol ON enrol.courseid = mdl_course.id
       JOIN {enrol_lti_tools} AS tool ON tool.enrolid = enrol.id WHERE mdl_course.id=enrol.courseid AND enrol.name = "Test LTI"');
       
       if(!empty($enrolinstances)){
           foreach($enrolinstances as $instance){
               $studiportexists = $DB->get_record_sql('SELECT * FROM studiport_lti_mdl WHERE toolid = ? AND platform="moodle"',array($instance->toolid));
               if(!$studiportexists){
               $todb = new stdClass();
               $todb->platform = 'moodle';
               $todb->active = 1;
               $todb->category = 0;
               $todb->description = '';
               $todb->toolid = $instance->toolid;
               $DB->execute("INSERT INTO studiport_lti_mdl SET category=0, description='', toolid=?, platform='moodle', active=1", array($instance->toolid));
               }
           }
       }

        $record = (array)$record;

        if (!isset($record['fullname'])) {
            $record['fullname'] = 'Test course '.$i;
        }

        if (!isset($record['shortname'])) {
            $record['shortname'] = 'tc_'.$i;
        }

        if (!isset($record['idnumber'])) {
            $record['idnumber'] = '';
        }

        if (!isset($record['format'])) {
            $record['format'] = 'topics';
        }

        if (!isset($record['newsitems'])) {
            $record['newsitems'] = 0;
        }

        if (!isset($record['numsections'])) {
            $record['numsections'] = 5;
        }

        if (!isset($record['summary'])) {
            $record['summary'] = "Test course";
        }

        if (!isset($record['summaryformat'])) {
            $record['summaryformat'] = FORMAT_MOODLE;
        }

        if (!isset($record['category'])) {
            $record['category'] = $DB->get_field_select('course_categories', "MIN(id)", "parent=0");
        }

        if (!isset($record['startdate'])) {
            $record['startdate'] = time();
        }

        if (isset($record['tags']) && !is_array($record['tags'])) {
            $record['tags'] = preg_split('/\s*,\s*/', trim($record['tags']), -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!empty($options['createsections']) && empty($record['numsections'])) {
            // Since Moodle 3.3 function create_course() automatically creates sections if numsections is specified.
            // For BC if 'createsections' is given but 'numsections' is not, assume the default value from config.
            $record['numsections'] = get_config('moodlecourse', 'numsections');
        }

        $course = create_course((object)$record);
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/auth/lti/auth.php');

        $enableauthplugin = true;
        $enableenrolplugin = true;
        $membersync = true;
        $membersyncmode = helper::MEMBER_SYNC_ENROL_AND_UNENROL;
        $gradesync = true;
        $gradesynccompletion = false;
        $enrolstartdate = 0;
        $provisioningmodeinstructor = 0;
        $provisioningmodelearner = 0;
      

    

        // Create a module and publish it.
      
        $tooldata = [
            'courseid' => $course->id,
            'membersyncmode' => $membersyncmode,
            'membersync' => $membersync,
            'gradesync' => $gradesync,
            'gradesynccompletion' => $gradesynccompletion,
            'ltiversion' => 'LTI-1p3',
            'enrolstartdate' => $enrolstartdate,
            'provisioningmodeinstructor' => 2,
            'provisioningmodelearner' =>1
        ];
        $tool = orca_create_lti_tool((object)$tooldata);


        context_course::instance($course->id);

        return $course;
    }

    /**
     * Helper function used to create an LTI tool.
     *
     * @param array $data
     * @return stdClass the tool
     */
    function orca_create_lti_tool($data = array()) {
        global $DB;

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));

        // Create a course if no course id was specified.
        if (empty($data->courseid)) {
            $course = $this->create_course();
            $data->courseid = $course->id;
        } else {
            $course = get_course($data->courseid);
        }

        if (!empty($data->cmid)) {
            $data->contextid = context_module::instance($data->cmid)->id;
        } else {
            $data->contextid = context_course::instance($data->courseid)->id;
        }

        // Set it to enabled if no status was specified.
        if (!isset($data->status)) {
            $data->status = ENROL_INSTANCE_ENABLED;
        }

        // Default to legacy lti version.
        if (empty($data->ltiversion) || !in_array($data->ltiversion, ['LTI-1p0/LTI-2p0', 'LTI-1p3'])) {
            $data->ltiversion = 'LTI-1p0/LTI-2p0';
        }

        // Add some extra necessary fields to the data.
        $data->name = $data->name ?? 'Test LTI';
        $data->roleinstructor = $teacherrole->id;
        $data->rolelearner = $studentrole->id;
        $data->secret =  random_string(32);

        // Get the enrol LTI plugin.
        $enrolplugin = enrol_get_plugin('lti');
        $instanceid = $enrolplugin->add_instance($course, (array) $data);

        // Get the tool associated with this instance.
        return $DB->get_record('enrol_lti_tools', array('enrolid' => $instanceid));
    }

function theme_orcaprovider_check_access(){
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";  
    $CurPageURL = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];  
    if (strpos($CurPageURL,'/user/') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/my') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/grade/report/overview/index.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/login/change_password.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/message/edit.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/message/notificationpreferences.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/badges/preferences.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/badges/mybadges.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,' /badges/mybackpack.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/blog/preferences.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/blog/external_blogs.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/blog/external_blog_edit.php') !== false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }else if (strpos($CurPageURL,'/course') !== false && strpos($CurPageURL,'/view') == false) {
        if (!has_capability('moodle/site:config', context_system::instance())) {
            print_error('noaccess','data');
        }
    }
    
}
