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
class report_ed_kpi_renderer extends \plugin_renderer_base {

    /**
     * Affichage
     * @param array $globalparams
     * @param array $departments
     * @param array $mydepartments
     * @param int $departmentselected
     * @return string
     * @throws coding_exception
     */
    public function render_filter(
            array $globalparams,
            array $departments = [],
            array $mydepartments = [],
            int $departmentselected = -1
    ) {

        if ((!$departments) || (!is_siteadmin() && !$mydepartments)) {
            // Pour l'instant, il n'y a aucun filtre non workplace.
            return '';
        }

        $output = '';

        $output .= '<div class="filters-container">';
        $output .= '<form id="report-filter-form" action="" method="get">';

        foreach ($globalparams as $name => $value) {
            $output .= '<input type="hidden" name="' . $name . '" value="' . $value . '">';
        }

        $output .= '<div class="filter-container">';

        $output .= '<label class="d-none" for="departmentid">' . get_string('departments', 'report_ed_kpi') . '</label>';
        $output .= '<select class="form-control custom-select " name="departmentid" id="departments">';
        foreach ($departments as $department) {
            // On exclut les départements parents sans enfant car on ne peut pas leur assigner de jobs.
            if (!isset($department->children)) {
                continue;
            }
            $output .= '<optgroup label="' . $department->name . '">';

            foreach ($department->children as $child) {
                $output .= $this->render_filter_option($child, 0, $departmentselected, $mydepartments);
            }
            $output .= '</optgroup>';
        }
        $output .= '</select>';
        $output .= '</div>';
        $output .= '</form>';
        $output .= '</div>';

        return $output;
    }

    /**
     *
     *
     * @param object $child
     * @param int $offset
     * @param array $selected
     * @param array $allowedids
     * @return string
     */
    private function render_filter_option(object $child, int $offset = 0, int $selected = -1, $allowedids = []) {
        $output = '';

        $option = '';
        if ($child->id == $selected) {
            $option = 'selected';
        }

        if (is_siteadmin() || in_array($child->id, $allowedids)) {
            $output .= '<option value="' . $child->id . '" ' . $option . '>';
            for ($i = 0; $i < $offset; $i++) {
                $output .= "&nbsp;";
            }
            $output .= $child->name . '</option>';
            $offset++;
        }

        if (isset($child->children)) {
            asort($child->children);
            foreach ($child->children as $subchild) {
                $output .= $this->render_filter_option($subchild, $offset, $selected, $allowedids);
            }
        }

        return $output;
    }

    /**
     *
     * @param object $aggregateddata
     * @param string $filters
     * @return string
     * @throws coding_exception
     */
    public function render_learning_summary_global_info(object $aggregateddata, string $filters = '') {
        $output = '';

        $output .= '<div class="global-summary">';

        if (!empty($filters)) {
            $output .= $filters;
        } else {
            $output .= '<div class="title"><h4>' . get_string('summary-title', 'report_ed_kpi') . '</h4></div>';
        }

        $output .= '<div class="learning-data">';
        // Enrolments.
        $output .= '<div class="summary-element">
                        <span class="value">' . $aggregateddata->enrolments . '</span>
                        <span class="legend">' . get_string('nbenrolments', 'report_ed_kpi') . '</span>
                    </div>';

        // Completed.
        $output .= '<div class="summary-element">
                        <span class="value">' . $aggregateddata->completed . '</span>
                        <span class="legend">' . get_string('nbcompleted', 'report_ed_kpi') . '</span>
                    </div>';

        // In progress.
        $output .= '<div class="summary-element">
                        <span class="value">' . $aggregateddata->inprogress . '</span>
                        <span class="legend">' . get_string('nbinprogress', 'report_ed_kpi') . '</span>
                    </div>';

        // Not started.
        $output .= '<div class="summary-element">
                        <span class="value">' . $aggregateddata->notstarted . '</span>
                        <span class="legend">' . get_string('nbnotstarted', 'report_ed_kpi') . '</span>
                    </div>';

        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     *
     * @param array $learningsdata
     * @param int $departmentid
     * @return string
     * @throws coding_exception
     */
    public function render_learnings_summary_table(array $learningsdata, int $departmentid = -1) {
        global $CFG;

        $output = '';

        $headers = [
            'code',
            'fullname',
            'nbenrolments',
            'nbcompleted',
            'nbinprogress',
            'nbnotstarted',
        ];

        $output .= '<div class="table-container">';
        $output .= '<h4>' . get_string('learnings-table-title', 'report_ed_kpi') . '</h4>';
        $output .= '<table id="table-all-learnings" class="flexible table table-striped table-hover report-table">';

        $output .= '<thead><tr>';
        foreach ($headers as $header) {
            $output .= '<td>' . get_string('header-' . $header, 'report_ed_kpi') . '</td>';
        }
        $output .= '</tr><thead>';

        $output .= '<tbody>';

        foreach ($learningsdata as $learningdata) {

            $output .= '<tr>';

            foreach ($headers as $header) {
                switch ($header) {
                    case 'code':
                    case 'fullname':
                        $link   = $CFG->wwwroot . '/report/ed_kpi/pages/learning_details.php?type=' . $learningdata->type .
                              '&id=' . $learningdata->id;
                        if ($departmentid !== -1) {
                            $link .= '&departmentid=' . $departmentid;
                        }
                        $output .= '<td><a href="' . $link . '">' . format_text($learningdata->$header) . '</a></td>';
                        break;
                    case 'nbenrolments' :
                    case 'nbcompleted':
                    case 'nbinprogress':
                    case 'nbnotstarted':
                        $param  = substr($header, 2);
                        $output .= '<td>' . $learningdata->$param . '</td>';
                        break;
                    default :
                        $output .= '<td></td>';
                }
            }
            $output .= '</tr>';
        }

        $output .= '</tbody>';

        $output .= '</table>';

        $output .= '</div>';

        return $output;
    }

    /**
     *
     * @param $learningid
     * @param array $learningdata
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function render_learning_summary_table($learningid, array $learningdata) {
        global $CFG;

        $output = '';

        $identityfields = explode(',', get_config('core', 'showuseridentity'));
        $headers        = [
            'firstname',
            'lastname',
            'status',
            'enrolmentdate',
            'lastaccess',
            'completiondate',
            'grade',
        ];

        if (\report_ed_kpi\data_interface::edtime_actived()) {
            $headers[] = 'time';
        }

        $output .= '<div class="table-container">';
        $output .= '<h4>' . get_string('users-table-title', 'report_ed_kpi') . '</h4>';
        $output .= '<table id="table-learning-details" class="flexible table table-striped table-hover report-table">';

        $output .= '<thead><tr>';
        foreach ($identityfields as $field) {
            $output .= '<td>' . get_string($field, 'core') . '</td>';
        }
        foreach ($headers as $header) {
            $output .= '<td>' . get_string('header-' . $header, 'report_ed_kpi') . '</td>';
        }
        $output .= '</tr><thead>';

        $output .= '<tbody>';

        $users = user_get_users_by_id(array_keys($learningdata));

        foreach ($learningdata as $userid => $userdata) {
            $user   = $users[$userid];
            $output .= '<tr>';

            foreach ($identityfields as $field) {
                $output .= '<td>' . $user->$field . '</td>';
            }

            foreach ($headers as $header) {

                switch ($header) {
                    case 'firstname':
                    case 'lastname':
                        $profillink = $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&course=' . $learningid;
                        $output     .= '<td><a href="' . $profillink . '">' . $user->$header . '</a></td>';
                        break;
                    case 'status' :
                        if ($userdata->completionstatus == COMPLETION_COMPLETE) {
                            $output .= '<td>' . get_string('completed', 'core_completion') . '</td>';
                        } else if ($userdata->completionstatus == COMPLETION_INCOMPLETE) {
                            $output .= '<td>' . get_string('completion-notstarted', 'report_ed_kpi') . '</td>';
                        } else {
                            $output .= '<td>' . get_string('completion-inprogress', 'report_ed_kpi') . '</td>';
                        }

                        break;
                    case 'enrolmentdate' :
                    case 'lastaccess':
                    case 'completiondate':
                        $output .= '<td>';
                        if ($userdata->$header != 0) {
                            $output .= $this->userdate($userdata->$header);
                        }
                        $output .= '</td>';
                        break;
                    case 'grade':
                        $output .= '<td>';
                        if ($userdata->$header !== false && $userdata->$header !== null) {
                            $output .= round($userdata->$header, 2);
                        }
                        $output .= '</td>';
                        break;
                    case 'time':
                        $output .= '<td>';
                        if ($userdata->$header != 0) {
                            $output .= $this->render_edtime_time($userdata->$header);
                        }
                        $output .= '</td>';
                        break;
                    default :
                        $output .= '<td></td>';
                }

            }

            $output .= '</tr>';
        }

        $output .= '</tbody>';

        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }

    /**
     *
     * @param $learningsdata
     * @param $title
     * @param $subtitle
     * @return string
     * @throws coding_exception
     */
    public function render_learnings_participation_chart($learningsdata, $title, $subtitle) {

        $output = '';

        $output .= '<div class="chart-container">';
        $output .= '<h4>' . $title . '</h4>';
        $output .= '<span class="legend">' . $subtitle . '</span>';

        $output .= '<canvas id="learning-data-circle" width="400" height="400"></canvas>';
        $output .= '</div>';

        $coursesnbenrolments = [];
        $coursesfullname     = [];
        foreach ($learningsdata as $coursedata) {

            if ($coursedata->enrolments == 0) {
                continue;
            }

            $coursesnbenrolments[$coursedata->id] = $coursedata->enrolments;
            $coursesfullname[$coursedata->id]     = strip_tags(format_text($coursedata->fullname));
        }

        arsort($coursesnbenrolments);

        $labels = [];

        $data = [];

        $backgroundcolor = [
            '#52b456',
            '#fe9c00',
            '#eb2269',
            '#269bf4',
            '#a12db5',
            '#009c8e',
            '#fe5d27',
            '#00c0d7',
            '#7f5b4d',
            '#a3a3a3',
        ];

        $othercourses = 0;
        foreach ($coursesnbenrolments as $courseid => $nbenrolmens) {

            // Max 10 courses, after that, merge all course in "Other courses".
            if (count($labels) >= 9) {
                $othercourses += $nbenrolmens;
                continue;
            }

            $labels[] = $coursesfullname[$courseid];
            $data[]   = $nbenrolmens;

        }
        if ($othercourses > 0) {
            $labels[] = get_string('othercourses', 'report_ed_kpi');
            $data[]   = $othercourses;
        }

        $jsdata = [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => $title,
                    'data'            => $data,
                    'backgroundColor' => $backgroundcolor,
                ],
            ],
        ];

        $this->page->requires->js_call_amd('report_ed_kpi/main', 'init_circle_chart', [
            'id'   => 'learning-data-circle',
            'data' => $jsdata,
        ]);

        return $output;
    }

    /**
     *
     * @param $learningdata
     * @param $title
     * @param $subtitle
     * @return string
     * @throws coding_exception
     */
    public function render_learning_circle_progression_chart($learningdata, $title, $subtitle) {

        $output = '';

        $output .= '<div class="chart-container">';
        $output .= '<h4>' . $title . '</h4>';
        $output .= '<span class="legend">' . $subtitle . '</span>';

        $output .= '<canvas id="learning-data-circle" width="400" height="400"></canvas>';
        $output .= '</div>';

        $percentnotstarted = round($learningdata->enrolments != 0 ?
            $learningdata->notstarted * 100 / $learningdata->enrolments : 0, 2);
        $percentinprogress = round($learningdata->enrolments != 0 ?
            $learningdata->inprogress * 100 / $learningdata->enrolments : 0, 2);
        $percentcompleted  = round($learningdata->enrolments != 0 ?
            $learningdata->completed * 100 / $learningdata->enrolments : 0, 2);

        $labels = [
            get_string('nbnotstarted', 'report_ed_kpi') . ' (' . $percentnotstarted . '%)',
            get_string('nbinprogress', 'report_ed_kpi') . ' (' . $percentinprogress . '%)',
            get_string('nbcompleted', 'report_ed_kpi') . ' (' . $percentcompleted . '%)',
        ];

        $dataset = [
            [
                'label'           => $title,
                'data'            => [
                    $learningdata->notstarted,
                    $learningdata->inprogress,
                    $learningdata->completed,
                ],
                'backgroundColor' => [
                    "#65a3cc",
                    "#df914b",
                    "#71c772",
                ],
            ],
        ];

        $jsdata = [
            'labels'   => $labels,
            'datasets' => $dataset,
        ];

        $this->page->requires->js_call_amd('report_ed_kpi/main', 'init_circle_chart', [
            'id'   => 'learning-data-circle',
            'data' => $jsdata,
        ]);

        return $output;
    }

    /**
     *
     * @param $monthlearningdata
     * @param $title
     * @param $subtitle
     * @return string
     * @throws coding_exception
     */
    public function render_learning_enrol_chart($monthlearningdata, $title, $subtitle) {

        $output = '';

        $output .= '<div class="chart-container">';
        $output .= '<h4>' . $title . '</h4>';
        $output .= '<span class="legend">' . $subtitle . '</span>';

        $output .= '<canvas id="learning-data" width="400" height="400"></canvas>';
        $output .= '</div>';

        $labels         = [];
        $datanotstarted = [
            'label'           => get_string('nbnotstarted', 'report_ed_kpi'),
            'data'            => [],
            'backgroundColor' => "#65a3cc",
            'realvalues'      => [],
        ];

        $datainprogress = [
            'label'           => get_string('nbinprogress', 'report_ed_kpi'),
            'data'            => [],
            'backgroundColor' => "#df914b",
            'realvalues'      => [],
        ];

        $datacompleted = [
            'label'           => get_string('nbcompleted', 'report_ed_kpi'),
            'data'            => [],
            'backgroundColor' => "#71c772",
            'realvalues'      => [],
        ];

        foreach ($monthlearningdata as $date => $monthdata) {
            $labels[] = date("M y", $date);

            $datanotstarted['data'][]       = floor($monthdata->enrolments != 0 ?
                $monthdata->notstarted * 100 / $monthdata->enrolments : 0);
            $datanotstarted['realvalues'][] = $monthdata->notstarted;

            $datainprogress['data'][]       = floor($monthdata->enrolments != 0 ?
                $monthdata->inprogress * 100 / $monthdata->enrolments : 0);
            $datainprogress['realvalues'][] = $monthdata->inprogress;

            $datacompleted['data'][]       = floor($monthdata->enrolments != 0 ?
                $monthdata->completed * 100 / $monthdata->enrolments : 0);
            $datacompleted['realvalues'][] = $monthdata->completed;

        }

        $jsdata = [
            'labels'   => $labels,
            'datasets' => [
                $datacompleted,
                $datainprogress,
                $datanotstarted,
            ],
        ];

        $this->page->requires->js_call_amd('report_ed_kpi/main', 'init_bar_chart', [
            'id'   => 'learning-data',
            'data' => $jsdata,
        ]);

        return $output;
    }

    /**
     *
     * @param $date
     * @return string
     * @throws coding_exception
     */
    private function userdate($date) {
        $format = get_string('date-format', 'report_ed_kpi');
        return userdate($date, $format);
    }

    /**
     *
     * @param $seconds
     * @return string
     */
    private function render_edtime_time($seconds) {
        return sprintf('%02d:%02d:%02d', ($seconds / 3600), ($seconds / 60 % 60), $seconds % 60);
    }
}
