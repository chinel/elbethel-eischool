<?php
namespace App\Http\Controllers;

class feeAllocationController extends Controller {

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

		if(!$this->panelInit->can( array("FeeAllocation.list","FeeAllocation.addFeeAllocation","FeeAllocation.editFeeAllocation","FeeAllocation.delFeeAllocation") )){
			exit;
		}
		
		$toReturn = array();
		$toReturn['classes'] = array();
		$toReturn['classAllocation'] = array();
		$classesIn = array();

		$classes = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->select('id','className')->get();
		foreach ($classes as $class) {
			$toReturn['classes'][$class->id] = $class->className;
		}

		$toReturn['feeAllocation'] = \DB::table('fee_allocation')
								->leftJoin('fee_group','fee_allocation.feeGroup','=','fee_group.id')
								->leftJoin('fee_type','fee_allocation.feeType','=','fee_type.id')
								->select('fee_allocation.id as id',
								'fee_allocation.feeTitle as feeTitle',
								'fee_group.group_title as feeGroup',
								'fee_type.feeTitle as feeType',
								'fee_allocation.feeFor as feeFor')->get();

		$toReturn['feeGroups'] = \fee_group::get()->toArray();
		return $toReturn;
	}

	public function listFeeTypes($id){

		if(!$this->panelInit->can( array("FeeAllocation.addFeeAllocation","FeeAllocation.editFeeAllocation","FeeAllocation.delFeeAllocation") )){
			exit;
		}

		$toReturn = array();
		$fee_type = \fee_type::where('feeGroup',$id)->select('id','feeTitle','feeCode','feeDescription','feeAmount')->get();

		foreach ($fee_type as $key => $value) {
			$toReturn[ $value->id ] = $value;
		}

		return $toReturn;
	}

	public function delete($id){

		if(!$this->panelInit->can( "FeeAllocation.delFeeAllocation" )){
			exit;
		}

		if ( $postDelete = \fee_allocation::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delFeeAllocation'],$this->panelInit->language['feeAllocationDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delFeeAllocation'],$this->panelInit->language['feeAllocationNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "FeeAllocation.addFeeAllocation" )){
			exit;
		}

		$feeTypeNextTS = 0;
		$fee_type = \fee_type::where('id',\Input::get('feeType'))->select('feeSchDetails')->first()->toArray();
		$fee_type['feeSchDetails'] = json_decode($fee_type['feeSchDetails'],true);


		if(is_array($fee_type['feeSchDetails'])){
			$compareTimes = array();
			foreach($fee_type['feeSchDetails'] as $key => $value){
				if($value['date'] >= time()){
					$compareTimes[] = $value['date'];
				}
			}
			if(count($compareTimes) > 0){
				$feeTypeNextTS = min($compareTimes);
			}
		}

		$feeAllocation = new \fee_allocation();
		$feeAllocation->feeTitle = \Input::get('feeTitle');
		$feeAllocation->feeGroup = \Input::get('feeGroup');
		$feeAllocation->feeType = \Input::get('feeType');
		$feeAllocation->feeTypeNextTS = $feeTypeNextTS;
		$feeAllocation->feeFor = \Input::get('feeFor');
		if(\Input::get('feeFor') == "class"){

			$feeForInfo = array();

			if(\Input::has('feeSchDetailsClass')){
				$feeForInfo['class'] = \Input::get('feeSchDetailsClass');
			}

			if(\Input::has('feeSchDetailsClassSection')){
				$feeForInfo['section'] = json_encode(\Input::get('feeSchDetailsClassSection'));
			}

			$feeAllocation->feeForInfo = json_encode($feeForInfo);

		}elseif(\Input::get('feeFor') == "student" && \Input::has('feeSchDetailsStudents')){

			$feeAllocation->feeForInfo = json_encode( \Input::get('feeSchDetailsStudents') );

		}
		$feeAllocation->save();

		if(\Input::get('generateWhen') == "now"){
			$this->panelInit->collectFees( $feeAllocation->id );
		}

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addFeeAllocation'],$this->panelInit->language['feeAllocationAdded'] );
	}

	function fetch($id){

		if(!$this->panelInit->can( "FeeAllocation.editFeeAllocation" )){
			exit;
		}

		$toReturn = array();

		$toReturn['allocation'] = \fee_allocation::where('id',$id)->first()->toArray();
		$toReturn['allocation']['feeForInfo'] = json_decode($toReturn['allocation']['feeForInfo'],true);

		if($toReturn['allocation']['feeFor'] == "class"){
			if(isset($toReturn['allocation']['feeForInfo']['class'])){
				$toReturn['allocation']['feeSchDetailsClass'] = $toReturn['allocation']['feeForInfo']['class'];

				if($this->panelInit->settingsArray['enableSections'] == true){
					$toReturn['sections'] = array();
					$fee_type = \sections::where('classId',$toReturn['allocation']['feeForInfo']['class'])->select('id','sectionName','sectionTitle')->get();
					foreach ($fee_type as $key => $value) {
						$toReturn['sections'][ $value->id ] = $value;
					}
				}

			}
			if(isset($toReturn['allocation']['feeForInfo']['section'])){
				$toReturn['allocation']['feeSchDetailsClassSection'] = $toReturn['allocation']['feeForInfo']['section'];
			}
		}

		if($toReturn['allocation']['feeFor'] == "student" AND is_array($toReturn['allocation']['feeForInfo'])){
			$toReturn['allocation']['feeSchDetailsStudents'] = $toReturn['allocation']['feeForInfo'];
		}

		$toReturn['feeTypes'] = array();
		$fee_type = \fee_type::where('feeGroup',$toReturn['allocation']['feeGroup'])->select('id','feeTitle','feeCode','feeDescription','feeAmount')->get();
		foreach ($fee_type as $key => $value) {
			$toReturn['feeTypes'][ $value->id ] = $value;
		}

		return $toReturn;
	}

	function edit($id){

		if(!$this->panelInit->can( "FeeAllocation.editFeeAllocation" )){
			exit;
		}
		
		$feeTypeNextTS = 0;
		$fee_type = \fee_type::where('id',\Input::get('feeType'))->select('feeSchDetails')->first()->toArray();
		$fee_type['feeSchDetails'] = json_decode($fee_type['feeSchDetails'],true);

		if(is_array($fee_type['feeSchDetails'])){
			$compareTimes = array();
			foreach($fee_type['feeSchDetails'] as $key => $value){
				if($value['date'] >= time()){
					$compareTimes[] = $value['date'];
				}
			}
			if(count($compareTimes) > 0){
				$feeTypeNextTS = min($compareTimes);
			}
		}

		$feeAllocation = \fee_allocation::find($id);
		$feeAllocation->feeTitle = \Input::get('feeTitle');
		$feeAllocation->feeGroup = \Input::get('feeGroup');
		$feeAllocation->feeType = \Input::get('feeType');
		$feeAllocation->feeTypeNextTS = $feeTypeNextTS;
		$feeAllocation->feeFor = \Input::get('feeFor');
		if(\Input::get('feeFor') == "class"){

			$feeForInfo = array();

			if(\Input::has('feeSchDetailsClass')){
				$feeForInfo['class'] = \Input::get('feeSchDetailsClass');
			}

			if(\Input::has('feeSchDetailsClassSection')){
				$feeForInfo['section'] = json_encode(\Input::get('feeSchDetailsClassSection'));
			}

			$feeAllocation->feeForInfo = json_encode($feeForInfo);

		}elseif(\Input::get('feeFor') == "student" && \Input::has('feeSchDetailsStudents')){

			$feeAllocation->feeForInfo = json_encode( \Input::get('feeSchDetailsStudents') );

		}
		$feeAllocation->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editFeeAllocation'],$this->panelInit->language['feeAllocationUpdated'],$feeAllocation->toArray() );
	}
}
