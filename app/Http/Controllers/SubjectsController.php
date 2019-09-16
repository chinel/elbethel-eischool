<?php
namespace App\Http\Controllers;

class SubjectsController extends Controller {

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

		if(!$this->panelInit->can( array("Subjects.list","Subjects.addSubject","Subjects.editSubject","Subjects.delSubject") )){
			exit;
		}

		$toReturn = array();
		$toReturn['subjects'] = \DB::table('subject')
					->leftJoin('users', 'users.id', '=', 'subject.teacherId')
					->select('subject.id as id',
					'subject.subjectTitle as subjectTitle',
					'subject.passGrade as passGrade',
					'subject.finalGrade as finalGrade',
					'subject.teacherId as teacherId',
					'users.fullName as teacherName')
					->get();
		$teachers = \User::where('role','teacher')->select('id','fullName')->get()->toArray();
		foreach ($teachers as $value) {
			$toReturn['teachers'][$value['id']] = $value;
		}
		return $toReturn;
	}

	public function delete($id){

		if(!$this->panelInit->can( "Subjects.delSubject" )){
			exit;
		}

		if ( $postDelete = \subject::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delSubject'],$this->panelInit->language['subjectDel']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delSubject'],$this->panelInit->language['subjectNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "Subjects.addSubject" )){
			exit;
		}

		$subject = new \subject();
		$subject->subjectTitle = \Input::get('subjectTitle');
		$subject->teacherId = json_encode(\Input::get('teacherId'));
		$subject->passGrade = \Input::get('passGrade');
		$subject->finalGrade = \Input::get('finalGrade');
		$subject->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addSubject'],$this->panelInit->language['subjectCreated'],$subject->toArray() );
	}

	function fetch($id){

		if(!$this->panelInit->can( "Subjects.editSubject" )){
			exit;
		}

		$subject = \subject::where('id',$id)->first()->toArray();
		$subject['teacherId'] = json_decode($subject['teacherId'],true);
		return $subject;
	}

	function edit($id){

		if(!$this->panelInit->can( "Subjects.editSubject" )){
			exit;
		}
		
		$subject = \subject::find($id);
		$subject->subjectTitle = \Input::get('subjectTitle');
		$subject->teacherId = json_encode(\Input::get('teacherId'));
		$subject->passGrade = \Input::get('passGrade');
		$subject->finalGrade = \Input::get('finalGrade');
		$subject->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editSubject'],$this->panelInit->language['subjectEdited'],$subject->toArray() );
	}

}
