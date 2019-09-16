<?php
namespace App\Http\Controllers;

class homeworksController extends Controller {

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

	public function listAll($page = 1,$search = "")
	{

		if(!$this->panelInit->can( array("Homework.list","Homework.View","Homework.addHomework","Homework.editHomework","Homework.delHomework","Homework.Download") )){
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

		if($this->panelInit->settingsArray['enableSections'] == true){
			$sections = \sections::get()->toArray();
			$sectionsArray = array();
			foreach ($sections as $section) {
				$sectionsArray[$section['id']] = $section['sectionName'];
			}
		}

		$subjects = \subject::get()->toArray();
		$subjectsArray = array();
		foreach ($subjects as $subject) {
			$subjectsArray[$subject['id']] = $subject['subjectTitle'];
		}

		$toReturn['homeworks'] = array();
		if(count($classesArray) > 0){
			$homeworks = new \homeworks();

			if($search != ""){
	            $homeworks = $homeworks->where(function($query) use ($search){
	                        $query->where("homeworkTitle","like","%".$search."%")->orWhere("homeworkDescription","like","%".$search."%");
	                });
	        }
			
			if($this->data['users']->role == "teacher"){

				$homeworks = $homeworks->where('teacherId',$this->data['users']->id);

			}elseif($this->data['users']->role == "student"){

				$homeworks = $homeworks->where('classId','LIKE','%"'.$this->data['users']->studentClass.'"%');
				if($this->panelInit->settingsArray['enableSections'] == true){
					$homeworks = $homeworks->where('sectionId','LIKE','%"'.$this->data['users']->studentSection.'"%');
				}

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
					unset($homeworks);
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
						$homeworks = $homeworks->where(function($query) use ($classArray){
											foreach($classArray as $value){
												$query = $query->orWhere('classId','LIKE','%"'.$value.'"%');
											}
								        });
					}

					if(count($sectionArray) > 0){
						$homeworks = $homeworks->where(function($query) use ($sectionArray){
											foreach($sectionArray as $value){
												$query = $query->orWhere('sectionId','LIKE','%"'.$value.'"%');
											}
								        });
					}

				}
			}
			$homeworks = $homeworks->where(function($query) use ($classesArray){
								foreach($classesArray as $key => $value){
									$query = $query->orWhere('classId','LIKE','%"'.$key.'"%');
								}
					        });

			$toReturn['totalItems'] = $homeworks->count();
			$homeworks = $homeworks->orderByRaw('homeworkDate + 0 Desc')->take('20')->skip(20* ($page - 1) )->get();
			
			$toReturn['userRole'] = $this->data['users']->role;

			if(!isset($homeworks)){
				return $toReturn;
			}

			foreach ($homeworks as $key => $homework) {
				$classId = json_decode($homework->classId);
				if($this->data['users']->role == "student" AND !in_array($this->data['users']->studentClass, $classId)){
					continue;
				}
				$toReturn['homeworks'][$key]['id'] = $homework->id;
				$toReturn['homeworks'][$key]['subjectId'] = $homework->subjectId;
				$toReturn['homeworks'][$key]['homeworkTitle'] = $homework->homeworkTitle;
				$toReturn['homeworks'][$key]['homeworkDescription'] = $homework->homeworkDescription;
				$toReturn['homeworks'][$key]['homeworkFile'] = $homework->homeworkFile;
				$toReturn['homeworks'][$key]['homeworkDate'] = $this->panelInit->unix_to_date($homework->homeworkDate);
				$toReturn['homeworks'][$key]['homeworkSubmissionDate'] = $this->panelInit->unix_to_date($homework->homeworkSubmissionDate);
				$toReturn['homeworks'][$key]['homeworkEvaluationDate'] = $this->panelInit->unix_to_date($homework->homeworkEvaluationDate);
				if(isset($subjectsArray[ $homework->subjectId ])){
					$toReturn['homeworks'][$key]['subject'] = $subjectsArray[ $homework->subjectId ];
				}
				$toReturn['homeworks'][$key]['classes'] = array();

				foreach($classId as $value){
					if(isset($classesArray[$value])) {
						$toReturn['homeworks'][$key]['classes'][] = $classesArray[$value];
					}
				}
				$toReturn['homeworks'][$key]['classes'] = implode(" , ", $toReturn['homeworks'][$key]['classes']);

				if($this->panelInit->settingsArray['enableSections'] == true){
					$toReturn['homeworks'][$key]['sections'] = array();
					
					$sectionId = json_decode($homework->sectionId);
					if(is_array($sectionId)){
						foreach($sectionId as $value){
							if(isset($sectionsArray[$value])) {
								$toReturn['homeworks'][$key]['sections'][] = $sectionsArray[$value];
							}
						}
						$toReturn['homeworks'][$key]['sections'] = implode(" , ", $toReturn['homeworks'][$key]['sections']);
					}					
				}
			}
		}

		return $toReturn;
	}

	public function search($keyword,$page = 1){

        return $this->listAll($page,$keyword);

    }

	public function download($id){

		if(!$this->panelInit->can( "Homework.Download" )){
			exit;
		}

		$toReturn = \homeworks::where('id',$id)->first();
		if(file_exists('uploads/homeworks/'.$toReturn->homeworkFile)){
			$fileName = preg_replace('/[^a-zA-Z0-9-_\.]/','-',$toReturn->homeworkTitle). "." .pathinfo($toReturn->homeworkFile, PATHINFO_EXTENSION);
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename=" . $fileName);
			echo file_get_contents('uploads/homeworks/'.$toReturn->homeworkFile);
		}else{
			echo "<br/><br/><br/><br/><br/><center>File not exist, Please contact site administrator to reupload it again.</center>";
		}
		exit;
	}

	public function delete($id){

		if(!$this->panelInit->can( "Homework.delHomework" )){
			exit;
		}

		if ( $postDelete = \homeworks::where('id', $id)->first() )
        {
			@unlink("uploads/homeworks/".$postDelete->homeworkFile);
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delHomework'],$this->panelInit->language['HomeworkDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delHomework'],$this->panelInit->language['HomeworknotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "Homework.addHomework" )){
			exit;
		}

		$homeworks = new \homeworks();
		$homeworks->classId = json_encode(\Input::get('classId'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$homeworks->sectionId = json_encode(\Input::get('sectionId'));
		}
		$homeworks->subjectId = \Input::get('subjectId');
		$homeworks->teacherId = $this->data['users']->id;
		$homeworks->homeworkTitle = \Input::get('homeworkTitle');
		$homeworks->homeworkDescription = \Input::get('homeworkDescription');
		$homeworks->homeworkDate = time();
		$homeworks->homeworkSubmissionDate = $this->panelInit->date_to_unix(\Input::get('homeworkSubmissionDate'));
		$homeworks->homeworkEvaluationDate = $this->panelInit->date_to_unix(\Input::get('homeworkEvaluationDate'));
		$homeworks->teacherId = $this->data['users']->id;
		$homeworks->save();
		if (\Input::hasFile('homeworkFile')) {
			$fileInstance = \Input::file('homeworkFile');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['addHomework'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}

			$newFileName = "homeworks_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/homeworks/',$newFileName);

			$homeworks->homeworkFile = $newFileName;
			$homeworks->save();
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
			$this->panelInit->send_push_notification($tokens_list,\Input::get('homeworkTitle'),$this->panelInit->language['homeworkNotif'],"homework",$homeworks->id);			
		}

		$homeworks->homeworkDate = $this->panelInit->unix_to_date( time() );
		$homeworks->homeworkEvaluationDate = \Input::get('homeworkEvaluationDate');
		$homeworks->homeworkEvaluationDate = \Input::get('homeworkEvaluationDate');

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addHomework'],$this->panelInit->language['HomeworkCreated'],$homeworks->toArray());
	}

	function fetch($id){

		if(!$this->panelInit->can( "Homework.editHomework" )){
			exit;
		}

		$toReturn = \homeworks::where('id',$id)->first();

		$DashboardController = new DashboardController();
		$toReturn['sections'] = $DashboardController->sectionsList(json_decode($toReturn->classId,true));
		$toReturn['subject'] = $DashboardController->subjectList(json_decode($toReturn->classId,true));

		$toReturn->classId = json_decode($toReturn->classId,true);

		$toReturn->homeworkDate = $this->panelInit->unix_to_date($toReturn->homeworkDate);
		$toReturn->homeworkSubmissionDate = $this->panelInit->unix_to_date($toReturn->homeworkSubmissionDate);
		$toReturn->homeworkEvaluationDate = $this->panelInit->unix_to_date($toReturn->homeworkEvaluationDate);

		return $toReturn;
	}

	function fetch_view($id){

		if(!$this->panelInit->can( "Homework.View" )){
			exit;
		}

		$toReturn = \homeworks::where('id',$id)->first()->toArray();

		$toReturn['homeworkDate'] = $this->panelInit->unix_to_date($toReturn['homeworkDate']);
		$toReturn['homeworkSubmissionDate'] = $this->panelInit->unix_to_date($toReturn['homeworkSubmissionDate']);
		$toReturn['homeworkEvaluationDate'] = $this->panelInit->unix_to_date($toReturn['homeworkEvaluationDate']);

		//Get Classes
		$toReturn['classId'] = json_decode($toReturn['classId'],true);
		if(is_array($toReturn['classId']) AND count($toReturn['classId']) > 0){
			$toReturn['classes'] = \classes::whereIn('id',$toReturn['classId'])->select('className')->get();
		}

		//Get Sections
		if($this->panelInit->settingsArray['enableSections'] == true){
			$toReturn['sectionId'] = json_decode($toReturn['sectionId'],true);
			if(is_array($toReturn['sectionId']) AND count($toReturn['sectionId']) > 0){
				$toReturn['sections'] = \sections::whereIn('id',$toReturn['sectionId'])->select('sectionName')->get();
			}
		}

		//Get Subject
		if($toReturn['subjectId'] != ""){
			$toReturn['subject'] = \subject::where('id',$toReturn['subjectId'])->select('subjectTitle')->first();
		}

		//Get Teacher
		if($toReturn['teacherId'] != ""){
			$toReturn['teacher'] = \User::where('id',$toReturn['teacherId'])->select('fullName')->first();
		}

		$studentsCompleted = json_decode($toReturn['studentsCompleted'],true);
		
		if($this->panelInit->can( "Homework.Answers" )){
			$toReturn['student_applied'] = array();
			$toReturn['student_not_applied'] = array();

			$class_users = \User::whereIn('studentClass',$toReturn['classId']);
			if($this->panelInit->settingsArray['enableSections'] == true){
				$class_users = $class_users->whereIn('studentSection',$toReturn['sectionId']);
			}
			$class_users = $class_users->select('id','fullName')->get();

			foreach ($class_users as $key => $value) {
				if(is_array($studentsCompleted) AND in_array($value->id, $studentsCompleted)){
					$toReturn['student_applied'][ $value->id ] = $value->fullName;
				}else{
					$toReturn['student_not_applied'][ $value->id ] = $value->fullName;
				}
			}
		}else{

			if($this->data['users']->role == "student"){
				$toReturn['student_applied'] = array();
				$toReturn['student_not_applied'] = array();

				if(is_array($studentsCompleted) AND in_array($this->data['users']->id, $studentsCompleted)){
					$toReturn['student_applied'][ $this->data['users']->id ] = $this->data['users']->fullName;
				}else{
					$toReturn['student_not_applied'][ $this->data['users']->id ] = $this->data['users']->fullName;
				}
			}


			if($this->data['users']->id == "parent"){
				$toReturn['student_applied'] = array();
				$toReturn['student_not_applied'] = array();

				$parentOf = json_decode($this->data['users']->parentOf,true);
				if(!is_array($parentOf)){
					$parentOf = array();
				}
				$ids = array();
				foreach ($parentOf as $value) {
					$ids[] = $value['id'];
				}

				if(count($ids) > 0){
					$parets_students = \User::whereIn('id',$ids)->select('id','fullName')->get();
					foreach ($parets_students as $key => $value) {

						if(is_array($studentsCompleted) AND in_array($value->id, $studentsCompleted)){
							$toReturn['student_applied'][ $value->id ] = $value->fullName;
						}else{
							$toReturn['student_not_applied'][ $value->id ] = $value->fullName;
						}

					}
				}

			}
		}

		return $toReturn;
	}

	function edit($id){

		if(!$this->panelInit->can( "Homework.editHomework" )){
			exit;
		}

		$homeworks = \homeworks::find($id);
		$homeworks->classId = json_encode(\Input::get('classId'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$homeworks->sectionId = json_encode(\Input::get('sectionId'));
		}
		$homeworks->subjectId = \Input::get('subjectId');
		$homeworks->homeworkTitle = \Input::get('homeworkTitle');
		$homeworks->homeworkDescription = \Input::get('homeworkDescription');
		$homeworks->homeworkDate = time();
		$homeworks->homeworkSubmissionDate = $this->panelInit->date_to_unix(\Input::get('homeworkSubmissionDate'));
		$homeworks->homeworkEvaluationDate = $this->panelInit->date_to_unix(\Input::get('homeworkEvaluationDate'));
		if (\Input::hasFile('homeworkFile')) {
			$fileInstance = \Input::file('homeworkFile');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['editHomework'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}
			@unlink("uploads/homeworks/".$homeworks->homeworkFile);
			
			$newFileName = "assignments_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/homeworks/',$newFileName);

			$homeworks->homeworkFile = $newFileName;
		}
		$homeworks->save();

		$homeworks->homeworkDate = $this->panelInit->unix_to_date( $homeworks->homeworkDate );
		$homeworks->homeworkEvaluationDate = \Input::get('homeworkEvaluationDate');
		$homeworks->homeworkEvaluationDate = \Input::get('homeworkEvaluationDate');

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editHomework'],$this->panelInit->language['HomeworkModified'],$homeworks->toArray());
	}

	function apply($id){

		if(!$this->panelInit->can( "Homework.Answers" )){
			exit;
		}

		$homeworks = \homeworks::find($id);
		$studentsCompleted = json_decode($homeworks->studentsCompleted,true);

		if(!is_array($studentsCompleted)){
			$studentsCompleted = array();
		}

		if(\Input::get('status') == 1 AND !in_array(\Input::get('student'), $studentsCompleted)){
			$studentsCompleted[] = \Input::get('student');
		}
		if(\Input::get('status') == 0 AND ($key = array_search(\Input::get('student'), $studentsCompleted)) !== false){
		    unset($studentsCompleted[$key]);
		}
		$homeworks->studentsCompleted = json_encode($studentsCompleted);
		$homeworks->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editHomework'],$this->panelInit->language['HomeworkModified'],$this->fetch_view($id));
	}

}
