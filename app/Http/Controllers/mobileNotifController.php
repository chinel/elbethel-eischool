<?php
namespace App\Http\Controllers;

class mobileNotifController extends Controller {

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

		if(!$this->panelInit->can( array("mobileNotifications.sendNewNotification") )){
			exit;
		}

	}

	public function listAll($page = 1)
	{		

		$return = array();
		$mobNotifications = \mob_notifications::orderBy('id','desc');
		$return['totalItems'] = $mobNotifications->count();
		$return['subject_list'] = \subject::get();

		$mobNotifications = $mobNotifications->take('20')->skip(20* ($page - 1) )->get()->toArray();
		foreach ($mobNotifications as $value) {
			$value['notifData'] = htmlspecialchars_decode($value['notifData'],ENT_QUOTES);
			$value['notifDate'] = $this->panelInit->unix_to_date($value['notifDate']);
			$return['items'][] = $value;
		}
		return $return;
	}

	public function create(){
		$mobNotifications = new \mob_notifications();

		if(\Input::get('userType') == "users"){

			if(!is_array(\Input::get('selectedUsers')) || ( is_array(\Input::get('selectedUsers')) AND count(\Input::get('selectedUsers')) == 0 ) ){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['mobileNotifications'],"No users selected !");
			}

			$mobNotifications->notifTo = "users";
			$mobNotifications->notifToIds = json_encode(\Input::get('selectedUsers'));
		}elseif(\Input::get('userType') == "students"){
			$mobNotifications->notifTo = "students";
			$mobNotifications->notifToIds = json_encode(\Input::get('classId'));
		}else{
			$mobNotifications->notifTo = \Input::get('userType');
			$mobNotifications->notifToIds = "";
		}

		$mobNotifications->notifData = htmlspecialchars(\Input::get('notifData'),ENT_QUOTES);

		$mobNotifications->notifDate = time();
		$mobNotifications->notifSender = $this->data['users']->fullName . " [ " . $this->data['users']->id . " ] ";
		$mobNotifications->save();


		if(isset($this->panelInit->settingsArray['firebase_apikey']) AND $this->panelInit->settingsArray['firebase_apikey'] != ""){
			//Send the PUSH Notifs.
			
			$users_list = \User::select('firebase_token');
			if(\Input::get('userType') == "users"){
				$usersList = array();
				$selectedUsers = \Input::get('selectedUsers');
				foreach ($selectedUsers as $user) {
					$usersList[] = $user['id'];
				}

				$users_list = $users_list->whereIn('id',$usersList);
			}elseif(\Input::get('userType') == "teachers"){
				$selectedUsersArray =  array();
				$subject = \subject::whereIn('id',\Input::get('subjectId'))->get()->toArray();
				foreach($subject as $value){
					$value['teacherId'] = json_decode($value['teacherId'],true);
					if(is_array($value['teacherId'])){
						foreach($value['teacherId'] as $value_){
							$selectedUsersArray[] = $value_;
						}
					}
				}

				$users_list = $users_list->where('role','teacher')->whereIn('id',$selectedUsersArray);
			}elseif(\Input::get('userType') == "students"){
				
				$users_list = $users_list->where('role','student')->whereIn('studentClass',\Input::get('classId'));
				if(\Input::has('sectionId')){
					$users_list = $users_list->whereIn('studentSection',\Input::get('sectionId'));
				}

			}elseif(\Input::get('userType') == "parents"){
				$users_list = $users_list->where('role','parent');

				$stdInClassIds = \User::where('role','student')->whereIn('studentClass',\Input::get('classId'))->select('id');
				if($this->panelInit->settingsArray['enableSections'] == true){
					$stdInClassIds = $stdInClassIds->whereIn('studentClass',\Input::get('sectionId'));
				}
				$stdInClassIds = $stdInClassIds->get()->toArray();

				$users_list = $users_list->where('role','parent')->where(function ($query) use ($stdInClassIds) {
										foreach($stdInClassIds as $value){
											$query = $query->orWhere('parentOf', 'like', '%"'.$value['id'].'"%');
										}
									});
			}else{
				$users_list = $users_list;
			}
			$users_list = $users_list->get()->toArray();

			//Send Push Notifications
			$tokens_list = array();
			foreach ($users_list as $value) {
				if($value['firebase_token'] != ""){
					$tokens_list[] = $value['firebase_token'];				
				}
			}

			if(count($tokens_list) > 0){
				$this->panelInit->send_push_notification($tokens_list,\Input::get('notifData'));			
			}
			//END of sending real-notifications

		}

		return $this->panelInit->apiOutput(true,$this->panelInit->language['mobileNotifications'],$this->panelInit->language['messQueued'],$this->listAll());
	}

	public function delete($id){
		if ( $postDelete = \mob_notifications::where('id', $id)->first() )
		{
			$postDelete->delete();
			return $this->panelInit->apiOutput(true,"Delete Notification","Notification deleted");
		}else{
			return $this->panelInit->apiOutput(false,"Delete Notification","Notification isn't exist");
		}
	}

	public function list_classes()
	{

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

}
