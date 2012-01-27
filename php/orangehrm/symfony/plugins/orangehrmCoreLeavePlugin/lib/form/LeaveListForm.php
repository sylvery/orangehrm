<?php

/**
 * Form class for leave list
 */
class LeaveListForm extends sfForm {
    const MODE_SUPERVISOR_DETAILED_LIST = 'detailed_supervisor_list';
    const MODE_HR_ADMIN_DETAILED_LIST = 'detailed_hr_admin_list';
    const MODE_MY_LEAVE_LIST = 'my_leave_list';
    const MODE_MY_LEAVE_DETAILED_LIST = 'my_leave_detailed_list';
    const MODE_TAKEN_LEAVE_LIST = 'my_taken_leave_list';
    const MODE_DEFAULT_LIST = 'default_list';

    private $mode;
    private $leavePeriod = null;
    private $employee = null;
    private $actionButtons = array();
    private $list = null;
    private $filters = null;
    private $showBackButton = false;

    private $leaveRequest;
    private $empJson;
    private $leavePeriodService;
    public $pageNo;
    private $companyStructureService;

    public function getCompanyStructureService() {
        if (is_null($this->companyStructureService)) {
            $this->companyStructureService = new CompanyStructureService();
            $this->companyStructureService->setCompanyStructureDao(new CompanyStructureDao());
        }
        return $this->companyStructureService;
    }

    public function setCompanyStructureService(CompanyStructureService $companyStructureService) {
        $this->companyStructureService = $companyStructureService;
    }


    public function getLeavePeriodService() {
        if (is_null($this->leavePeriodService)) {
            $leavePeriodService = new LeavePeriodService();
            $leavePeriodService->setLeavePeriodDao(new LeavePeriodDao());
            $this->leavePeriodService = $leavePeriodService;
        }
        return $this->leavePeriodService;
    }
    
    public function __construct($mode = null, $leavePeriod = null, $employee = null, 
            $filters = null, $loggedUserId = null, $leaveRequest = null) {

        if (empty($mode)) {
            $mode = self::MODE_DEFAULT_LIST;
        }

        $this->mode = $mode;
        $this->leavePeriod = $leavePeriod;
        $this->employee = $employee;
        $this->actionButtons = array();
        $this->filters = $filters;
        $this->leaveRequest = $leaveRequest;
        
        parent::__construct(array(), array());
    }

    public function configure() {

        $this->setWidgets(array(
            'calFromDate' => new ohrmWidgetDatePickerNew(array(), array('id' => 'calFromDate')),
            'calToDate' => new ohrmWidgetDatePickerNew(array(), array('id' => 'calToDate')),
        ));        
                        
        //$startDate = $this->_getFilterParam('calFromDate');
        //$endDate = $this->_getFilterParam('calToDate');
        if (empty($startDate) && empty($endDate)) {

            if ($this->leavePeriod instanceof LeavePeriod) {
                $startDate = set_datepicker_date_format($this->leavePeriod->getStartDate());
                $endDate = set_datepicker_date_format($this->leavePeriod->getEndDate());
            }
        }

        $this->getWidget('calFromDate')->setDefault($startDate);
        $this->getWidget('calToDate')->setDefault($endDate);
        
        $defaultStatuses = $this->_getFilterParam('chkSearchFilter');
        $leaveStatusChoices = Leave::getStatusTextList();        
        $this->setWidget('chkSearchFilter', new ohrmWidgetCheckboxGroup(array('choices' => $leaveStatusChoices,
                                                                  'show_all_option' => true,
                                                                  'default' => $defaultStatuses)));
            
        $this->getWidgetSchema()->setLabel('chkSearchFilter', __('Show Leave with Status'));


        if ($this->mode != self::MODE_MY_LEAVE_LIST && $this->mode != self::MODE_MY_LEAVE_DETAILED_LIST) {
            
            $this->setWidget('cmbSubunit', new ohrmWidgetSubUnitDropDown(array('choices' => $subUnitList, 'default' => $this->_getFilterParam('cmbSubunit')), array('id' => 'cmbSubunit')));            
            $employeeId = trim($this->_getFilterParam('txtEmpId'));
            if ($employeeId == "" && $this->employee instanceof Employee) {
                $employeeId = $this->employee->getEmpNumber();
            }


            $this->setWidget('txtEmpID', new sfWidgetFormInputHidden(array('default' => $employeeId)));            
            
            if (is_null($this->_getFilterParam('cmbWithTerminated'))) {
                $this->setWidget('cmbWithTerminated', new sfWidgetFormInputCheckbox());
            } else if ($this->_getFilterParam('cmbWithTerminated') == 'on') {
                $this->setWidget('cmbWithTerminated', new sfWidgetFormInputCheckbox(array('value_attribute_value' => 'on', 'default' => true)));
            }

            $employeeName = trim($this->_getFilterParam('txtEmployee'));
            if ($employeeName == "" && $this->employee instanceof Employee) {
                $employeeName = $this->employee->getFirstName() . " " . $this->employee->getMiddleName() . " " . $this->employee->getLastName();
            }
            $this->setWidget('txtEmployee', new sfWidgetFormInput(array('default' => $employeeName)));
        }        
    }

    /**
     * Formats the title of the leave list according to the mode
     *
     * @return string Title of the leave list
     */
    public function getTitle() {

        if ($this->mode === self::MODE_SUPERVISOR_DETAILED_LIST) {
            $title = 'Approve Leave Request for %s';
            $replacements = array($this->employee->getFullName());
        } elseif ($this->mode === self::MODE_HR_ADMIN_DETAILED_LIST) {
            $str = "";
            if ($this->leaveRequest instanceof LeaveRequest) {
                $str .= "(" . $this->getLeaveDateRange($this->leaveRequest->getLeaveRequestId()) . ") - ";
            }
            $str .= $this->employee->getFullName();
            $title = __('Leave Request') . ' %s';
            $replacements = array($str);
        } elseif ($this->mode === self::MODE_TAKEN_LEAVE_LIST) {
            $title = 'Leave Taken by %s in %s';
            $replacements = array($this->employee->getFullName(), $this->leavePeriod->getDescription());
        } elseif ($this->mode === self::MODE_MY_LEAVE_LIST) {
            $title = __('My Leave List');
            $replacements = null;
        } elseif ($this->mode === self::MODE_MY_LEAVE_DETAILED_LIST) {
            $title = __('My Leave Details');
            $replacements = null;
        } else {
            $title = 'Leave List';
            $replacements = null;
        }

        return vsprintf(__($title), $replacements);
    }

    /**
     * Returns the set of action buttons associated with each mode of the leave list
     *
     * @return array Array of action buttons as instances of ohrmWidegetButton class
     */
    public function getSearchActionButtons() {
        return array(
            'btnSearch' => new ohrmWidgetButton('btnSearch', 'Search', array('class' => 'searchbutton')),
            'btnReset' => new ohrmWidgetButton('btnReset', 'Reset', array('class' => 'clearbutton')),
        );
    }

    public function setList($list) {
        $this->list = $list;
    }

    public function getList() {
        return $this->list;
    }

    public function isPaginated() {
        return ($this->mode == self::MODE_DEFAULT_LIST || $this->mode == self::MODE_MY_LEAVE_LIST);
    }

    public function isDetailed() {
        return!($this->mode == self::MODE_DEFAULT_LIST || $this->mode == self::MODE_MY_LEAVE_LIST);
    }

    public function setShowBackButton($value) {
        $this->showBackButton = $value;
    }

    public function getEmployeeListAsJson() {
        return $this->empJson;
    }

    public function setEmployeeListAsJson($str) {
        $this->empJson = $str;
    }

    public function getActionButtons() {

        if ((!empty($this->list)) && ($this->mode !== self::MODE_TAKEN_LEAVE_LIST)) {
            $this->actionButtons['btnSave'] = new ohrmWidgetButton('btnSave', "Save", array('class' => 'savebutton'));
        }

        // showing back button only on details
        if ($this->isDetailed()) {
            $this->actionButtons['btnBack'] = new ohrmWidgetButton('btnBack', "Back", array('class' => 'backbutton'));
        }

        return $this->actionButtons;
    }

    public function getLeaveDateRange($leaveRequestId) {

        sfContext::getInstance()->getConfiguration()->loadHelpers('OrangeDate');

        $leaveRequestService = new LeaveRequestService();
        $leaveRequestService->setLeaveRequestDao(new LeaveRequestDao());

        $leaveList = $leaveRequestService->searchLeave($leaveRequestId);
        $count = count($leaveList);

        if ($count == 1) {

            return set_datepicker_date_format($leaveList[0]->getLeaveDate());
        } else {

            $range = set_datepicker_date_format($leaveList[0]->getLeaveDate());
            $range .= " " . __('to') . " ";
            $range .= set_datepicker_date_format($leaveList[$count - 1]->getLeaveDate());

            return $range;
        }
    }

    protected function _isOverQuotaAllowed($leaveTypeId) {

        $leaveTypeService = new LeaveTypeService();
        $leaveTypeService->setLeaveTypeDao(new LeaveTypeDao());
        $leaveType = $leaveTypeService->readLeaveType($leaveTypeId);

        if (!$leaveType instanceof LeaveType) {
            return false;
        }

        return true;
    }

    public function getQuotaClass($leaveTypeId) {

        if ($this->_isOverQuotaAllowed($leaveTypeId)) {
            return '';
        } else {
            return ' quotaSelect';
        }
    }

    public function getQuotaArray($leaveRequestList) {

        $quotaArray = array();

        if ($leaveRequestList[0] instanceof LeaveRequest) {

            foreach ($leaveRequestList as $request) {

                $employeeId = $request->getEmpNumber();
                $leaveTypeId = $request->getLeaveTypeId();
                $leavePeriodId = $request->getLeavePeriodId();

                if (!$this->_isOverQuotaAllowed($leaveTypeId)) {

                    $key = $employeeId . '-';
                    $key .= $leaveTypeId . '-';
                    $key .= $leavePeriodId;

                    $leaveEntitlementService = new LeaveEntitlementService();
                    $leaveEntitlementService->setLeaveEntitlementDao(
                            new LeaveEntitlementDao());

                    $leaveBalance = $leaveEntitlementService->getLeaveBalance(
                                    $employeeId,
                                    $leaveTypeId,
                                    $leavePeriodId);

                    $quotaArray[$key] = $leaveBalance;
                }
            }
        } elseif ($leaveRequestList[0] instanceof Leave) {

            $employeeId = $leaveRequestList[0]->getEmployeeId();
            $leaveTypeId = $leaveRequestList[0]->getLeaveTypeId();
            $leavePeriodId = $leaveRequestList[0]->getLeaveRequest()
                            ->getLeavePeriodId();

            $key = $employeeId . '-';
            $key .= $leaveTypeId . '-';
            $key .= $leavePeriodId;

            $leaveEntitlementService = new LeaveEntitlementService();
            $leaveEntitlementService->setLeaveEntitlementDao(
                    new LeaveEntitlementDao());

            $leaveBalance = $leaveEntitlementService->getLeaveBalance(
                            $employeeId, $leaveTypeId,
                            $leavePeriodId);

            $quotaArray[$key] = $leaveBalance;
        }

        return $quotaArray;
    }

    private function _getFilterParam($paramName) {
        $value = null;

        if (isset($this->filters[$paramName])) {
            $value = $this->filters[$paramName];
        }

        return $value;
    }

}
