<?php
namespace App\Http\Controllers;

class feeTypesController extends Controller {

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

		if(!$this->panelInit->can( array("FeeTypes.list","FeeTypes.addFeeType","FeeTypes.editFeeType","FeeTypes.delFeeType") )){
			exit;
		}
		
		$toReturn = array();
		$toReturn['types'] = \fee_type::get();
		$toReturn['groups'] = \fee_group::get();

		return $toReturn;
	}

	public function delete($id){

		if(!$this->panelInit->can( "FeeTypes.delFeeType" )){
			exit;
		}

		if ( $postDelete = \fee_type::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delFeeType'],$this->panelInit->language['feeDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delFeeType'],$this->panelInit->language['feeNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "FeeTypes.addFeeType" )){
			exit;
		}

		if(\Input::has('feeSchDetails')){
			$feeSchDetails = \Input::get('feeSchDetails');
			foreach($feeSchDetails as $key => $value){
				$feeSchDetails[$key]['date'] = $this->panelInit->date_to_unix($value['date']);
				$feeSchDetails[$key]['due'] = $this->panelInit->date_to_unix($value['due']);
			}
		}else{
			$feeSchDetails = array();
		}

		$feeType = new \fee_type();
		$feeType->feeTitle = \Input::get('feeTitle');
		if(\Input::has('feeCode')){
			$feeType->feeCode = \Input::get('feeCode');
		}
		if(\Input::has('feeDescription')){
			$feeType->feeDescription = \Input::get('feeDescription');
		}
		$feeType->feeGroup = \Input::get('feeGroup');
		$feeType->feeAmount = \Input::get('feeAmount');
		$feeType->feeSchType = \Input::get('feeSchType');
		$feeType->feeSchDetails = json_encode($feeSchDetails);
		$feeType->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addFeeType'],$this->panelInit->language['feeAdded'],$feeType->toArray() );
	}

	function fetch($id){

		if(!$this->panelInit->can( "FeeTypes.editFeeType" )){
			exit;
		}

		$fee_type = \fee_type::where('id',$id)->first()->toArray();
		$fee_type['feeSchDetails'] = json_decode($fee_type['feeSchDetails'],true);

		if(is_array($fee_type['feeSchDetails'])){
			foreach($fee_type['feeSchDetails'] as $key => $value){
				$fee_type['feeSchDetails'][$key]['date'] = $this->panelInit->unix_to_date($value['date']);
				$fee_type['feeSchDetails'][$key]['due'] = $this->panelInit->unix_to_date($value['due']);
			}
		}

		return $fee_type;
	}

	function edit($id){

		if(!$this->panelInit->can( "FeeTypes.editFeeType" )){
			exit;
		}
		
		$feeTypeNextTS = 0;

		if(\Input::has('feeSchDetails')){
			$compareTimes = array();
			$feeSchDetails = \Input::get('feeSchDetails');
			foreach($feeSchDetails as $key => $value){
				$feeSchDetails[$key]['date'] = $this->panelInit->date_to_unix($value['date']);
				$feeSchDetails[$key]['due'] = $this->panelInit->date_to_unix($value['due']);
				if($feeSchDetails[$key]['date'] >= time()){
					$compareTimes[] = $feeSchDetails[$key]['date'];
				}
			}
			if( count($compareTimes) > 0 ){
				$feeTypeNextTS = min($compareTimes);
			}
		}else{
			$feeSchDetails = array();
		}

		$feeType = \fee_type::find($id);
		$feeType->feeTitle = \Input::get('feeTitle');
		if(\Input::has('feeCode')){
			$feeType->feeCode = \Input::get('feeCode');
		}
		if(\Input::has('feeDescription')){
			$feeType->feeDescription = \Input::get('feeDescription');
		}
		$feeType->feeGroup = \Input::get('feeGroup');
		$feeType->feeAmount = \Input::get('feeAmount');
		$feeType->feeSchType = \Input::get('feeSchType');
		$feeType->feeSchDetails = json_encode($feeSchDetails);
		$feeType->save();

		$toReturn['feeAllocation'] = \fee_allocation::where('feeType',$feeType->id)->update( array('feeTypeNextTS'=>$feeTypeNextTS) );

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editFeeType'],$this->panelInit->language['feeUpdated'],$feeType->toArray() );
	}
}
