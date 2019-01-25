<?php
/**
 * @package     HumanResources
 * @subpackage  Model
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2018-2019 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Model of a Daily Working Time Report
 *
 * The daily working time report combines multiple source records into a single
 * working time report. A time report is splitted into multiple categories of
 * working time. It's important to note, that the computed times in a report
 * are _not_ the sum of it's source timesheet records:
 * - times are cut according to evaluation_period
 * - break_deduction according to HumanResources_Model_WorkingTime
 * - goodies (might be extra time category) according to HumanResources_Model_WorkingTime
 *
 * DailyWorkingTimeReports are calculated once a day by a scheduler job. New
 * reports are created and all reports which from this and the last month which
 * don't have their is_cleared flag set get updated. Older reports can be
 * created/updated manually in the UI
 *
 * Timesheet records get their working_time_is_cleared and cleared_in fields
 * managed by the WorkingTimeReports calculations and clearance
 * @TODO: disallow to edit workingtime props in ts when clearance is set
 *
 * - controller holt timesheets
 * - regeln (wegschneiden + pausenzeiten) werden darauf angewendet => business rule processor
 * - transportmodel
 * -> report
 *
 * - alle zeiten in sekunden! auch stundenzettel müssen auf sekunden umgestellt werden...
 *
 * @package     HumanResources
 * @subpackage  Model
 *
 * @property Tinebase_DateTime          evaluation_period_start
 * @property Tinebase_DateTime          evaluation_period_end
 * @property boolean                    is_cleared
 * @property integer                    break_time_deduction
 * @property integer                    working_time_correction
 * @property integer                    working_time_actual
 * @property integer                    working_time_target
 * @property integer                    working_time_target_correction
 * @property integer                    break_time_net
 * @property Tinebase_Record_RecordSet  working_times
 */
class HumanResources_Model_DailyWTReport extends Tinebase_Record_Abstract
{
    const MODEL_NAME_PART = 'DailyWTReport';

    const FLDS_MONTHLYWTREPORT = 'monthlywtreport';

    /**
     * holds the configuration object (must be declared in the concrete class)
     *
     * @var Tinebase_ModelConfiguration
     */
    protected static $_configurationObject = null;

    /**
     * Holds the model configuration (must be assigned in the concrete class)
     *
     * @var array
     */
    protected static $_modelConfiguration = [
        'version' => 2,
        'recordName' => 'Daily Working Time Report',
        'recordsName' => 'Daily Working Time Reports', // ngettext('Daily Working Time Report', 'Daily Working Time Reports', n)
        'containerProperty' => null,
        'titleProperty' => 'date',
        'hasRelations' => false, // TODO really no relations?
        'hasCustomFields' => true,
        'hasNotes' => true,
        'hasTags' => true,
        'modlogActive' => true,
        'hasAttachments' => false,

        'createModule'    => true,
        'exposeHttpApi'     => true,
        'exposeJsonApi'     => true,

        'isDependent'     => false, // TODO remove?

        'appName' => 'HumanResources',
        'modelName' => self::MODEL_NAME_PART,

        'associations' => [
            \Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_ONE => [
                'employee_id' => [
                    'targetEntity' => 'HumanResources_Model_Employee',
                    'fieldName' => 'employee_id',
                    'joinColumns' => [[
                        'name' => 'employee_id',
                        'referencedColumnName'  => 'id'
                    ]],
                ]
            ],
            \Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_ONE => [
                'monthlywtreport' => [
                    'targetEntity' => HumanResources_Model_MonthlyWTReport::class,
                    'fieldName' => 'monthlywtreport',
                    'joinColumns' => [[
                        'name' => 'monthlywtreport',
                        'referencedColumnName'  => 'id'
                    ]],
                ]
            ],
        ],

        // why do i have to define this -> autodefine???
        'table'             => [
            'name'    => 'humanresources_wt_dailyreport',
            'indexes' => [
                'employee_id' => [
                    'columns' => ['employee_id'],
                ],
            ],
            self::UNIQUE_CONSTRAINTS => [
                'employee_id__date' => [
                    self::COLUMNS       => ['employee_id', 'date'],
                ],
            ],
        ],

        'fields' => [
            'employee_id' => [
                'label' => 'Employee',
                'type'  => 'record',
                'validators' => [
                    Zend_Filter_Input::ALLOW_EMPTY => false,
                    Zend_Filter_Input::PRESENCE => Zend_Filter_Input::PRESENCE_REQUIRED
                ],
                'duplicateCheckGroup' => 'date-employee', // TODO this doesnt work I guess
                'config' => [
                    'appName'     => 'HumanResources',
                    'modelName'   => 'Employee',
                ],
            ],
            'monthlywtreport' => [
                'label' => 'Monthly Working Time Report',
                'type'  => 'record',
                'inputFilters'  => ['Zend_Filter_Empty' => false],
                'validators' => [
                    Zend_Filter_Input::ALLOW_EMPTY => false,
                    Zend_Filter_Input::PRESENCE => Zend_Filter_Input::PRESENCE_REQUIRED
                ],
                'config' => [
                    'appName'     => HumanResources_Config::APP_NAME,
                    'modelName'   => HumanResources_Model_MonthlyWTReport::MODEL_NAME_PART,
                ],
            ],
            'date' => [
                'validators' => [
                    Zend_Filter_Input::ALLOW_EMPTY => false,
                    Zend_Filter_Input::PRESENCE => Zend_Filter_Input::PRESENCE_REQUIRED,
                ],
                'label'         => 'Date', // _('Date')
                'type'          => 'date',
            ],
            // kommt aus WorkingTime, z.b. von 9-17 uhr, kann auf tagesbasis im report geändert werden, siehe correction properties
            // änderungen stoßen neuberechnung an
            'evaluation_period_start' => [
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'label'         => 'Evaluation Start Time', // _('Evaluation Start Time')
                'type'          => 'time',
                'nullable'      => true,
            ],
            'evaluation_period_end' => [ // kommt aus WorkingTime
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'label'         => 'Evaluation End Time', // _('Evaluation End Time')
                'type'          => 'time',
                'nullable'      => true,
            ],
            'evaluation_period_start_correction' => [
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'label'         => 'Evaluation Start Time Correction', // _('Evaluation Start Time Correction')
                'type'          => 'time',
                'nullable'      => true,
                'inputFilters'  => ['Zend_Filter_Empty' => null],
            ],
            'evaluation_period_end_correction' => [
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'label'         => 'Evaluation End Time Correction', // _('Evaluation End Time Correction')
                'type'          => 'time',
                'nullable'      => true,
                'inputFilters'  => ['Zend_Filter_Empty' => null],
            ],
            //  zeit zwischen den zetteln (brutto pausenzeit - in transportklasse) + break_time_deduction
            'break_time_net'    => [
                'label'         => 'Break Time Net', // _('Break Time Net')
                'type'          => 'integer',
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'nullable'      => true,
            ],
            // WorkingTime - passiert, wenn MA zu wenig pause gemacht hat
            'break_time_deduction' => [
                'label'         => 'Break Deduction Time', // _('Break Deduction Time')
                'type'          => 'integer',
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'nullable'      => true,
            ],
            'working_times'  => [
                self::TYPE                  => self::TYPE_RECORDS,
                self::NULLABLE              => true,
                self::CONFIG                => [
                    self::APP_NAME              => HumanResources_Config::APP_NAME,
                    self::MODEL_NAME            => HumanResources_Model_BLDailyWTReport_WorkingTime::MODEL_NAME_PART,
                    self::STORAGE               => self::TYPE_JSON,
                ],
            ],
            // manuelles feld für korrekturen ("musterschüler")
            'working_time_correction' => [
                'type'          => 'integer',
                'label'         => 'Working Time Correction', // _('Working Time Correction')
                'nullable'      => true,
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'inputFilters'  => ['Zend_Filter_Empty' => null],
            ],
            // echte arbeitszeit nach regelanwendung
            'working_time_actual' => [
                'type'          => 'integer',
                'label'         => 'Actual Working Time', // _('Actual Working Time')
                'nullable'      => true,
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'inputFilters'  => ['Zend_Filter_Empty' => null],
            ],
            'working_time_target_correction' => [
                'type'          => 'integer',
                'label'         => 'Target Working Time', // _('Target Working Time')
                'nullable'      => true,
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'inputFilters'  => ['Zend_Filter_Empty' => null],
            ],
            // ziel zeit aus WorkingTime
            'working_time_target' => [
                'type'          => 'integer',
                'label'         => 'Target Working Time', // _('Target Working Time')
                'nullable'      => true,
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'inputFilters'  => ['Zend_Filter_Empty' => null],
            ],
            // z.b. krankheit, urlaub, feiertag (bei regelarbeit leer)
            'system_remark' => [
                'label'         => 'System Remark', // _('System Remark')
                'type'          => 'string',
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'nullable'      => true,
            ],
            'user_remark' => [
                'label'         => 'Remark', // _('Remark')
                'type'          => 'text',
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true],
                'nullable'      => true,
            ],
            // monatsprotokoll rechnet ab - nach übergabe an lohnbuchhaltung
            'is_cleared' => [
                'label'         => 'Is Cleared', // _('Is Cleared')
                'validators'    => [Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => 0],
                'type'          => 'boolean',
                'default'       => 0,
                'shy'           => true,
                'copyOmit'      => true,
            ],
        ]
    ];

    /**
     * @return HumanResources_Model_DailyWTReport
     */
    public function getCleanClone()
    {
        $result = clone $this;
        $result->break_time_net = 0;
        $result->break_time_deduction = 0;
        $result->working_time_actual = 0;
        $result->working_time_target = 0;
        $result->working_times = null;
        $result->evaluation_period_start = null;
        $result->evaluation_period_end = null;
        return $result;
    }

    /**
     * @return int
     */
    public function getIsWorkingTime()
    {
        return (int)$this->working_time_actual + (int)$this->working_time_correction;
    }

    /**
     * @return int
     */
    public function getShouldWorkingTime()
    {
        return (int)$this->working_time_target + (int)$this->working_time_target_correction;
    }
}