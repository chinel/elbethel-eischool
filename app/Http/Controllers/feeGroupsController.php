<?php
namespace App\Http\Controllers;

class feeGroupsController extends Controller {

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
		if(!$this->panelInit->can( array("FeeGroups.list","FeeGroups.addFeeGroup","FeeGroups.editFeeGroup","FeeGroups.delFeeGroup") )){
			exit;
		}

		return \fee_group::get();
	}

	public function delete($id){

		if(!$this->panelInit->can( "FeeGroups.delFeeGroup" )){
			exit;
		}

		if ( $postDelete = \fee_group::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delFeeGroup'],$this->panelInit->language['feeGroupDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delFeeGroup'],$this->panelInit->language['feeGroupNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "FeeGroups.addFeeGroup" )){
			exit;
		}

		$feeGroup = new \fee_group();
		$feeGroup->group_title = \Input::get('group_title');
		if(\Input::has('group_description')){
			$feeGroup->group_description = \Input::get('group_description');
		}
		$feeGroup->invoice_prefix = \Input::get('invoice_prefix');
		$feeGroup->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addFeeGroup'],$this->panelInit->language['feeGroupAdded'],$feeGroup->toArray() );
	}

	function fetch($id){

		if(!$this->panelInit->can( "FeeGroups.editFeeGroup" )){
			exit;
		}

		return \fee_group::where('id',$id)->first();
	}

	function edit($id){

		if(!$this->panelInit->can( "FeeGroups.editFeeGroup" )){
			exit;
		}
		
		$feeGroup = \fee_group::find($id);
		$feeGroup->group_title = \Input::get('group_title');
		$feeGroup->group_description = \Input::get('group_description');
		$feeGroup->invoice_prefix = \Input::get('invoice_prefix');
		$feeGroup->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editFeeGroup'],$this->panelInit->language['feeGroupUpdated'],$feeGroup->toArray() );
	}
}
