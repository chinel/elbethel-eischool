<?php
namespace App\Http\Controllers;

class StudyMaterialController extends Controller {

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
		$this->data['users'] = $this->panelInit->getAuthUser();
		if(!isset($this->data['users']->id)){
			return \Redirect::to('/');
		}

	}

	public function listAll()
	{

		if(!$this->panelInit->can( array("studyMaterial.list","studyMaterial.addMaterial","studyMaterial.editMaterial","studyMaterial.delMaterial","studyMaterial.Download") )){
			exit;
		}

		$toReturn = array();

		if($this->data['users']->role == "teacher"){
			$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->where('classTeacher','LIKE','%"'.$this->data['users']->id.'"%')->get()->toArray();
		}else{
			$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
		}

		$classesArray = array();

		foreach($toReturn['classes'] as $class){
			$classesArray[$class['id']] = $class['className'];
		}

		$subjects = \subject::get()->toArray();
		$subjectArray = array();
		foreach($subjects as $subject){
			$subjectArray[$subject['id']] = $subject['subjectTitle'];
		}

		$toReturn['materials'] = array();
		$studyMaterial = new \study_material();

		if($this->data['users']->role == "student"){
			$studyMaterial = $studyMaterial->where('class_id','LIKE','%"'.$this->data['users']->studentClass.'"%');
			if($this->panelInit->settingsArray['enableSections'] == true){
				$studyMaterial = $studyMaterial->where('sectionId','LIKE','%"'.$this->data['users']->studentSection.'"%');
			}
		}

		if($this->data['users']->role == "teacher"){
			$studyMaterial = $studyMaterial->where('teacher_id',$this->data['users']->id);
		}

		$studyMaterial = $studyMaterial->get();

		foreach ($studyMaterial as $key => $material) {
			$classId = json_decode($material->class_id);
			if($this->data['users']->role == "student" AND !in_array($this->data['users']->studentClass, $classId)){
				continue;
			}
			$toReturn['materials'][$key]['id'] = $material->id;
			$toReturn['materials'][$key]['subjectId'] = $material->subject_id;
			if(isset($subjectArray[$material->subject_id])){
				$toReturn['materials'][$key]['subject'] = $subjectArray[$material->subject_id];
			}else{
				$toReturn['materials'][$key]['subject'] = "";
			}
			$toReturn['materials'][$key]['material_title'] = $material->material_title;
			$toReturn['materials'][$key]['material_description'] = $material->material_description;
			$toReturn['materials'][$key]['material_file'] = $material->material_file;
			$toReturn['materials'][$key]['classes'] = "";

            if(is_array($classId)){
            	foreach($classId as $value){
    				if(isset($classesArray[$value])) {
    					$toReturn['materials'][$key]['classes'] .= $classesArray[$value].", ";
    				}
    			}
            }
		}

		$toReturn['userRole'] = $this->data['users']->role;
		return $toReturn;
		exit;
	}

	public function delete($id){

		if(!$this->panelInit->can( "studyMaterial.delMaterial" )){
			exit;
		}

		if ( $postDelete = \study_material::where('id', $id)->first() )
        {
			@unlink('uploads/studyMaterial/'.$postDelete->material_file);
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delMaterial'],$this->panelInit->language['materialDel']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delMaterial'],$this->panelInit->language['materialNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "studyMaterial.addMaterial" )){
			exit;
		}

		$studyMaterial = new \study_material();
		$studyMaterial->class_id = json_encode(\Input::get('class_id'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$studyMaterial->sectionId = json_encode(\Input::get('sectionId'));
		}
		$studyMaterial->subject_id = \Input::get('subject_id');
		$studyMaterial->material_title = \Input::get('material_title');
		$studyMaterial->material_description = \Input::get('material_description');
		$studyMaterial->teacher_id = $this->data['users']->id;
		$studyMaterial->save();
		if (\Input::hasFile('material_file')) {
			$fileInstance = \Input::file('material_file');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['addMaterial'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}

			$newFileName = "material_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/studyMaterial/',$newFileName);

			$studyMaterial->material_file = $newFileName;
			$studyMaterial->save();
		}

		//Send Push Notifications
		$tokens_list = array();
		$user_list = \User::where('role','student')->whereIn('studentClass',\Input::get('class_id'));
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
			$this->panelInit->send_push_notification($tokens_list,$this->panelInit->language['materialNotif']." : ".\Input::get('material_title'),$this->panelInit->language['studyMaterial'],"material");			
		}

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addMaterial'],$this->panelInit->language['materialAdded'],$studyMaterial->toArray() );
	}

	function fetch($id){

		if(!$this->panelInit->can( "studyMaterial.editMaterial" )){
			exit;
		}

		$studyMaterial = \study_material::where('id',$id)->first()->toArray();
		$DashboardController = new DashboardController();
		$studyMaterial['sections'] = $DashboardController->sectionsList(json_decode($studyMaterial['class_id'],true));
		$studyMaterial['subject'] = $DashboardController->subjectList(json_decode($studyMaterial['class_id'],true));
		$studyMaterial['class_id'] = json_decode($studyMaterial['class_id'],true);
		return $studyMaterial;
	}

	public function download($id){

		if(!$this->panelInit->can( "studyMaterial.Download" )){
			exit;
		}
		

		$toReturn = \study_material::where('id',$id)->first();
		if(file_exists('uploads/studyMaterial/'.$toReturn->material_file)){
			$fileName = preg_replace('/[^a-zA-Z0-9-_\.]/','',$toReturn->material_title). "." .pathinfo($toReturn->material_file, PATHINFO_EXTENSION);
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename=" . $fileName);
			echo file_get_contents('uploads/studyMaterial/'.$toReturn->material_file);
		}else{
			echo "<br/><br/><br/><br/><br/><center>File not exist, Please contact site administrator to reupload it again.</center>";
		}
		exit;
	}

	function edit($id){

		if(!$this->panelInit->can( "studyMaterial.editMaterial" )){
			exit;
		}
		
		$studyMaterial = \study_material::find($id);
		$studyMaterial->class_id = json_encode(\Input::get('class_id'));
		if($this->panelInit->settingsArray['enableSections'] == true){
			$studyMaterial->sectionId = json_encode(\Input::get('sectionId'));
		}
		$studyMaterial->subject_id = \Input::get('subject_id');
		$studyMaterial->material_title = \Input::get('material_title');
		$studyMaterial->material_description = \Input::get('material_description');
		if (\Input::hasFile('material_file')) {
			$fileInstance = \Input::file('material_file');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['editMaterial'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}
			@unlink("uploads/studyMaterial/".$studyMaterial->material_file);
			
			$newFileName = "material_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/studyMaterial/',$newFileName);

			$studyMaterial->material_file = $newFileName;
		}
		$studyMaterial->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editMaterial'],$this->panelInit->language['materialEdited'],$studyMaterial->toArray() );
	}
}
