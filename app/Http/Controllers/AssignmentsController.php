<?php
namespace App\Http\Controllers;

class AssignmentsController extends Controller {

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

		if(!$this->panelInit->can( array("Assignments.list","Assignments.AddAssignments","Assignments.editAssignment","Assignments.delAssignment","Assignments.viewAnswers","Assignments.applyAssAnswer","Assignments.Download") )){
			exit;
		}

		$toReturn = array();

		if($this->data['users']->role == "teacher"){
			$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->where('classTeacher','LIKE','%"'.$this->data['users']->id.'"%')->get()->toArray();
		}else{
			$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
		}

		$classesArray = array();
		foreach ($toReturn['classes'] as $class) {
			$classesArray[$class['id']] = $class['className'];
		}

		$toReturn['assignments'] = array();
		if(count($classesArray) > 0){
			$assignments = new \assignments();

			if($this->data['users']->role == "teacher"){
				$assignments = $assignments->where('teacherId',$this->data['users']->id)->where(function($query) use ($classesArray){
									foreach($classesArray as $key => $value){
										$query = $query->orWhere('classId','LIKE','%"'.$key.'"%');
									}
						        })->orderByRaw('AssignDeadLine + 0 Desc')->get();
			}elseif($this->data['users']->role == "student"){
				$assignments = $assignments->where('classId','LIKE','%"'.$this->data['users']->studentClass.'"%');
				if($this->panelInit->settingsArray['enableSections'] == true){
					$assignments = $assignments->where('sectionId','LIKE','%"'.$this->data['users']->studentSection.'"%');
				}
				$assignments = $assignments->get();
			}elseif ($this->data['users']->role == "parent") {
				$parentOf = json_decode($this->data['users']->parentOf,true);
				if(!is_array($parentOf)){
					$parentOf = array();
				}
				$ids = array();
				foreach ($parentOf as $value) {
					$ids[] = $value['id'];
				}

				if(count($ids) == 0){
					unset($assignments);
				}

				if(count($ids) > 0){
					$classArray = array();
					$sectionArray = array();
					$studentArray = \User::where('role','student')->whereIn('id',$ids)->select('id','fullName','studentClass','studentSection')->get();
					foreach ($studentArray as $stOne) {
						$students[$stOne->id] = array('id'=>$stOne->id,'fullName'=>$stOne->fullName,'studentClass'=>$stOne->studentClass,'studentSection'=>$stOne->studentSection);
						$classArray[] = $stOne->studentClass;
						if($this->panelInit->settingsArray['enableSections'] == true){
							$sectionArray[] = $stOne->studentSection;
						}
					}

					if(count($classArray) > 0){
						$assignments = $assignments->where(function($query) use ($classArray){
											foreach($classArray as $value){
												$query = $query->orWhere('classId','LIKE','%"'.$value.'"%');
											}
								        });
					}

					if(count($sectionArray) > 0){
						$assignments = $assignments->where(function($query) use ($sectionArray){
											foreach($sectionArray as $value){
												$query = $query->orWhere('sectionId','LIKE','%"'.$value.'"%');
											}
								        });
					}
					$assignments = $assignments->get();

					$assignmentsIds = array();
					foreach ($assignments as $assignment) {
						$assignmentsIds[] = $assignment->id;
						$toReturn['assignmentsAnswers'][$assignment->id] = array();

						$classId = json_decode($assignment->classId,true);
						$sectionId = json_decode($assignment->sectionId,true);

						reset($students);
						foreach($students as $student){
							if(is_array($classId) AND in_array($student['studentClass'],$classId) ){
								if(is_array($sectionId) AND in_array($student['studentSection'],$sectionId)){
									$toReturn['assignmentsAnswers'][$assignment->id][$student['id']] = $student;
								}
							}
						}
					}

					if(count($assignmentsIds) > 0){
						$assignmentsAnswers = \DB::table('assignments_answers')
												->leftJoin('users', 'users.id', '=', 'assignments_answers.userId')
												->select('assignments_answers.id as id',
												'assignments_answers.userId as userId',
												'assignments_answers.assignmentId as assignmentId',
												'assignments_answers.userNotes as userNotes',
												'assignments_answers.userTime as userTime',
												'assignments_answers.fileName as AssignFile',
												'users.fullName as fullName')
												->whereIn('assignments_answers.userId',$ids)
												->whereIn('assignments_answers.assignmentId',$assignmentsIds)
												->get();

						foreach ($assignmentsAnswers as $answer) {
							$toReturn['assignmentsAnswers'][$answer->assignmentId][$answer->userId] = $answer;
							$toReturn['assignmentsAnswers'][$answer->assignmentId][$answer->userId]->userTime = $this->panelInit->unix_to_date($answer->userTime);
						}
					}
				}
			}else{
				$assignments = $assignments->where(function($query) use ($classesArray){
									foreach($classesArray as $key => $value){
										$query = $query->orWhere('classId','LIKE','%"'.$key.'"%');
									}
						        })->orderByRaw('AssignDeadLine + 0 Desc')->get();
			}

			$toReturn['userRole'] = $this->data['users']->role;

			if(!isset($assignments)){
				return $toReturn;
			}

			foreach ($assignments as $key => $assignment) {
				$classId = json_decode($assignment->classId);
				if($this->data['users']->role == "student" AND !in_array($this->data['users']->studentClass, $classId)){
					continue;
				}
				$toReturn['assignments'][$key]['id'] = $assignment->id;
				$toReturn['assignments'][$key]['subjectId'] = $assignment->subjectId;
				$toReturn['assignments'][$key]['AssignTitle'] = $assignment->AssignTitle;
				$toReturn['assignments'][$key]['AssignDescription'] = $assignment->AssignDescription;
				$toReturn['assignments'][$key]['AssignFile'] = $assignment->AssignFile;
				$toReturn['assignments'][$key]['AssignDeadLine'] = $this->panelInit->unix_to_date($assignment->AssignDeadLine);
				$toReturn['assignments'][$key]['classes'] = "";

				foreach($classId as $value){
					if(isset($classesArray[$value])) {
						$toReturn['assignments'][$key]['classes'] .= $classesArray[$value].", ";
					}
				}
			}
		}

		return $toReturn;
	}

	public function download($id){

		if(!$this->panelInit->can( array("Assignments.Download") )){
			exit;
		}

		$toReturn = \assignments::where('id',$id)->first();
		if(file_exists('uploads/assignments/'.$toReturn->AssignFile)){
			$fileName = preg_replace('/[^a-zA-Z0-9-_\.]/','-',$toReturn->AssignTitle). "." .pathinfo($toReturn->AssignFile, PATHINFO_EXTENSION);
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename=" . $fileName);
			echo file_get_contents('uploads/assignments/'.$toReturn->AssignFile);
		}else{
			echo "<br/><br/><br/><br/><br/><center>File not exist, Please contact site administrator to reupload it again.</center>";
		}
		exit;
	}

	public function delete($id){

		if(!$this->panelInit->can( "Assignments.delAssignment" )){
			exit;
		}

		if ( $postDelete = \assignments::where('id', $id)->first() )
        {
			@unlink("uploads/assignments/".$postDelete->AssignFile);
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delAssignment'],$this->panelInit->language['assignemntDel']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delAssignment'],$this->panelInit->language['assignemntNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "Assignments.AddAssignments" )){
			exit;
		}

		$assignments = new \assignments();
		$assignments->classId = json_encode(\Input::get('classId'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$assignments->sectionId = json_encode(\Input::get('sectionId'));
		}
		$assignments->subjectId = \Input::get('subjectId');
		$assignments->teacherId = \Input::get('teacherId');
		$assignments->AssignTitle = \Input::get('AssignTitle');
		$assignments->AssignDescription = \Input::get('AssignDescription');
		$assignments->AssignDeadLine = $this->panelInit->date_to_unix(\Input::get('AssignDeadLine'));
		$assignments->teacherId = $this->data['users']->id;
		$assignments->save();
		if (\Input::hasFile('AssignFile')) {
			$fileInstance = \Input::file('AssignFile');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['AddAssignments'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}

			$newFileName = "assignments_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/assignments/',$newFileName);

			$assignments->AssignFile = $newFileName;
			$assignments->save();
		}

		//Send Push Notifications
		$tokens_list = array();
		$user_list = \User::where('role','student')->whereIn('studentClass',\Input::get('classId'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$user_list = $user_list->whereIn('studentSection',\Input::get('sectionId'));
		}
		$user_list = $user_list->select('firebase_token')->get();
		
		foreach ($user_list as $value) {
			if($value['firebase_token'] != ""){
				$tokens_list[] = $value['firebase_token'];				
			}
		}

		if(count($tokens_list) > 0){
			$this->panelInit->send_push_notification($tokens_list,\Input::get('AssignTitle'),$this->panelInit->language['newAssigmentAdded'],"assignment",$assignments->id);			
		}

		$assignments->AssignDeadLine = \Input::get('AssignDeadLine');

		return $this->panelInit->apiOutput(true,$this->panelInit->language['AddAssignments'],$this->panelInit->language['assignmentCreated'],$assignments->toArray());
	}

	function fetch($id){

		if(!$this->panelInit->can( array("Assignments.editAssignment","Assignments.viewAnswers","Assignments.applyAssAnswer") )){
			exit;
		}

		$toReturn = \assignments::where('id',$id)->first();
		$DashboardController = new DashboardController();
		$toReturn['sections'] = $DashboardController->sectionsList(json_decode($toReturn->classId,true));
		$toReturn['subject'] = $DashboardController->subjectList(json_decode($toReturn->classId,true));
		$toReturn->classId = json_decode($toReturn->classId,true);
		$toReturn->AssignDeadLine = $this->panelInit->unix_to_date($toReturn->AssignDeadLine);
		return $toReturn;
	}

	function edit($id){

		if(!$this->panelInit->can( array("Assignments.editAssignment","Assignments.viewAnswers","Assignments.applyAssAnswer") )){
			exit;
		}

		$assignments = \assignments::find($id);
		$assignments->classId = json_encode(\Input::get('classId'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$assignments->sectionId = json_encode(\Input::get('sectionId'));
		}
		$assignments->subjectId = \Input::get('subjectId');
		$assignments->AssignTitle = \Input::get('AssignTitle');
		$assignments->AssignDescription = \Input::get('AssignDescription');
		$assignments->AssignDeadLine = $this->panelInit->date_to_unix(\Input::get('AssignDeadLine'));
		if (\Input::hasFile('AssignFile')) {
			$fileInstance = \Input::file('AssignFile');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['editAssignment'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}
			@unlink("uploads/assignments/".$assignments->AssignFile);

			$newFileName = "assignments_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/assignments/',$newFileName);

			$assignments->AssignFile = $newFileName;
		}
		$assignments->save();

		$assignments->AssignDeadLine = \Input::get('AssignDeadLine');

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editAssignment'],$this->panelInit->language['assignmentModified'],$assignments->toArray());
	}

	function checkUpload(){

		if(!$this->panelInit->can( array("Assignments.editAssignment","Assignments.viewAnswers","Assignments.applyAssAnswer") )){
			exit;
		}

		$toReturn = \assignments::where('id',\Input::get('assignmentId'))->first();

		if($toReturn->AssignDeadLine < time()){
			return $this->panelInit->apiOutput(false,$this->panelInit->language['applyAssAnswer'],$this->panelInit->language['assDeadTime']);
		}

		$assignmentsAnswers = \assignments_answers::where('assignmentId',\Input::get('assignmentId'))->where('userId',$this->data['users']->id)->count();
		if($assignmentsAnswers > 0){
			return $this->panelInit->apiOutput(false,$this->panelInit->language['applyAssAnswer'],$this->panelInit->language['assAlreadySub']);
		}
		return array("canApply"=>"true");
	}

	function upload($id){

		if(!$this->panelInit->can( array("Assignments.viewAnswers","Assignments.applyAssAnswer") )){
			exit;
		}

		$assignmentsAnswers = new \assignments_answers();
		$assignmentsAnswers->assignmentId = $id;
		$assignmentsAnswers->userId = $this->data['users']->id;
		$assignmentsAnswers->userNotes = \Input::get('userNotes');
		$assignmentsAnswers->userTime = time();
		if (!\Input::hasFile('fileName')) {
			return $this->panelInit->apiOutput(false,$this->panelInit->language['applyAssAnswer'],$this->panelInit->language['assNoFilesUploaded']);
		}elseif (\Input::hasFile('fileName')) {
			$fileInstance = \Input::file('fileName');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['applyAssAnswer'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}
			
			$newFileName = "assignments_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/assignmentsAnswers/',$newFileName);

			$assignmentsAnswers->fileName = $newFileName;
			$assignmentsAnswers->save();
		}
		$assignmentsAnswers->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['applyAssAnswer'],$this->panelInit->language['assUploadedSucc']);
	}

	function listAnswers($id){

		if(!$this->panelInit->can( "Assignments.viewAnswers" )){
			exit;
		}

		$assignmentsAnswers = \DB::table('assignments_answers')
								->leftJoin('users', 'users.id', '=', 'assignments_answers.userId')
								->leftJoin('classes', 'classes.id', '=', 'users.studentClass')
								->select('assignments_answers.id as id',
								'assignments_answers.userId as userId',
								'assignments_answers.userNotes as userNotes',
								'assignments_answers.userTime as userTime',
								'assignments_answers.fileName as AssignFile',
								'users.fullName as fullName',
								'classes.className as className')
								->where('assignmentId',$id)
								->get();

		foreach ($assignmentsAnswers as $key => $assignment) {
			$assignmentsAnswers[$key]->userTime = $this->panelInit->unix_to_date($assignmentsAnswers[$key]->userTime , $this->panelInit->settingsArray['dateformat']." hr:mn a");
		}


		return $assignmentsAnswers;
	}

	public function downloadAnswer($id){

		if(!$this->panelInit->can( "Assignments.viewAnswers" )){
			exit;
		}

		$toReturn = \assignments_answers::where('id',$id)->first();
		$user = \User::where('id',$toReturn->userId)->first();
		if(file_exists('uploads/assignmentsAnswers/'.$toReturn->fileName)){
			$fileName = preg_replace('/[^a-zA-Z0-9-_\.]/','-',$user->fullName). "." .pathinfo($toReturn->fileName, PATHINFO_EXTENSION);
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename=" . $fileName);
			echo file_get_contents('uploads/assignmentsAnswers/'.$toReturn->fileName);
		}else{
			echo "<br/><br/><br/><br/><br/><center>File not exist, Please contact site administrator to reupload it again.</center>";
		}
		exit;
	}

}
