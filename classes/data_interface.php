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

namespace report_ed_kpi;

define('NB_MONTH_TRACKED', 8);

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

/**
 * Class data_interface
 *
 * Provides methods for handling user jobs, department hierarchies, course enrolments,
 * and learning data within an organizational structure.
 *
 * Interacts with the 'tool_organisation' plugin to retrieve and manage data such as
 * user roles, job positions, and course-related reports.
 *
 * @package    local_customplugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_interface {

    /**
     * Indicates whether the edtime plugin is activated.
     * @var bool
     */
    private $edtimeactivated = false;
    /**
     * Stores learning data for tracked courses and users.
     * @var array
     */
    private $learningsdata = [];
    /**
     * Stores information about all departments.
     * @var array|null
     */
    private $alldepartments = null;
    /**
     * Organisation object from the 'tool_organisation' plugin.
     * @var \tool_organisation\organisation
     */
    private $organisation;
    /**
     * Object representing the user's jobs with associated roles and positions.
     * @var \tool_organisation\output\user_with_jobs
     */
    private $userwithjobs;

    /**
     * Class constructor. Initializes the organisation and user jobs if the workplace plugin is active,
     * and checks if the edtime plugin is activated.
     */
    public function __construct() {
        $this->edtimeactivated = $this->edtime_actived();
        if (self::is_workplace()) {
            $this->organisation = new \tool_organisation\organisation();
            $this->userwithjobs = $this->organisation::get_user_with_jobs();
        }
    }

    /**
     * Checks if the platform is a workplace environment by verifying if the 'tool_program' table exists.
     *
     * @return bool Returns true if the workplace environment is active, false otherwise.
     */
    public static function is_workplace() {
        global $DB;
        $tables          = $DB->get_tables();
        return isset($tables['tool_program']);
    }

    /**
     * Returns the list of jobs this user is holding that can be additionally filtered
     *
     * @param bool $manageronly only return manager jobs
     * @param int $withpermissions only return jobs with permissions
     * @return \tool_organisation\output\job[]
     */
    public function get_jobs_of_current_user(bool $manageronly = false, int $withpermissions = 0): array {
        return $this->userwithjobs->get_jobs($manageronly, $withpermissions);
    }

    /**
     * Retrieves the tenant ID of a given user.
     *
     * If no user ID is provided, the tenant ID of the current user is returned.
     *
     * @param int $userid The user ID. If set to 0, the current user's ID is used.
     * @return int The tenant ID associated with the user.
     * @throws \dml_exception If an error occurs while retrieving the record from the database.
     */
    public function get_user_tenantid($userid = 0) {
        global $USER, $DB;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        return $DB->get_record('tool_tenant_user', ['userid' => $userid])->tenantid;
    }

    /**
     * Get departments directly attached to the active user.
     *
     * @param bool $manageronly
     * @param int $withpermissions
     * @param bool $subdepartments
     * @return \tool_organisation\department[]
     * @throws \coding_exception
     */
    public function get_departments_of_current_user(bool $manageronly = false, int $withpermissions = 0,
        bool $subdepartments = false) {
        global $DB;
        $departments = [];
        $jobs        = $this->get_jobs_of_current_user($manageronly, $withpermissions);
        foreach ($jobs as $job) {
            $department                          = $job->get_department();
            if ($subdepartments) {
                $subdepartmentsrecords = $DB->get_records_sql('
                    SELECT *
                    FROM {tool_organisation_department} d
                    WHERE id <> '. $department->get('id') .'
                    AND path LIKE "' . $department->get('path') . '%"
                ');
                foreach ($subdepartmentsrecords as $subdepartmentsrecord) {
                    $departments[$subdepartmentsrecord->id] = new \tool_organisation\department(0, $subdepartmentsrecord);
                }

            }

            $departments[$department->get('id')] = $department;
        }

        uasort($departments, function($a, $b) {
            if ($a->get('path') > $b->get('path')) {
                return 1;
            }
        });

        return $departments;
    }


    /**
     * Get id of positions options where user has a superior or equal position.
     *
     * @param bool $includefirstoption
     * @return int[]
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_positions_options(bool $manageronly = false, int $withpermissions = 0) {
        global $DB;

        $options = [];
        $jobs        = $this->get_jobs_of_current_user($manageronly, $withpermissions);
        foreach ($jobs as $job) {
            $position     = $job->get_position();
            $subpositions = $DB->get_records_sql('
                SELECT id, name
                FROM {tool_organisation_position}
                WHERE path LIKE "' . $position->get('path') . '%"
                    AND path <> "' . $position->get('path') . '"
                ORDER BY name ASC
            ');

            $options[] = $position->get('id');
            $options[] = $position->get_framework_id();
            if (count($subpositions) > 0) {
                foreach ($subpositions as $subposition) {
                    $options[] = $subposition->id;
                }
            }
        }

        $options = array_unique($options);
        return $options;

    }

    /**
     * Retrieves a list of departments that are being used, filtered by course enrolments and tenant ID.
     *
     * This method returns departments where users are currently enrolled in a specific course or belong to a specific tenant.
     * If a course ID is provided, it filters the departments by users enrolled in that course. If a tenant ID is provided,
     * it further filters the departments based on the tenant.
     *
     * The departments are grouped by their parent-child relationships and the number of members in each department is aggregated.
     *
     * @param int|null $course The course ID to filter users by. If null, no course filtering is applied.
     * @param int|null $tenantid The tenant ID to filter departments by. If null, no tenant filtering is applied.
     * @return array A hierarchical array of departments, with child departments nested under their parent.
     * @throws \dml_exception If an error occurs while retrieving data from the database.
     */
    public function get_used_departments($course = null, $tenantid = null) {
        global $DB;

        $condition = '';

        if ($course) {
            $userids = array_keys($this->get_course_enrolments($course, [], $tenantid));
            $condition .= ' AND j.userid IN (' . implode(',', $userids) . ')';
        }

        if ($tenantid) {
            $condition .= ' AND d.tenantid = ' . $tenantid . ' ';
        }

        $alldepartments = $DB->get_records_sql('
            SELECT d.id, d.name, d.parentid, d.pathlevel, d.path, count(j.userid) as members
            FROM {tool_organisation_department} d
            LEFT JOIN {tool_organisation_job} j ON d.id = j.departmentid
            WHERE 1=1    ' . $condition . '
            GROUP BY d.id
            ORDER BY pathlevel DESC
        ');

        $filtereddepartments = [];
        foreach ($alldepartments as $department) {

            if ($department->members == 0) {
                continue;
            }

            if (!empty($department->parentid) && isset($alldepartments[$department->parentid])) {

                $alldepartments[$department->parentid]->members += $department->members;

                if (!isset($alldepartments[$department->parentid]->children)) {
                    $alldepartments[$department->parentid]->children = [];
                }

                $alldepartments[$department->parentid]->children[$department->id] = $department;
            } else {
                $filtereddepartments[$department->id] = $department;
            }

        }

        return $filtereddepartments;
    }

    /**
     * Retrieves a list of department IDs, including the provided department and all of its child departments.
     *
     * This method returns an array of department IDs, starting with the given department, and recursively adding
     * all child departments that belong to it, forming a complete hierarchy of departments.
     *
     * @param int $departmentid The ID of the department for which to retrieve child department IDs.
     * @return array An array of department IDs, including the provided department and all of its child departments.
     *               If the department ID is -1, an empty array is returned.
     * @throws \dml_exception If an error occurs while querying the database.
     */
    public function get_department_childrenids_list($departmentid) {
        global $DB;

        if ($departmentid === -1) {
            return [];
        }

        $children = $DB->get_records_sql('
            SELECT id
            FROM {tool_organisation_department}
            WHERE parentid = :departmentid
        ', ['departmentid' => $departmentid]);

        $ids = [$departmentid];
        foreach ($children as $child) {
            $ids[] = $child->id;
            $childids = $this->get_department_childrenids_list($child->id);
            $ids = array_merge($ids, $childids);
        }

        return $ids;
    }

    /**
     *
     * @param false $course
     * @param null $tenantid
     * @return array
     * @throws \dml_exception
     */
    public function get_used_positions($course = false, $tenantid = null) {
        global $DB;

        $condition = '';
        if ($course) {
            $userids = array_keys($this->get_course_enrolments($course, [], $tenantid));
            $condition = 'AND j.userid IN (' . implode(',', $userids) . ')';
        }

        if ($tenantid) {
            $condition .= ' WHERE p.tenantid = ' . $tenantid . ' ';
        }

        $allpositions = $DB->get_records_sql('
            SELECT p.id, p.name, p.parentid, p.pathlevel, p.path, count(j.userid) as members
            FROM {tool_organisation_position} p
            LEFT JOIN {tool_organisation_job} j ON p.id = j.positionid
                ' . $condition . '
            GROUP BY p.id
            ORDER BY p.pathlevel DESC
        ');

        $filteredpositions = [];
        foreach ($allpositions as $position) {

            if ($position->members == 0) {
                continue;
            }

            if ($position->parentid != null) {
                $allpositions[$position->parentid]->members += $position->members;

                if (!isset($allpositions[$position->parentid]->children)) {
                    $allpositions[$position->parentid]->children = [];
                }

                $allpositions[$position->parentid]->children[] = $position;
            }

            if ($position->parentid == null) {
                $filteredpositions[$position->id] = $position;
            }

        }

        return $filteredpositions;
    }

    /**
     *
     * @param $positionid
     * @return array
     * @throws \dml_exception
     */
    public function get_position_childrenids_list($positionid) {
        global $DB;

        $children = $DB->get_records_sql('
            SELECT id
            FROM {tool_organisation_position}
            WHERE parentid = :positionid
        ', ['positionid' => $positionid]);

        $ids = [$positionid];
        foreach ($children as $child) {
            $ids[] = $child->id;
            $childids = $this->get_position_childrenids_list($child->id);
            $ids = array_merge($ids, $childids);
        }

        return $ids;
    }

    /**
     * Permet de savoir si edtime est présent sur la plateforme, pour calculer ou non le temps passé
     * @return bool
     */
    public static function edtime_actived() {
        global $CFG;

        $edtimelib = $CFG->dirroot . '/admin/tool/log/store/edtime/classes/report_api.php';
        if (file_exists($edtimelib)) {
            return true;
        }

        return false;
    }

    /**
     * Récupère les courses que l'on veut tracker dans les rapports (tous sauf 1 pour l'instant)
     * A terme, il faudra les filtrer pour éviter les doublons quand on affichera les stats de programmes
     * @return array
     * @throws \dml_exception
     */
    public function get_reported_courses() {
        global $DB;

        return $DB->get_records_sql('
            SELECT c.id, c.shortname, c.fullname
            FROM {course} c
            WHERE c.id > 1
        ');
    }

    /**
     * Récupère des données de type nombre d'enrolments/enrolment completé/en cours/pas commencé par cours traqués
     * A modifier pour ajouter les program/certification etc...
     * [
     *   id, // course id, program id...
     *   type, // course, program...
     *   code, // course shortname ...
     *   fullname,
     *   enrolments
     *   notstarted
     *   inprogress
     *   completed
     * ]
     * @return array
     * @throws \dml_exception
     */
    public function get_all_learning_data(array $departmentsfilter = [], $tenantid = null) {

        $learningdata = [];

        $courses = $this->get_reported_courses();
        foreach ($courses as $course) {

            $allcoursedata = $this->get_course_enrolments($course, $departmentsfilter, $tenantid);

            if (empty($allcoursedata)) {
                continue;
            }

            $aggregatedata = $this->get_course_aggregated_data($allcoursedata);
            $coursedata = ['type' => 'course', 'id' => $course->id, 'code' => $course->shortname, 'fullname' => $course->fullname];

            $learningdata[] = (object) array_merge($coursedata, (array)$aggregatedata);
        }

        return $learningdata;
    }

    /**
     *
     * @param $learningsdata
     * @return object
     */
    public function get_learnings_aggregated_data($learningsdata) {

        $aggregatedata = (object)[
            'enrolments' => 0,
            'notstarted' => 0,
            'inprogress' => 0,
            'completed' => 0,
        ];

        foreach ($learningsdata as $learningdata) {
            $aggregatedata->enrolments += $learningdata->enrolments;
            $aggregatedata->notstarted += $learningdata->notstarted;
            $aggregatedata->inprogress += $learningdata->inprogress;
            $aggregatedata->completed += $learningdata->completed;
        }

        return $aggregatedata;
    }

    /**
     *
     * @param array $departmentsfilter
     * @param null $tenantid
     * @return array
     * @throws \dml_exception
     */
    public function get_learnings_aggregated_month_data(array $departmentsfilter = [], $tenantid = null) {

        $nbmonth = NB_MONTH_TRACKED;

        $learningsmonthsdata = [];
        for ($i = ($nbmonth - 1); $i >= 0; $i--) {
            $mindate = strtotime("first day of -" . $i . ' month 00:00');

            $learningsmonthsdata[$mindate] = (object)[
                'enrolments' => 0,
                'notstarted' => 0,
                'inprogress' => 0,
                'completed' => 0,
            ];
        }

        $courses = $this->get_reported_courses();
        foreach ($courses as $course) {

            $allcoursedata = $this->get_course_enrolments($course, $departmentsfilter, $tenantid);

            if (empty($allcoursedata)) {
                continue;
            }

            $monthsdata = $this->get_course_aggregated_data_by_month($allcoursedata);

            foreach ($monthsdata as $date => $monthdata) {

                if ($monthdata->enrolments == 0) {
                    continue;
                }

                $learningsmonthsdata[$date]->enrolments += $monthdata->enrolments;
                $learningsmonthsdata[$date]->notstarted += $monthdata->notstarted;
                $learningsmonthsdata[$date]->inprogress += $monthdata->inprogress;
                $learningsmonthsdata[$date]->completed += $monthdata->completed;
            }
        }

        return $learningsmonthsdata;
    }

    /**
     *
     * @param $course
     * @param array $departmentsfilters
     * @param null $tenantid
     * @return array|mixed
     * @throws \dml_exception
     */
    public function get_course_enrolments($course, array $departmentsfilters = [], $tenantid = null) {
        global $DB;
        if (empty($departmentsfilters) && array_key_exists('course-' . $course->id, $this->learningsdata)) {
            return $this->learningsdata['course-' . $course->id];
        }

        $join = '';
        $where = '';

        if ($tenantid) {
            $join .= ' JOIN {tool_tenant_user} tu ON u.id = tu.userid ';
            $where .= ' AND tu.tenantid = '. $tenantid;
        }

        $context = \context_course::instance($course->id);
        $userswithroles = $DB->get_records_sql('
            SELECT DISTINCT ra.userid
            FROM {role_assignments} ra
            JOIN {user} u ON ra.userid = u.id
            '.$join.'
            WHERE ra.contextid = :contextid
            AND ra.roleid IN (:rolesids)
            AND u.deleted = 0
            '.$where.'
        ', ['contextid' => $context->id, 'rolesids' => implode(',', $this->get_reported_roles()), 'courseid1' => $course->id]);

        if (!$userswithroles) {
            $this->learningsdata['course-' . $course->id] = [];
            return [];
        }

        // S'il n'y a pas de date de début, comme pour les inscriptions via des programmes, le timestart sera à 0 et donc on exclut.
        // EDIT 20241205 - neoma-67 - add condition to get cohort enrolments.
        $enroledusers = $DB->get_records_sql('
            SELECT ue.userid, min(timestart) as timestart
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid = :courseid AND (timestart > 0 OR e.enrol = "cohort" )
            GROUP BY ue.userid
        ', ['courseid' => $course->id]);

        if (!$enroledusers) {
            $this->learningsdata['course-' . $course->id] = [];
            return [];
        }

        $userslastaccess = $DB->get_records('user_lastaccess', ['courseid' => $course->id], '', 'userid, timeaccess');

        // Grade.
        $coursegrades = \grade_get_course_grades($course->id, array_keys($userswithroles))->grades;

        // Filter users by departement and/or positions.
        $filteredusers = [];
        if (!empty($departmentsfilters)) {

            $filteredusers = $DB->get_records_sql('
              SELECT distinct userid
              FROM {tool_organisation_job}
              WHERE departmentid IN ('.implode(',', $departmentsfilters).')
              AND userid IN ('.implode(',', array_keys($enroledusers)).')
        '   );
        }

        if ((!empty($departmentsfilters)) && !$filteredusers) {
            return [];
        }

        $usersdata = [];
        foreach ($userswithroles as $user) {
            $userid = $user->userid;

            if ($filteredusers && !array_key_exists($userid, $filteredusers)) {
                continue;
            }

            if (!array_key_exists($userid, $enroledusers)) {
                continue;
            }

            $usersdata[$user->userid] = (object)[
                'completionstatus' => null,
                'enrolmentdate' => $enroledusers[$userid]->timestart,
                'lastaccess' => 0,
                'completiondate' => 0,
                'grade' => null,
            ];

            if (array_key_exists($userid, $userslastaccess)) {
                $usersdata[$user->userid]->lastaccess = $userslastaccess[$userid]->timeaccess;
            }

            if (array_key_exists($userid, $coursegrades)) {
                $usersdata[$user->userid]->grade = $coursegrades[$userid]->grade;
            }

            // Edtime time if the plugin exist.
            if ($this->edtimeactivated) {
                $usersdata[$user->userid]->time = \logstore_edtime\report_api::calculate_user_time(0, time(), $user->userid,
                    $course->id);
            }

        }

        // Pour toutes les réussites ont vérifie que Moodle a bien noté la réussite en base et on récupère la date.
        $coursecompletiondata = $DB->get_records('course_completions', ['course' => $course->id], '',
            'userid, timestarted, timecompleted');
        foreach ($coursecompletiondata as $userid => $completiondata) {
            if (!array_key_exists($userid, $usersdata)) {
                continue;
            }

            if ($completiondata->timecompleted > 0) {
                $usersdata[$userid]->completionstatus = 1;
                $usersdata[$userid]->completiondate = $completiondata->timecompleted;
            } else {
                $usersdata[$userid]->completionstatus = $usersdata[$userid]->lastaccess > 0 ? -1 : 0;
            }

        }

        if (!$departmentsfilters) {
            $this->learningsdata['course-' . $course->id] = $usersdata;
        }

        return $usersdata;

    }

    /**
     *
     * @param $courseusersdata
     * @return object
     */
    public function get_course_aggregated_data($courseusersdata) {
        $aggregatedata = (object)[
            'enrolments' => count($courseusersdata),
            'notstarted' => 0,
            'inprogress' => 0,
            'completed' => 0,
        ];

        foreach ($courseusersdata as $userdata) {
            if ($userdata->completiondate != 0) {
                $aggregatedata->completed ++;
                continue;
            }

            if ($userdata->completionstatus == -1) {
                $aggregatedata->inprogress ++;
                continue;
            }

            $aggregatedata->notstarted ++;
        }

        return $aggregatedata;
    }

    /**
     *
     * @param $courseusersdata
     * @return array
     */
    public function get_course_aggregated_data_by_month($courseusersdata) {

        $nbmonth = NB_MONTH_TRACKED;

        $monthsdata = [];
        for ($i = ($nbmonth - 1); $i >= 0; $i--) {
            $mindate = strtotime("first day of -" . $i . ' month 00:00');

            $monthsdata[$mindate] = (object)[
                'enrolments' => 0,
                'notstarted' => 0,
                'inprogress' => 0,
                'completed' => 0,
            ];
        }

        foreach ($courseusersdata as $userdata) {

            $cleanenrolmentdate = strtotime(date('01-m-Y', $userdata->enrolmentdate));
            if (!array_key_exists($cleanenrolmentdate, $monthsdata)) {
                continue;
            }

            $monthsdata[$cleanenrolmentdate]->enrolments++;
            if ($userdata->completiondate != 0) {
                $monthsdata[$cleanenrolmentdate]->completed ++;
                continue;
            }

            if ($userdata->completionstatus == -1) {
                $monthsdata[$cleanenrolmentdate]->inprogress ++;
                continue;
            }

            $monthsdata[$cleanenrolmentdate]->notstarted ++;
        }

        return $monthsdata;
    }

    /**
     *
     * @return false|string[]
     * @throws \dml_exception
     */
    public function get_reported_roles() {
        return explode(',', get_config('core', 'gradebookroles'));
    }

}
