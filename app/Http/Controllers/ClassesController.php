<?php
namespace App\Http\Controllers;

class ClassesController extends Controller {

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

		if(!$this->panelInit->can( array("classes.list","classes.addClass","classes.editClass","classes.delClass") )){
			exit;
		}

		$toReturn = array();
		$teachers = \User::where('role','teacher')->select('id','fullName')->get()->toArray();
		$toReturn['dormitory'] =  \dormitories::get()->toArray();

		$toReturn['subject'] = array();
		$subjects =  \subject::get();
		foreach ($subjects as $value) {
		    $toReturn['subject'][$value->id] = $value->subjectTitle;
		}

		$toReturn['classes'] = array();
		$classes = \classes::leftJoin('dormitories', 'dormitories.id', '=', 'classes.dormitoryId')
					->select('classes.id as id',
					'classes.className as className',
					'classes.classTeacher as classTeacher',
					'classes.classSubjects as classSubjects',
					'dormitories.id as dormitory',
					'dormitories.dormitory as dormitoryName');

		if($this->data['users']->role == "teacher"){
			$classes = $classes->where('classTeacher','LIKE','%"'.$this->data['users']->id.'"%');
		}

		$classes = $classes->where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();

		$toReturn['teachers'] = array();
		foreach($teachers as $teacherKey => $teacherValue){
			$toReturn['teachers'][$teacherValue['id']] = $teacherValue;
		}

		foreach($classes as $key => $class){
			$toReturn['classes'][$key] = $class;
			
			$toReturn['classes'][$key]['classSubjects'] = json_decode($toReturn['classes'][$key]['classSubjects'],true);
			$toReturn['classes'][$key]['classTeacher'] = json_decode($toReturn['classes'][$key]['classTeacher'],true);
		}

		return $toReturn;
	}

	public function delete($id){

		if(!$this->panelInit->can( "classes.delClass" )){
			exit;
		}

		if ( $postDelete = \classes::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delClass'],$this->panelInit->language['classDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delClass'],$this->panelInit->language['classNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "classes.addClass" )){
			exit;
		}

		$classes = new \classes();
		$classes->className = \Input::get('className');
		$classes->classTeacher = json_encode(\Input::get('classTeacher'));
		$classes->classAcademicYear = $this->panelInit->selectAcYear;
		$classes->classSubjects = json_encode(\Input::get('classSubjects'));
		if(\Input::has('dormitoryId')){
			$classes->dormitoryId = \Input::get('dormitoryId');
		}
		$classes->save();

		$classes->classTeacher = "";
		$teachersList = \User::whereIn('id',\Input::get('classTeacher'))->get();
		foreach ($teachersList as $teacher) {
			$classes->classTeacher .= $teacher->fullName.", ";
		}
		$classes->classSubjects = json_decode($classes->classSubjects);

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addClass'],$this->panelInit->language['classCreated'],$classes->toArray() );
	}

	function fetch($id){

		if(!$this->panelInit->can( "classes.editClass" )){
			exit;
		}

		$classDetail = \classes::where('id',$id)->first()->toArray();
		$classDetail['classTeacher'] = json_decode($classDetail['classTeacher']);
		$classDetail['classSubjects'] = json_decode($classDetail['classSubjects']);
		return $classDetail;
	}

	function edit($id){

		if(!$this->panelInit->can( "classes.editClass" )){
			exit;
		}

		$classes = \classes::find($id);
		$classes->className = \Input::get('className');
		$classes->classTeacher = json_encode(\Input::get('classTeacher'));
		$classes->classSubjects = json_encode(\Input::get('classSubjects'));
		if(\Input::has('dormitoryId')){
			$classes->dormitoryId = \Input::get('dormitoryId');
		}
		$classes->save();

		$classes->classTeacher = "";
		$teachersList = \User::whereIn('id',\Input::get('classTeacher'))->get();
		foreach ($teachersList as $teacher) {
			$classes->classTeacher .= $teacher->fullName.", ";
		}
		$classes->classSubjects = json_decode($classes->classSubjects);

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editClass'],$this->panelInit->language['classUpdated'],$classes->toArray() );
	}

}
