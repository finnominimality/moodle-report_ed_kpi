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
 *
 *
 * @package    report_ed_kpi
 * @copyright  2024 Edunao SAS (contact@edunao.com)
 * @author     Mylène Sevilla <mylene.sevilla@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_ed_kpi_extend_navigation_course($navigation, $course, $context) {
    // Check if the user has the capability to view the course KPI report.
    $showonnavigation = has_capability('report/ed_kpi:viewcoursekpi', $context);

    // If the user has the capability, add the KPI report link to the navigation.
    if ($showonnavigation) {
        // Define the URL for the KPI report page, passing the course ID as a parameter.
        $url = new moodle_url('/report/ed_kpi/pages/learning_details.php', ['type' => 'course', 'id' => $course->id]);

        // Add the link to the course navigation.
        $navigation->add(
            get_string('pluginname', 'report_ed_kpi'),
            $url, navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}
