<?php
namespace App\Http\Controllers;

class employeesController extends Controller {

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
		if(!$this->panelInit->can( array("employees.list","employees.addEmployee","employees.editEmployee","employees.delEmployee") )){
			exit;
		}
		
		$toReturn = array();

		$toReturn['employees'] = \User::where('role','employee')->get();
		
		$toReturn['roles'] = array();
		$roles = \roles::select('id','role_title')->get();
		foreach ($roles as $key => $value) {
			$toReturn['roles'][$value->id] = $value->role_title;
		}

		$toReturn['departments'] = array();
		$departments = \departments::select('id','depart_title')->get();
		foreach ($departments as $key => $value) {
			$toReturn['departments'][$value->id] = $value->depart_title;
		}

		$toReturn['designations'] = array();
		$designations = \designations::select('id','desig_title')->get();
		foreach ($designations as $key => $value) {
			$toReturn['designations'][$value->id] = $value->desig_title;
		}

		return $toReturn;
	}

	public function delete($id){

		if(!$this->panelInit->can( "employees.delEmployee" )){
			exit;
		}

		if ( $postDelete = \User::where('role','employee')->where('id',$id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delEmployee'],$this->panelInit->language['employeeDelSucc']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delEmployee'],$this->panelInit->language['employeeNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "employees.addEmployee" )){
			exit;
		}

		if(\User::where('username',trim(\Input::get('username')))->count() > 0){
			return $this->panelInit->apiOutput(false,$this->panelInit->language['addEmployee'],$this->panelInit->language['usernameAlreadyUsed']);
		}
		if(\User::where('email',\Input::get('email'))->count() > 0){
			return $this->panelInit->apiOutput(false,$this->panelInit->language['addEmployee'],$this->panelInit->language['emailAlreadyUsed']);
		}
		$User = new \User();
		$User->username = \Input::get('username');
		$User->email = \Input::get('email');
		$User->fullName = \Input::get('fullName');
		$User->password = \Hash::make(\Input::get('password'));
		$User->role = "employee";
		if(\Input::has('comVia')){
			$User->comVia = json_encode(\Input::get('comVia'));
		}
		$User->role_perm = \Input::get('role_perm');
		if(\Input::has('department')){
			$User->department = \Input::get('department');
		}
		if(\Input::has('designation')){
			$User->designation = \Input::get('designation');
		}
		$User->save();

		if (\Input::hasFile('photo')) {
			$fileInstance = \Input::file('photo');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['addEmployee'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}

			$newFileName = "profile_".$User->id.".jpg";
			$file = $fileInstance->move('uploads/profile/',$newFileName);

			$User->photo = "profile_".$User->id.".jpg";
			$User->save();
		}

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addEmployee'],$this->panelInit->language['employeeCreated'],$User->toArray());
	}

	function fetch($id){

		if(!$this->panelInit->can( "employees.editEmployee" )){
			exit;
		}

		$user = \User::where('role','employee')->where('id',$id)->first()->toArray();
		$user['comVia'] = json_decode($user['comVia'],true);
		if(!is_array($user['comVia'])){
			$user['comVia'] = array();
		}
		return $user;
	}

	function edit($id){

		if(!$this->panelInit->can( "employees.editEmployee" )){
			exit;
		}

		if(\User::where('username',trim(\Input::get('username')))->where('id','<>',$id)->count() > 0){
			return $this->panelInit->apiOutput(false,$this->panelInit->language['editEmployee'],$this->panelInit->language['usernameAlreadyUsed']);
		}
		if(\User::where('email',\Input::get('email'))->where('id','!=',$id)->count() > 0){
			return $this->panelInit->apiOutput(false,$this->panelInit->language['editEmployee'],$this->panelInit->language['emailAlreadyUsed']);
		}
		$User = \User::find($id);
		$User->username = \Input::get('username');
		$User->email = \Input::get('email');
		$User->fullName = \Input::get('fullName');
		if(\Input::get('password') != ""){
			$User->password = \Hash::make(\Input::get('password'));
		}
		if (\Input::hasFile('photo')) {
			$fileInstance = \Input::file('photo');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['editEmployee'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}
			
			$newFileName = "profile_".$User->id.".jpg";
			$file = $fileInstance->move('uploads/profile/',$newFileName);

			$User->photo = "profile_".$User->id.".jpg";
		}
		if(\Input::has('comVia')){
			$User->comVia = json_encode(\Input::get('comVia'));
		}
		$User->role_perm = \Input::get('role_perm');
		if(\Input::has('department')){
			$User->department = \Input::get('department');
		}
		if(\Input::has('designation')){
			$User->designation = \Input::get('designation');
		}
		$User->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editEmployee'],$this->panelInit->language['employeeUpdated'],$User->toArray());
	}

	function account_status($id){

		if(!$this->panelInit->can( "employees.editEmployee" )){
			exit;
		}

		$user = \User::where('role','employee')->where('id',$id)->first();

		if($id == $this->data['users']->id){
			return $this->panelInit->apiOutput(false,$this->panelInit->language['editEmployee'],$this->panelInit->language['accStatusCantYourself'] );
		}

		if($user->account_active == "1"){
			$user->account_active = "0";
		}else{
			$user->account_active = "1";
		}

		$user->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editEmployee'],$this->panelInit->language['accStatusChged'], array( 'id' => $user->id,'account_active' => $user->account_active ) );
	}
}
