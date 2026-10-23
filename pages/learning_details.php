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
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     Céline Hernandez <celine@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/report/ed_kpi/classes/data_interface.php');

// Params.
$type         = required_param('type', PARAM_TEXT); // Course, program, certification...
$id           = required_param('id', PARAM_INT);
$departmentid = optional_param('departmentid', -1, PARAM_INT);

// Perform security checks to ensure the user is logged in and has the appropriate capability to view the KPI report,
// either at the course level or at the system level.
require_login();
if ($type === 'course' && !empty($id)) {
    $context = context_course::instance($id);
    require_capability('report/ed_kpi:viewcoursekpi', $context);
} else {
    $context = context_system::instance();
    require_capability('report/ed_kpi:view', $context);
}

// Get data depending on type.
if ($type == 'course') {
    $learning       = get_course($id);
    $learning->code = $learning->shortname;
}

// Init page.
$pageurl = '/report/ed_kpi/pages/learning_details.php?type=' . $type . '&id=' . $id;
if ($departmentid !== -1) {
    $pageurl = '/report/ed_kpi/pages/learning_details.php?type=' . $type . '&id=' . $id . '&departmentid=' . $departmentid;
}
$returnlink = $CFG->wwwroot . '/report/ed_kpi/index.php';
if ($departmentid !== -1) {
    $returnlink .= '?departmentid=' . $departmentid;
}
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$learningname = strip_tags(format_text($learning->fullname));
$pagetitle = get_string('pluginname', 'report_ed_kpi') . ' - ' . $learningname;
$PAGE->set_heading($pagetitle);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'report_ed_kpi'), new moodle_url($returnlink));
$PAGE->navbar->add($learningname, new moodle_url($pageurl));
/* START edunao_chantiers_internes-248 */
$PAGE->requires->css('/report/ed_kpi/css/jquery.dataTables.css');
/* END edunao_chantiers_internes-248 */

// Get general data.
$datainterface = new \report_ed_kpi\data_interface();
$renderer      = $PAGE->get_renderer('report_ed_kpi');

// Get workplace data.
$alldepartmentselected = [];
$departments           = [];
$mydepartments         = [];
$tenantid              = null;
if (report_ed_kpi\data_interface::is_workplace()) {
    $tenantid              = is_siteadmin() ? null : $datainterface->get_user_tenantid();
    $departments           = $datainterface->get_used_departments(null, $tenantid);
    $mydepartments         = array_keys($datainterface->get_departments_of_current_user(
            true, \tool_organisation\organisation::PERM_VIEW_REPORTS, true
    ));
    if (count($mydepartments) > 0) {
        $departmentid = $departmentid === -1 || !in_array($departmentid, $mydepartments) ? end($mydepartments) : $departmentid;
    } else if (!is_siteadmin()) {
        $departmentid = -1;
    }
    $alldepartmentselected = $datainterface->get_department_childrenids_list($departmentid);
}

// A terme, on aura des types programmes ou certification avec le même affichage mais des data différentes.
if ($type == 'course') {
    // Récupération des data du cours.
    $learningdata        = $datainterface->get_course_enrolments($learning, $alldepartmentselected, $tenantid);
    $aggregatedata       = $datainterface->get_course_aggregated_data($learningdata);
    $monthaggregateddata = $datainterface->get_course_aggregated_data_by_month($learningdata);
} else {
    throw new moodle_exception('unrecognizedlearningtype', 'report_ed_kpi');
}

// Output page.
echo $OUTPUT->header();

// Bandeau d'alerte s'il n'y a pas de data.
if (!$learningdata) {
    echo '<div class="alert alert-danger" role="alert">' . get_string('nodata', 'report_ed_kpi') . '</div>';
}

// Render summary infos.
$filters = $renderer->render_filter([
        'id'   => $id,
        'type' => $type,
], $departments, $mydepartments, $departmentid);
echo $renderer->render_learning_summary_global_info($aggregatedata, $filters);

// Render graphs.
echo '<div class="charts-container">';
echo $renderer->render_learning_circle_progression_chart($aggregatedata, get_string('learning-circlechart-title', 'report_ed_kpi'),
        get_string('learning-circlechart-subtitle', 'report_ed_kpi'));
echo $renderer->render_learning_enrol_chart($monthaggregateddata, get_string('learning-barchart-title', 'report_ed_kpi'),
        get_string('learning-barchart-subtitle', 'report_ed_kpi'));
echo '</div>';

// Render table.
echo $renderer->render_learning_summary_table($id, $learningdata);

// Render a return link.
echo '<a class="return-link" href="' . $returnlink . '">' . get_string('returntoall', 'report_ed_kpi') . '</a>';

// Include datatables.
$tablecsvfilename = 'Learning_KPIs-' . $learning->code;
$PAGE->requires->js_call_amd('report_ed_kpi/main', 'init_details', [
        'lang'             => current_language(),
        'tablecsvfilename' => $tablecsvfilename,
]);

// Include filter.
$PAGE->requires->js_call_amd('report_ed_kpi/main', 'init_filters');

echo $OUTPUT->footer();
