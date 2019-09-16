<?php
namespace App\Http\Controllers;

class ClassScheduleController extends Controller {

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

		if(!$this->panelInit->can( array("classSch.list","classSch.addSch","classSch.editSch","classSch.delSch") )){
			exit;
		}

		$toReturn = array();
		$toReturn['classes'] = array();
		if($this->panelInit->settingsArray['enableSections'] == true){

			$toReturn['sections'] = array();
			if($this->data['users']->role == "student"){

				$classesIn = array();
				$classes = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get();
				foreach ($classes as $value) {
					$toReturn['classes'][$value->id] = $value->className;
					$classesIn[] = $value->id;
				}

				$sections = \DB::table('sections')
							->select('sections.id as id',
							'sections.sectionName as sectionName',
							'sections.sectionTitle as sectionTitle',
							'sections.classId as classId',
							'sections.teacherId as teacherId')
							->where('id',$this->data['users']->studentSection)
							->get();

				foreach ($sections as $key => $section) {
					$sections[$key]->teacherId = json_decode($sections[$key]->teacherId,true);
					if(isset($toReturn['classes'][$section->classId])){
						$toReturn['sections'][$toReturn['classes'][$section->classId]][] = $section;
					}
				}

			}elseif($this->data['users']->role == "parent"){

				$classesIn = array();
				$classes = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get();
				foreach ($classes as $value) {
					$toReturn['classes'][$value->id] = $value->className;
					$classesIn[] = $value->id;
				}

				$parentOf = json_decode($this->data['users']->parentOf,true);
				if(!is_array($parentOf)){
					$parentOf = array();
				}
				$ids = array();
				foreach($parentOf as $value){
					$ids[] = $value['id'];
				}
				if(count($ids) > 0){
					$studentArray = \User::where('role','student')->whereIn('id',$ids)->get();
					foreach ($studentArray as $stOne) {
						$sectionsId[] = $stOne->studentSection;
					}

					if(count($sectionsId)){
						$sections = \DB::table('sections')
									->select('sections.id as id',
									'sections.sectionName as sectionName',
									'sections.sectionTitle as sectionTitle',
									'sections.classId as classId',
									'sections.teacherId as teacherId')
									->whereIn('id',$sectionsId)
									->get();

						foreach ($sections as $key => $section) {
							$sections[$key]->teacherId = json_decode($sections[$key]->teacherId,true);
							if(isset($toReturn['classes'][$section->classId])){
								$toReturn['sections'][$toReturn['classes'][$section->classId]][] = $section;
							}
						}
					}

				}

			}elseif($this->data['users']->role == "teacher"){

				$classesIn = array();
				$classes = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->where('classTeacher','LIKE','%"'.$this->data['users']->id.'"%')->get();
				foreach ($classes as $value) {
					$toReturn['classes'][$value->id] = $value->className;
					$classesIn[] = $value->id;
				}

				$toReturn['sections'] = array();
				if(count($classesIn) > 0){
					$sections = \DB::table('sections')
								->select('sections.id as id',
								'sections.sectionName as sectionName',
								'sections.sectionTitle as sectionTitle',
								'sections.classId as classId',
								'sections.teacherId as teacherId')
								->whereIn('sections.classId',$classesIn)
								->get();

					foreach ($sections as $key => $section) {
						$sections[$key]->teacherId = json_decode($sections[$key]->teacherId,true);
						if(isset($toReturn['classes'][$section->classId])){
							$toReturn['sections'][$toReturn['classes'][$section->classId]][] = $section;
						}
					}
				}

			}else{

				$classesIn = array();
				$classes = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get();
				foreach ($classes as $value) {
					$toReturn['classes'][$value->id] = $value->className;
					$classesIn[] = $value->id;
				}

				$toReturn['sections'] = array();
				if(count($classesIn) > 0){
					$sections = \DB::table('sections')
								->select('sections.id as id',
								'sections.sectionName as sectionName',
								'sections.sectionTitle as sectionTitle',
								'sections.classId as classId',
								'sections.teacherId as teacherId')
								->whereIn('sections.classId',$classesIn)
								->get();

					foreach ($sections as $key => $section) {
						$sections[$key]->teacherId = json_decode($sections[$key]->teacherId,true);
						if(isset($toReturn['classes'][$section->classId])){
							$toReturn['sections'][$toReturn['classes'][$section->classId]][] = $section;
						}
					}
				}

			}


		}else{
			if($this->data['users']->role == "student"){
				$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->where('id',$this->data['users']->studentClass)->get()->toArray();
			}elseif($this->data['users']->role == "teacher"){
				$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->where('classTeacher','LIKE','%"'.$this->data['users']->id.'"%')->get()->toArray();
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
					if(count($ids) > 0){
						$studentArray = \User::where('role','student')->whereIn('id',$ids)->get();
						foreach ($studentArray as $stOne) {
							$classesIds[] = $stOne->studentClass;
						}
						$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->whereIn('id',$classesIds)->get()->toArray();
					}
				}
			}else{
				$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
			}
		}

		$toReturn['subject'] = \subject::get()->toArray();


		$toReturn['days'] = array(	1=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][1],
							2=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][2],
							3=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][3],
							4=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][4],
							5=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][5],
							6=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][6],
							7=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][7]
						);

		$toReturn['teachers'] = \User::where('role','teacher')->where('activated','1')->select('id','fullName');
		if($this->data['panelInit']->settingsArray['teachersSort'] != ""){
			$toReturn['teachers'] = $toReturn['teachers']->orderByRaw($this->data['panelInit']->settingsArray['teachersSort']);
		}
		$toReturn['teachers'] = $toReturn['teachers']->get();

		$toReturn['userRole'] = $this->data['users']->role;
		return $toReturn;
	}

	function fetch($id){

		if(!$this->panelInit->can( "classSch.list" )){
			exit;
		}

		$arrayOfDays = array(0=>$this->panelInit->language['Sunday'],1=>$this->panelInit->language['Monday'],2=>$this->panelInit->language['Tuesday'],3=>$this->panelInit->language['Wednesday'],4=>$this->panelInit->language['Thurusday'],5=>$this->panelInit->language['Friday'],6=>$this->panelInit->language['Saturday']);

		$subjectArray = array();
		$subjectObject = \subject::get();
		foreach ($subjectObject as $subject) {
			$subjectArray[$subject->id] = $subject->subjectTitle;
		}

		$toReturn = array();

		$toReturn['schedule'] = array(	1=>array('dayName'=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][1],'data'=>array()),
							2=>array('dayName'=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][2],'data'=>array()),
							3=>array('dayName'=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][3],'data'=>array()),
							4=>array('dayName'=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][4],'data'=>array()),
							5=>array('dayName'=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][5],'data'=>array()),
							6=>array('dayName'=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][6],'data'=>array()),
							7=>array('dayName'=>$this->panelInit->weekdays[ $this->panelInit->settingsArray['gcalendar'] ][7],'data'=>array())
						);

		if($this->panelInit->settingsArray['enableSections'] == true){
			$classSchedule = \class_schedule::where('sectionId',$id)->orderBy('startTime')->get();
			foreach ($classSchedule as $schedule) {
				$toReturn['schedule'][$schedule->dayOfWeek]['sub'][] = array('id'=>$schedule->id,'sectionId'=>$schedule->sectionId,'subjectId'=> isset($subjectArray[$schedule->subjectId])?$subjectArray[$schedule->subjectId]:"" ,'start'=>wordwrap($schedule->startTime,2,':',true),'end'=>wordwrap($schedule->endTime,2,':',true) );
			}

			$toReturn['section'] = \sections::where('id',$id)->select('sectionTitle','classId')->first();
			$toReturn['class'] = \classes::where('id',$toReturn['section']->classId)->select('className')->first();
		}else{
			$classSchedule = \class_schedule::where('classId',$id)->orderBy('startTime')->get();
			foreach ($classSchedule as $schedule) {
				$toReturn['schedule'][$schedule->dayOfWeek]['sub'][] = array('id'=>$schedule->id,'classId'=>$schedule->classId,'subjectId'=> isset($subjectArray[$schedule->subjectId])?$subjectArray[$schedule->subjectId]:"" ,'start'=>wordwrap($schedule->startTime,2,':',true),'end'=>wordwrap($schedule->endTime,2,':',true) );
			}

			$toReturn['class'] = \classes::where('id',$id)->select('className')->first();
		}

		return $toReturn;
	}

	function addSub($class){

		if(!$this->panelInit->can( "classSch.addSch" )){
			exit;
		}

		$classSchedule = new \class_schedule();
		if($this->panelInit->settingsArray['enableSections'] == true){
			$classSchedule->sectionId = $class;
		}else{
			$classSchedule->classId = $class;
		}
		$classSchedule->subjectId = \Input::get('subjectId');
		$classSchedule->dayOfWeek = \Input::get('dayOfWeek');
		$classSchedule->teacherId = \Input::get('teacherId');

		$startTime = "";
		if(\Input::get('startTimeHour') < 10){
			$startTime .= "0";
		}
		$startTime .= \Input::get('startTimeHour');
		if(\Input::get('startTimeMin') < 10){
			$startTime .= "0";
		}
		$startTime .= \Input::get('startTimeMin');
		$classSchedule->startTime = $startTime;

		$endTime = "";
		if(\Input::get('endTimeHour') < 10){
			$endTime .= "0";
		}
		$endTime .= \Input::get('endTimeHour');
		if(\Input::get('endTimeMin') < 10){
			$endTime .= "0";
		}
		$endTime .= \Input::get('endTimeMin');
		$classSchedule->endTime = $endTime;
		$classSchedule->save();

		$classSchedule->startTime = wordwrap($classSchedule->startTime,2,':',true);
		$classSchedule->endTime = wordwrap($classSchedule->endTime,2,':',true);
		$classSchedule->subjectId = \subject::where('id',\Input::get('subjectId'))->first()->subjectTitle;

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addSch'],$this->panelInit->language['schCreaSucc'],$classSchedule->toArray() );
	}

	public function delete($class,$id){

		if(!$this->panelInit->can( "classSch.delSch" )){
			exit;
		}

		if ( $postDelete = \class_schedule::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delSch'],$this->panelInit->language['schDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delSch'],$this->panelInit->language['schNotExist']);
        }
	}

	function fetchSub($id){

		if(!$this->panelInit->can( "classSch.editSch" )){
			exit;
		}

		$sub = \class_schedule::where('id',$id)->first()->toArray();
		$sub['startTime'] = str_split($sub['startTime'],2);
		$sub['startTimeHour'] = intval($sub['startTime'][0]);
		$sub['startTimeMin'] = intval($sub['startTime'][1]);

		$sub['endTime'] = str_split($sub['endTime'],2);
		$sub['endTimeHour'] = intval($sub['endTime'][0]);
		$sub['endTimeMin'] = intval($sub['endTime'][1]);

		return json_encode($sub);
	}

	function editSub($id){

		if(!$this->panelInit->can( "classSch.editSch" )){
			exit;
		}

		$classSchedule = \class_schedule::find($id);
		$classSchedule->subjectId = \Input::get('subjectId');
		$classSchedule->dayOfWeek = \Input::get('dayOfWeek');
		$classSchedule->teacherId = \Input::get('teacherId');

		$startTime = "";
		if(\Input::get('startTimeHour') < 10){
			$startTime .= "0";
		}
		$startTime .= \Input::get('startTimeHour');
		if(\Input::get('startTimeMin') < 10){
			$startTime .= "0";
		}
		$startTime .= \Input::get('startTimeMin');
		$classSchedule->startTime = $startTime;

		$endTime = "";
		if(\Input::get('endTimeHour') < 10){
			$endTime .= "0";
		}
		$endTime .= \Input::get('endTimeHour');
		if(\Input::get('endTimeMin') < 10){
			$endTime .= "0";
		}
		$endTime .= \Input::get('endTimeMin');
		$classSchedule->endTime = $endTime;
		$classSchedule->save();

		$classSchedule->startTime = wordwrap($classSchedule->startTime,2,':',true);
		$classSchedule->endTime = wordwrap($classSchedule->endTime,2,':',true);
		$classSchedule->subjectId = \subject::where('id',\Input::get('subjectId'))->first()->subjectTitle;

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editSch'],$this->panelInit->language['schModSucc'],$classSchedule->toArray() );
	}

}
