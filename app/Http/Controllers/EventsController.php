<?php
namespace App\Http\Controllers;

class EventsController extends Controller {

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

		if(!$this->panelInit->can( array("events.list","events.View","events.addEvent","events.editEvent","events.delEvent") )){
			exit;
		}

		$toReturn = array();
		if($this->data['users']->role == "admin" ){
			$toReturn['events'] = \events::orderby('eventDate','DESC')->get()->toArray();
		}else{
			$toReturn['events'] = \events::where('eventFor',$this->data['users']->role)->orWhere('eventFor','all')->orderby('eventDate','DESC')->get()->toArray();
		}

		foreach ($toReturn['events'] as $key => $item) {
			$toReturn['events'][$key]['eventDescription'] = strip_tags(htmlspecialchars_decode($toReturn['events'][$key]['eventDescription'],ENT_QUOTES));
			$toReturn['events'][$key]['eventDate'] = $this->panelInit->unix_to_date($toReturn['events'][$key]['eventDate']);
		}

		return $toReturn;
	}

	public function delete($id){

		if(!$this->panelInit->can( "events.delEvent" )){
			exit;
		}

		if ( $postDelete = \events::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delEvent'],$this->panelInit->language['eventDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delEvent'],$this->panelInit->language['eventNotEist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "events.addEvent" )){
			exit;
		}

		$events = new \events();
		$events->eventTitle = \Input::get('eventTitle');
		$events->eventDescription = htmlspecialchars(\Input::get('eventDescription'),ENT_QUOTES);
		$events->eventFor = \Input::get('eventFor');
		$events->enentPlace = \Input::get('enentPlace');
		$events->eventDate = $this->panelInit->date_to_unix(\Input::get('eventDate'));
		$events->fe_active = \Input::get('fe_active');

		if (\Input::hasFile('eventImage')) {
			$fileInstance = \Input::file('eventImage');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['addEvent'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}

			$newFileName = uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/events/',$newFileName);

			$events->eventImage = $newFileName;
		}

		$events->save();

		//Send Push Notifications
		$tokens_list = array();
		if($events->eventFor == "all"){
			$user_list = \User::select('firebase_token')->get();
		}else{
			$user_list = \User::where('role',$events->eventFor)->select('firebase_token')->get();
		}
		foreach ($user_list as $value) {
			if($value['firebase_token'] != ""){
				$tokens_list[] = $value['firebase_token'];				
			}
		}

		if(count($tokens_list) > 0){
			$eventDescription = strip_tags(\Input::get('eventDescription'));
			$this->panelInit->send_push_notification($tokens_list,$eventDescription,$events->eventTitle,"events",$events->id);			
		}

		$events->eventDescription = strip_tags(htmlspecialchars_decode($events->eventDescription));
		$events->eventDate = $this->panelInit->unix_to_date($events->eventDate);

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addEvent'],$this->panelInit->language['eventCreated'],$events->toArray() );
	}

	function fetch($id){

		if(!$this->panelInit->can( array("events.View","events.editEvent") )){
			exit;
		}

		$data = \events::where('id',$id)->first()->toArray();
		$data['eventDescription'] = htmlspecialchars_decode($data['eventDescription'],ENT_QUOTES);
		$data['eventDate'] = $this->panelInit->unix_to_date($data['eventDate']);
		return json_encode($data);
	}

	function edit($id){

		if(!$this->panelInit->can( "events.editEvent" )){
			exit;
		}

		$events = \events::find($id);
		$events->eventTitle = \Input::get('eventTitle');
		$events->eventDescription = htmlspecialchars(\Input::get('eventDescription'),ENT_QUOTES);
		$events->eventFor = \Input::get('eventFor');
		$events->enentPlace = \Input::get('enentPlace');
		$events->eventDate = $this->panelInit->date_to_unix(\Input::get('eventDate'));
		$events->fe_active = \Input::get('fe_active');

		if (\Input::hasFile('eventImage')) {
			$fileInstance = \Input::file('eventImage');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['editEvent'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}
			
			$newFileName = uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/events/',$newFileName);

			$events->eventImage = $newFileName;
		}

		$events->save();

		$events->eventDescription = strip_tags(htmlspecialchars_decode($events->eventDescription));
		$events->eventDate = $this->panelInit->unix_to_date($events->eventDate);

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editEvent'],$this->panelInit->language['eventModified'],$events->toArray() );
	}

	function fe_active($id){

		if(!$this->panelInit->can( "events.editEvent" )){
			exit;
		}

		$events = \events::find($id);
		
		if($events->fe_active == 1){
			$events->fe_active = 0;
		}else{
			$events->fe_active = 1;
		}

		$events->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editEvent'],$this->panelInit->language['eventModified'], array("fe_active"=>$events->fe_active) );
	}
}
