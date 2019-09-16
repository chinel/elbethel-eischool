<?php
namespace App\Http\Controllers;

class AttendanceController extends Controller {

	var $data = array();
	var $panelInit ;
	var $layout = 'dashboard';

	public function __construct(){
		if(app('request')->header('Authorization') != "" || \Input::has('token')){
			$this->middleware('jwt.auth');
		}else{
			$this->middleware('authApplication');
		}

		$this->panelInit = new \DashboardInit();
		$this->data['panelInit'] = $this->panelInit;
		$this->data['breadcrumb']['Settings'] = \URL::to('/dashboard/languages');
		$this->data['users'] = $this->panelInit->getAuthUser();

		if(!isset($this->data['users']->id)){
			return \Redirect::to('/');
		}

	}

	public function listAll()
	{

		if(!$this->panelInit->can( array("Attendance.takeAttendance","Attendance.attReport") )){
			exit;
		}

		$toReturn = array();
		$toReturn['attendanceModel'] = $this->data['panelInit']->settingsArray['attendanceModel'];

		if($this->data['users']->role == "teacher"){
			$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->where('classTeacher','LIKE','%"'.$this->data['users']->id.'"%')->get()->toArray();
		}else{
			$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
		}

		$toReturn['userRole'] = $this->data['users']->role;
		return $toReturn;
	}

	public function listAttendance(){

		if(!$this->panelInit->can( "Attendance.takeAttendance" )){
			exit;
		}

		$toReturn = array();
		
		$toReturn['class'] = \classes::where('id',\Input::get('classId'));
		if($toReturn['class']->count() == 0){
			return $toReturn;
		}
		$toReturn['class'] = $toReturn['class']->first()->toArray();

		if(\Input::get('subjectId')){
			$toReturn['subject'] = \subject::where('id',\Input::get('subjectId'))->first()->toArray();
		}

		$toReturn['students'] = array();
		$studentArray = \User::where('role','student')->where('activated','1')->where('studentClass',\Input::get('classId'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$studentArray = $studentArray->where('studentSection',\Input::get('sectionId'));
		}

		if($this->data['panelInit']->settingsArray['studentsSort'] != ""){
			$studentArray = $studentArray->orderByRaw($this->data['panelInit']->settingsArray['studentsSort']);
		}

		$studentArray = $studentArray->get();

		$attendanceList = array();
		$vacationList = array();

		$vacationArray = \vacation::where('vacDate',$this->panelInit->date_to_unix(\Input::get('attendanceDay')))->where('acYear',$this->panelInit->selectAcYear)->where('role','student')->get();

		if(\Input::get('subjectId')){
			$attendanceArray = \attendance::where('classId',\Input::get('classId'))->where('subjectId',\Input::get('subjectId'))->where('date',$this->panelInit->date_to_unix(\Input::get('attendanceDay')))->get();
		}else{
			$attendanceArray = \attendance::where('classId',\Input::get('classId'))->where('date',$this->panelInit->date_to_unix(\Input::get('attendanceDay')))->get();
		}
		foreach ($attendanceArray as $stAttendance) {
			$attendanceList[$stAttendance->studentId] = $stAttendance;
		}
		foreach ($vacationArray as $vacation) {
			$vacationList[$vacation->userid] = $vacation->acceptedVacation;
		}

		$i = 0;
		foreach ($studentArray as $stOne) {
			$toReturn['students'][$i] = array('name'=>$stOne->fullName,'id'=>$stOne->id,'studentRollId'=>$stOne->studentRollId,'attendance'=> '', );

			if(isset($attendanceList[$stOne->id])){
				$toReturn['students'][$i]['attendance'] = $attendanceList[$stOne->id]->status;
				$toReturn['students'][$i]['attNotes'] = $attendanceList[$stOne->id]->attNotes;
			}

			if(isset($vacationList[$stOne->id])){
				$toReturn['students'][$i]['vacation'] = true;
				$toReturn['students'][$i]['vacationStat'] = $vacationList[$stOne->id];
			}

			$i ++ ;
		}

		return json_encode($toReturn);
	}

	public function saveAttendance(){

		if(!$this->panelInit->can( "Attendance.takeAttendance" )){
			exit;
		}
		
		$vacationArray = array();
		$vacationList = \vacation::where('vacDate',$this->panelInit->date_to_unix(\Input::get('attendanceDay')))->where('acYear',$this->panelInit->selectAcYear)->where('role','student')->get();
		foreach ($vacationList as $vacation) {
			$vacationArray[$vacation->userid] = $vacation->id;
		}

		if($this->panelInit->settingsArray['absentNotif'] == "mail" || $this->panelInit->settingsArray['absentNotif'] == "mailsms"){
			$mail = true;
		}
		if($this->panelInit->settingsArray['absentNotif'] == "sms" || $this->panelInit->settingsArray['absentNotif'] == "mailsms"){
			$sms = true;
		}
		if(isset($mail) || isset($sms)){
			$mailTemplate = \mailsms_templates::where('templateTitle','Student Absent')->first();
		}

		$stAttendance = \Input::get('stAttendance');
		foreach($stAttendance as $key => $value){
			if(isset($value['attendance']) AND strlen($value['attendance']) > 0){

				$attendanceN = \attendance::where('studentId',$value['id'])->where('date',$this->panelInit->date_to_unix(\Input::get('attendanceDay')))->where('classId',\Input::get('classId'));
				if($this->data['panelInit']->settingsArray['attendanceModel'] == "subject"){
					$attendanceN = $attendanceN->where('subjectId', \Input::get('subjectId') );
				}

				$changed_att_status = true;
				if($attendanceN->count() == 0){
					$attendanceN = new \attendance();
				}else{
					$attendanceN = $attendanceN->first();

					if($attendanceN->status == $value['attendance']){
						$changed_att_status = false;
					}

				}

				$attendanceN->classId = \Input::get('classId');
				$attendanceN->date = $this->panelInit->date_to_unix(\Input::get('attendanceDay'));
				$attendanceN->studentId = $value['id'];
				$attendanceN->status = $value['attendance'];
				if(isset($value['attNotes'])){
					$attendanceN->attNotes = $value['attNotes'];						
				}
				if($this->data['panelInit']->settingsArray['attendanceModel'] == "subject"){
					$attendanceN->subjectId = \Input::get('subjectId');
				}
				$attendanceN->save();

				if($changed_att_status == false){
					continue;
				}

				if($value['attendance'] != "1" AND $this->panelInit->settingsArray['absentNotif'] != "0"){
					$parents = \User::where('parentOf','like','%"'.$value['id'].'"%')->orWhere('parentOf','like','%:'.$value['id'].'}%')->get();
					$student = \User::where('id',$value['id'])->first();

					$absentStatus = "";
					switch ($value['attendance']) {
						case '0':
							$absentStatus = $this->panelInit->language['Absent'];
							break;
						case '2':
							$absentStatus = $this->panelInit->language['Late'];
							break;
						case '3':
							$absentStatus = $this->panelInit->language['LateExecuse'];
							break;
						case '4':
							$absentStatus = $this->panelInit->language['earlyDismissal'];
							break;
						case '9':
							$absentStatus = $this->panelInit->language['acceptedVacation'];
							break;
					}
					$MailSmsHandler = new \MailSmsHandler();
					foreach ($parents as $parent) {
						if(isset($mail) AND strpos($parent->comVia, 'mail') !== false){
							$studentTemplate = $mailTemplate->templateMail;
							$examGradesTable = "";
							$searchArray = array("{studentName}","{studentRoll}","{studentEmail}","{studentUsername}","{parentName}","{parentEmail}","{absentDate}","{absentStatus}","{schoolTitle}");
							$replaceArray = array($student->fullName,$student->studentRollId,$student->email,$student->username,$parent->fullName,$parent->email,\Input::get('attendanceDay'),$absentStatus,$this->panelInit->settingsArray['siteTitle']);
							$studentTemplate = str_replace($searchArray, $replaceArray, $studentTemplate);
							$MailSmsHandler->mail($parent->email,$this->panelInit->language['absentReport'],$studentTemplate);
						}
						if(isset($sms) AND $parent->mobileNo != "" AND strpos($parent->comVia, 'sms') !== false){
							$origin_template = $mailTemplate->templateSMS;
							$examGradesTable = "";
							$searchArray = array("{studentName}","{studentRoll}","{studentEmail}","{studentUsername}","{parentName}","{parentEmail}","{absentDate}","{absentStatus}","{schoolTitle}");
							$replaceArray = array($student->fullName,$student->studentRollId,$student->email,$student->username,$parent->fullName,$parent->email,\Input::get('attendanceDay'),$absentStatus,$this->panelInit->settingsArray['siteTitle']);
							$studentTemplate = str_replace($searchArray, $replaceArray, $origin_template);
							$MailSmsHandler->sms($parent->mobileNo,$studentTemplate);
						}

						//Send Push Notifications
						if($parent->firebase_token != ""){
							$this->panelInit->send_push_notification($parent->firebase_token,$this->panelInit->language['attNNotif']." : " . $student->fullName . " ".$this->panelInit->language['is']." " . $absentStatus . " - ".$this->panelInit->language['Date']." : " . \Input::get('attendanceDay'),$this->panelInit->language['Attendance'],"attendance");					
						}
					}

				}
			}
		}

		return $this->panelInit->apiOutput(true,"Attendance",$this->panelInit->language['attendanceSaved'] );
	}

	public function reportAttendance(){

		if(!$this->panelInit->can( "Attendance.attReport" )){
			exit;
		}

		$toReturn = array();

		$toReturn['class'] = \classes::where('id',\Input::get('classId'));
		if($toReturn['class']->count() == 0){
			return $toReturn;
		}
		$toReturn['class'] = $toReturn['class']->first()->toArray();

		if(\Input::get('subjectId')){
			$toReturn['subject'] = \subject::where('id',\Input::get('subjectId'))->first()->toArray();
		}

		//Prepare users list
		$toReturn['students'] = array();
		$student_ids = array();
		$studentArray = \User::where('role','student')->where('activated','1')->where('studentClass',\Input::get('classId'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$studentArray = $studentArray->where('studentSection',\Input::get('sectionId'));
		}
		if($this->data['panelInit']->settingsArray['studentsSort'] != ""){
			$studentArray = $studentArray->orderByRaw($this->data['panelInit']->settingsArray['studentsSort']);
		}
		$studentArray = $studentArray->get();

		foreach ($studentArray as $key => $value) {
			$toReturn['students'][$value->id] = array("id"=>$value->id,"fullName"=>$value->fullName,"studentRollId"=>$value->studentRollId,"precentage"=>array("0"=>0,"1"=>0,"2"=>0,"3"=>0,"4"=>0),"attendance"=>array(),"vacation"=>array());
			$student_ids[] = $value->id;
		}

		$toReturn['date_range'] = $this->panelInit->date_ranges(\Input::get('attendanceDayFrom'),\Input::get('attendanceDayTo'));

		//Prepare attendance List
		$attendanceN = \attendance::where('date','>=',$this->panelInit->date_to_unix(\Input::get('attendanceDayFrom')))->where('date','<=',$this->panelInit->date_to_unix(\Input::get('attendanceDayTo')))->where('classId',\Input::get('classId'));
		if($this->data['panelInit']->settingsArray['attendanceModel'] == "subject"){
			$attendanceN = $attendanceN->where('subjectId', \Input::get('subjectId') );
		}
		$attendanceN = $attendanceN->get();

		foreach ($attendanceN as $key => $value) {
			if(isset($toReturn['students'][$value->studentId])){
				if(!isset($toReturn['students'][$value->studentId]['attendance'][$value->date])){
					$toReturn['students'][$value->studentId]['attendance'][$value->date] = array();
				}
				$toReturn['students'][$value->studentId]['attendance'][$value->date]['status'] = $value->status;
				$toReturn['students'][$value->studentId]['attendance'][$value->date]['attNotes'] = $value->attNotes;
			}
		}

		if(count($student_ids) > 0){
			$vacationArray = \vacation::where('vacDate','>=',$this->panelInit->date_to_unix(\Input::get('attendanceDayFrom')))->where('vacDate','<=',$this->panelInit->date_to_unix(\Input::get('attendanceDayTo')))->whereIn('userid',$student_ids)->get();
			foreach ($vacationArray as $key => $value) {
				if(isset($toReturn['students'][$value->userid])){
					$toReturn['students'][$value->userid]['vacation'][$value->vacDate] = $value->acceptedVacation;
				}
			}
		}
		

		foreach ($toReturn['students'] as $key => $value) {
			$total = 0;
			$attendance_perc = array("0"=>0,"1"=>0,"2"=>0,"3"=>0,"4"=>0);
			foreach ($toReturn['students'][$key]['attendance'] as $key_ => $value_) {
				if(!isset($attendance_perc[$value_['status']])){
					$attendance_perc[$value_['status']] = 0;
				}
				$attendance_perc[$value_['status']]++;
				$total ++;
			}
			if($total == 0){
				continue;
			}
			$toReturn['students'][$key]['precentage'][0] = round(($attendance_perc[0] / $total)*100,1);
			$toReturn['students'][$key]['precentage'][1] = round(($attendance_perc[1] / $total)*100,1);
			$toReturn['students'][$key]['precentage'][2] = round(($attendance_perc[2] / $total)*100,1);
			$toReturn['students'][$key]['precentage'][3] = round(($attendance_perc[3] / $total)*100,1);
			$toReturn['students'][$key]['precentage'][4] = round(($attendance_perc[4] / $total)*100,1);
		}

		return $toReturn;
	}

	public function getStats($date = ""){
		if($date == ""){
			$date = date('m/Y');
		}

		$startTime = time() - (30*60*60*24);
		$endTime = time() + (60*60*24);

		$toReturn = array();
		$classes = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get();

		if($this->data['users']->role == "teacher"){
			$classes = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->where('classTeacher','LIKE','%"'.$this->data['users']->id.'"%')->get();
		}else{
			$classes = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get();
		}

		$toReturn['classes'] = array();
		$subjList = array();
		foreach ($classes as $class) {
			$class['classSubjects'] = json_decode($class['classSubjects'],true);
			if(is_array($class['classSubjects'])){
				foreach ($class['classSubjects'] as $subject) {
					$subjList[] = $subject;
				}
			}
			$toReturn['classes'][$class->id] = $class->className ;
		}

		$subjList = array_unique($subjList);
		if($this->data['panelInit']->settingsArray['attendanceModel'] == "subject"){
			$toReturn['subjects'] = array();
			if(count($subjList) > 0){
				$subjects = \subject::whereIN('id',$subjList)->get();
				foreach ($subjects as $subject) {
					$toReturn['subjects'][$subject->id] = $subject->subjectTitle ;
				}
			}
		}

		$toReturn['role'] = $this->data['users']->role;
		$toReturn['attendanceModel'] = $this->data['panelInit']->settingsArray['attendanceModel'];

		if($this->data['users']->role == "admin" || $this->data['users']->role == "teacher"){
			$attendanceArray = \attendance::where('date','>=',$startTime)->where('date','<=',$endTime)->orderBy('date','asc')->get();

		}elseif($this->data['users']->role == "student"){
			$attendanceArray = \attendance::where('studentId',$this->data['users']->id)->where('date','>=',$startTime)->where('date','<=',$endTime)->orderBy('date','asc')->get();
			foreach ($attendanceArray as $value) {
				$toReturn['studentAttendance'][] = array("date"=>$this->panelInit->unix_to_date($value->date),"status"=>$value->status,"subject"=>isset($toReturn['subjects'][$value->subjectId])?$toReturn['subjects'][$value->subjectId]:"" ) ;
			}
		}elseif($this->data['users']->role == "parent"){
			if($this->data['users']->parentOf != ""){
				$parentOf = json_decode($this->data['users']->parentOf,true);
				if(!is_array($parentOf)){
					$parentOf = array();
				}
				$ids = array();
				foreach($parentOf as $value){
					$ids[] = $value['id'];
				}

				$studentArray = \User::where('role','student')->whereIn('id',$ids)->get();
				foreach ($studentArray as $stOne) {
					$students[$stOne->id] = array('name'=>$stOne->fullName,'studentRollId'=>$stOne->studentRollId);
				}

				if(count($ids) > 0){
					$attendanceArray = \attendance::whereIn('studentId',$ids)->where('date','>=',$startTime)->where('date','<=',$endTime)->orderBy('date','asc')->get();
					foreach ($attendanceArray as $value) {
						if(isset($students[$value->studentId]) AND !isset($toReturn['studentAttendance'][$value->studentId])){
							$toReturn['studentAttendance'][$value->studentId]['n'] = $students[$value->studentId];
							$toReturn['studentAttendance'][$value->studentId]['d'] = array();
						}
						if(isset($toReturn['studentAttendance'][$value->studentId]['d'])){
							$toReturn['studentAttendance'][$value->studentId]['d'][] = array("date"=>$this->panelInit->unix_to_date($value->date),"status"=>$value->status,"subject"=>isset($toReturn['subjects'][$value->subjectId])?$toReturn['subjects'][$value->subjectId]:"" );
						}
					}
				}
			}
		}
		return $toReturn;
	}

	public function biometric(){
		//update devices list
		$update_bio_devices = array();
		$devices_list = \Input::get('devices');
		foreach ($devices_list as $key => $value) {
			$update_bio_devices[] = array("ip"=>$key,"status"=>$value,"time"=>time());
		}

		$device_time = \Input::get('time');

		$update_attendance = \Input::get('attendance');
		
	}

}
