<?php
namespace App\Http\Controllers;

class TransportsController extends Controller {

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

		if(!$this->panelInit->can( array("Transportation.list","Transportation.addTransport","Transportation.editTransport","Transportation.delTrans") )){
			exit;
		}

		$toRetuen = array();
		$toRetuen['routes'] = \transportation::get()->toArray();
		foreach ($toRetuen['routes'] as $key => $value) {
			$toRetuen['routes'][$key]['vehicles_list'] = json_decode($toRetuen['routes'][$key]['vehicles_list'],true);
		}
		
		$toRetuen['vehicles'] = array();
		$vehicles = \transport_vehicles::select('id','plate_number','driver_name')->get();
		foreach ($vehicles as $key => $value) {
			$toRetuen['vehicles'][$value->id] = $value->plate_number . " ( " .$value->driver_name ." )";
		}

		return $toRetuen;
	}

	public function delete($id){

		if(!$this->panelInit->can( "Transportation.delTrans" )){
			exit;
		}

		if ( $postDelete = \transportation::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delTrans'],$this->panelInit->language['transDel']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delTrans'],$this->panelInit->language['transNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "Transportation.addTransport" )){
			exit;
		}

		$transportation = new \transportation();
		$transportation->transportTitle = \Input::get('transportTitle');
		$transportation->routeDetails = \Input::get('routeDetails');
		$transportation->vehicles_list = json_encode(\Input::get('vehicles_list'));
		$transportation->transportFare = \Input::get('transportFare');
		$transportation->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addTransport'],$this->panelInit->language['transportCreated'],$transportation->toArray() );
	}

	function fetch($id){
		
		if(!$this->panelInit->can( "Transportation.editTransport" )){
			exit;
		}

		return \transportation::where('id',$id)->first();
	}

	function edit($id){

		if(!$this->panelInit->can( "Transportation.editTransport" )){
			exit;
		}

		$transportation = \transportation::find($id);
		$transportation->transportTitle = \Input::get('transportTitle');
		$transportation->routeDetails = \Input::get('routeDetails');
		$transportation->vehicles_list = json_encode(\Input::get('vehicles_list'));
		$transportation->transportFare = \Input::get('transportFare');
		$transportation->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editTransport'],$this->panelInit->language['transportUpdated'],$transportation->toArray() );
	}

	function members(){

		if(!$this->panelInit->can( "Transportation.members" )){
			exit;
		}

		$toReturn = array();

		$transport_vehicles = array();
		$transport_vehicles_ =  \transport_vehicles::get()->toArray();
		foreach ($transport_vehicles_ as $key => $value) {
			$transport_vehicles[$value['id']] = $value['plate_number'] . " ( " .$value['driver_name'] ." )";
		}

		$toReturn['transports'] = array();
		$toReturn['transport_vehicles'] = array();
		$transports =  \transportation::get()->toArray();
		foreach ($transports as $key => $value) {
			$value['vehicles_list'] = json_decode($value['vehicles_list'],true);
			$toReturn['transports'][$value['id']] = $value;
			$toReturn['transports'][$value['id']]['vehicles'] = array();
			if(is_array($value['vehicles_list'])){
				foreach ($value['vehicles_list'] as $key_ => $value_) {
					if(isset($transport_vehicles[ $value_ ])){
						$toReturn['transports'][$value['id']]['vehicles'][ $value_ ] = $transport_vehicles[ $value_ ];					
					}
				}
			}
			
		}

		return $toReturn;
	}

	function getmembers(){

		if(!$this->panelInit->can( "Transportation.members" )){
			exit;
		}
		
		$users = \User::where('activated','1')->where('transport',\Input::get('transport'))->where('transport_vehicle',\Input::get('transport_vehicle'))->select('id','username','email','fullName','role','studentClass','studentSection','studentRollId')->get()->toArray();

		return $users;
	}
}
