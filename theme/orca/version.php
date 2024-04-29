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
 * Boost.
 *
 * @package    theme_orca
 * @copyright  2021 metromorph softworks GmbH <devel@metromorph.de>
 * @author     Nikolas List
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
global $CFG;
$plugin->version   = 2022071500;
$plugin->requires = 2017051500;
$plugin->release = 'v0.17.6';
$plugin->component = 'theme_orca';
if(file_exists($CFG->dirroot . '/course/format/certificates/version.php')){
$plugin->dependencies = [
    'format_certificates' => 2021082048
];
}