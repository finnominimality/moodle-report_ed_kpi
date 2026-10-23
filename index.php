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

require_once('../../config.php');
require_once($CFG->dirroot . '/report/ed_kpi/classes/data_interface.php');

// Params.
$departmentid = optional_param('departmentid', -1, PARAM_INT);

// Security checks.
require_login();
$context = context_system::instance();
require_capability('report/ed_kpi:view', $context);

// Init page.
$pagetitle = get_string('pluginname', 'report_ed_kpi');
$PAGE->set_url('/report/ed_kpi/index.php');
$PAGE->set_context($context);
$PAGE->set_heading($pagetitle);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add($pagetitle, new moodle_url('/report/ed_kpi/index.php'));
/* START edunao_chantiers_internes-248 */
$PAGE->requires->css('/report/ed_kpi/css/jquery.dataTables.css');
/* END edunao_chantiers_internes-248 */

// Get general data.
$renderer      = $PAGE->get_renderer('report_ed_kpi');
$datainterface = new \report_ed_kpi\data_interface();

// Get workplace data.
$alldepartmentselected = [];
$departments           = [];
$mydepartments         = [];
$tenantid              = null;
if (report_ed_kpi\data_interface::is_workplace()) {
    $tenantid      = is_siteadmin() ? null : $datainterface->get_user_tenantid();
    $departments   = $datainterface->get_used_departments(null, $tenantid);
    $mydepartments = array_keys($datainterface->get_departments_of_current_user(
            true, \tool_organisation\organisation::PERM_VIEW_REPORTS, true
    ));
    if (count($mydepartments) > 0) {
        $departmentid = $departmentid === -1 || !in_array($departmentid, $mydepartments) ? end($mydepartments) : $departmentid;
    } else if (!is_siteadmin()) {
        $departmentid = -1;
    }
    $alldepartmentselected = $datainterface->get_department_childrenids_list($departmentid);
}

// Récupération des data en fonction des filtres.
$alldata             = $datainterface->get_all_learning_data($alldepartmentselected, $tenantid);
$aggregateddata      = $datainterface->get_learnings_aggregated_data($alldata);
$aggregatedmonthdata = $datainterface->get_learnings_aggregated_month_data($alldepartmentselected, $tenantid);

// Output page.
echo $OUTPUT->header();

// Bandeau d'alerte s'il n'y a pas de data.
if (!$alldata) {
    echo '<div class="alert alert-danger" role="alert">' . get_string('nodata', 'report_ed_kpi') . '</div>';
}

$filters = $renderer->render_filter([], $departments, $mydepartments, $departmentid);

echo $renderer->render_learning_summary_global_info($aggregateddata, $filters);

echo '<div class="charts-container">';
echo $renderer->render_learnings_participation_chart($alldata, get_string('learnings-circlechart-title', 'report_ed_kpi'),
        get_string('learnings-circlechart-subtitle', 'report_ed_kpi'));
echo $renderer->render_learning_enrol_chart($aggregatedmonthdata, get_string('learning-barchart-title', 'report_ed_kpi'),
        get_string('learning-barchart-subtitle', 'report_ed_kpi'));
echo '</div>';

echo $renderer->render_learnings_summary_table($alldata, $departmentid);

// Include datatables.
$tablecsvfilename = 'Learning_KPIs-All';
$PAGE->requires->js_call_amd('report_ed_kpi/main', 'init_index', [
        'lang'             => current_language(),
        'tablecsvfilename' => $tablecsvfilename,
]);

// Include filter.
$PAGE->requires->js_call_amd('report_ed_kpi/main', 'init_filters');

echo $OUTPUT->footer();
