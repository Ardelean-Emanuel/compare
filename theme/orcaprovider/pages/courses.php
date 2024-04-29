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

use core\analytics\indicator\read_actions;

require_once(__DIR__ . '../../../../config.php');
require_once(__DIR__ . '../../lib.php');
require_once(__DIR__ . '../../../../course/lib.php');
global $THEME, $USER;

require_login();
if(!is_siteadmin()){
    $PAGE->set_context(context_system::instance());
    echo $OUTPUT->header();
    echo $OUTPUT->box('User has no access.', 'notifyproblem');
    echo $OUTPUT->footer();
    die();
}

$url = new moodle_url('/theme/orcaprovider/pages/courses.php');
$PAGE->set_url($url);


$context = context_system::instance();

$PAGE->set_context($context);

$title = 'Generate courses';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');
// $PAGE->add_body_classes(['full-width']);
$PAGE->navbar->ignore_active();
$PAGE->navbar->add($title, $url);

$output = $PAGE->get_renderer('core', 'course');
generate_courses(500);
echo $OUTPUT->header();


echo $OUTPUT->footer();
