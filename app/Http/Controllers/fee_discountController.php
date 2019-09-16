<?php
namespace App\Http\Controllers;

class fee_discountController extends Controller {

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

		if(!$this->panelInit->can( array("FeeDiscount.list","FeeDiscount.addFeeDiscount","FeeDiscount.editFeeDiscount","FeeDiscount.delFeeDiscount","FeeDiscount.assignUser") )){
			exit;
		}
		
		return \fee_discount::get();
	}

	public function delete($id){

		if(!$this->panelInit->can( "FeeDiscount.delFeeDiscount" )){
			exit;
		}

		if ( $postDelete = \fee_discount::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delFeeDiscount'],$this->panelInit->language['FeeDiscountDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delFeeDiscount'],$this->panelInit->language['FeeDiscountNotExist']);
        }
	}

	public function create(){

		if(!$this->panelInit->can( "FeeDiscount.addFeeDiscount" )){
			exit;
		}

		$fee_discount = new \fee_discount();
		$fee_discount->discount_name = \Input::get('discount_name');
		if(\Input::has('dicount_description')){
			$fee_discount->dicount_description = \Input::get('dicount_description');
		}
		$fee_discount->discount_code = \Input::get('discount_code');
		$fee_discount->discount_type = \Input::get('discount_type');
		$fee_discount->discount_value = \Input::get('discount_value');
		$fee_discount->discount_status = \Input::get('discount_status');
		$fee_discount->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addFeeDiscount'],$this->panelInit->language['FeeDiscountAdded'],$fee_discount->toArray() );
	}

	function fetch($id){

		if(!$this->panelInit->can( array("FeeDiscount.editFeeDiscount","FeeDiscount.assignUser") )){
			exit;
		}

		$fee_discount = \fee_discount::where('id',$id)->first()->toArray();

		return $fee_discount;
	}

	function edit($id){

		if(!$this->panelInit->can( "FeeDiscount.editFeeDiscount" )){
			exit;
		}

		$fee_discount = \fee_discount::find($id);
		$fee_discount->discount_name = \Input::get('discount_name');
		if(\Input::has('dicount_description')){
			$fee_discount->dicount_description = \Input::get('dicount_description');
		}
		$fee_discount->discount_code = \Input::get('discount_code');
		$fee_discount->discount_type = \Input::get('discount_type');
		$fee_discount->discount_value = \Input::get('discount_value');
		$fee_discount->discount_status = \Input::get('discount_status');
		$fee_discount->save();

		if($fee_discount->discount_status == 1){
			$this->remove_discount_invoices();
		}

		if($fee_discount->discount_status == 0){
			$this->remove_discount_invoices($fee_discount->id);
		}


		return $this->panelInit->apiOutput(true,$this->panelInit->language['editFeeDiscount'],$this->panelInit->language['feeDiscountMod'],$fee_discount->toArray() );
	}

	function search_assignments($area){

		if(!$this->panelInit->can( "FeeDiscount.assignUser" )){
			exit;
		}

		$keyword = \Input::get('keyword');
		if($area == "invoices"){
			$invoices = \payments::where('paymentStatus','0')->where(function($query) use ($keyword){
														$query->where('paymentTitle','like','%'.$keyword.'%')->orWhere('paymentDescription','like','%'.$keyword.'%');
													})->select('id','paymentTitle','paymentDescription')->get();
			$retArray = array();
			
			foreach ($invoices as $invoice) {
				$retArray[] = array("id"=>$invoice->id,"paymentTitle"=>$invoice->paymentTitle,"paymentDescription"=>$invoice->paymentDescription);
			}

			return json_encode($retArray);
		}
		if($area == "students"){
			
			$students = \User::where('role','student')->where(function($query) use ($keyword){
														$query->where('fullName','like','%'.$keyword.'%')->orWhere('username','like','%'.$keyword.'%')->orWhere('email','like','%'.$keyword.'%');
													})->select('id','fullName','username','email')->get();
			$retArray = array();
			
			foreach ($students as $student) {
				$retArray[$student->id] = array("id"=>$student->id,"name"=>$student->fullName,"username"=>$student->username,"email"=>$student->email);
			}

			return json_encode($retArray);
		}
	}

	function get_assignments($id){

		if(!$this->panelInit->can( "FeeDiscount.assignUser" )){
			exit;
		}

		$toReturn = array();

		$toReturn['fee_discount'] = \fee_discount::where('id',$id)->first()->toArray();
		$toReturn['fee_discount']['discount_assignment'] = json_decode($toReturn['fee_discount']['discount_assignment'],true);
		$toReturn['classes'] = \classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();

		return $toReturn;
	}

	function set_assignments($id){

		if(!$this->panelInit->can( "FeeDiscount.assignUser" )){
			exit;
		}

		$fee_discount = \fee_discount::find($id);
		$discount_assignment = json_decode($fee_discount->discount_assignment,true);

		$apply_to_params = array();

		if(\Input::get('apply_to') == "class" && \Input::has('classId')){

			if(!isset($discount_assignment['classes'])){
				$discount_assignment['classes'] = array();
			}

			$to_add = array('classId'=>\Input::get('classId'));
			$to_add['uniqid'] = "cl-".\Input::get('classId');

			$apply_to_params['classId'] = \Input::get('classId');
			if(\Input::has('sectionId')){
				$to_add['sectionId'] = \Input::get('sectionId');
				$to_add['uniqid'] .= "-".\Input::get('sectionId');
				$apply_to_params['sectionId'] = \Input::get('sectionId');
			}

			$discount_assignment['classes'][ $this->uniqidReal() ] = $to_add;

		}

		if(\Input::get('apply_to') == "invoice" && \Input::has('invoices')){
			
			$invoices_list = \Input::get('invoices');
			if(!isset($discount_assignment['invoices'])){
				$discount_assignment['invoices'] = array();
			}

			foreach ($invoices_list as $key => $value) {
				$apply_to_params[] = $value['id'];
				$value['uniqid'] = "inv-".$value['id'];
				$discount_assignment['invoices'][ $this->uniqidReal() ] = $value;
			}

		}

		if(\Input::get('apply_to') == "students" && \Input::has('students')){
			
			$students_list = \Input::get('students');
			if(!isset($discount_assignment['students'])){
				$discount_assignment['students'] = array();
			}

			foreach ($students_list as $key => $value) {
				$apply_to_params[] = $value['id'];
				$value['uniqid'] = "std-".$value['id'];
				$discount_assignment['students'][ $this->uniqidReal() ] = $value;
			}

		}

		$this->apply_discount_invoices($fee_discount,\Input::get('apply_to'),$apply_to_params);

		$fee_discount->discount_assignment = json_encode($discount_assignment);
		$fee_discount->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editFeeDiscount'],$this->panelInit->language['feeDiscountMod'],$this->get_assignments($id) );
	}

	function apply_discount_invoices($fee_discount,$apply_to,$apply_to_params){
		if($apply_to == "class"){

			$students_in_class = \User::where('role','student')->where('studentClass',$apply_to_params['classId']);
			if(isset($apply_to_params['sectionId'])){
				$students_in_class = $students_in_class->where('studentSection',$apply_to_params['sectionId']);
			}
			$students_in_class = $students_in_class->select('id');

			if($students_in_class->count() == 0){
				return;
			}

			$students_in_class = $students_in_class->get();

			$ids = array();
			foreach ($students_in_class as $key => $value) {
				$ids[] = $value['id'];
			}

			$invoices = \payments::whereIn('paymentStudent',$ids)->where('paymentStatus','0')->get();
		}

		if($apply_to == "invoice"){
			$invoices = \payments::whereIn('id',$apply_to_params)->where('paymentStatus','0')->get();
		}
		
		if($apply_to == "students"){
			$invoices = \payments::whereIn('paymentStudent',$apply_to_params)->where('paymentStatus','0')->get();
		}

		foreach ($invoices as $key => $invoice) {

			if($fee_discount->discount_type == "percentage"){
				$discount_value = ($invoice->paymentAmount * $fee_discount->discount_value) / 100;
			}
			
			if($fee_discount->discount_type == "fixed"){
				$discount_value = 0;
				if($fee_discount->discount_value >= $invoice->paymentAmount){
					$discount_value = $invoice->paymentAmount;
				}else{
					$discount_value = $fee_discount->discount_value;
				}
			}

			if($discount_value > $invoice->paymentDiscount){
				$paymentDiscounted = $invoice->paymentAmount - $discount_value;
				\payments::where('id',$invoice->id)->update( array('paymentDiscount'=>$discount_value,'paymentDiscounted'=>$paymentDiscounted,'discount_id'=>$fee_discount->id) );
			}

		}
	}

	function remove_assignment($id){

		if(!$this->panelInit->can( "FeeDiscount.assignUser" )){
			exit;
		}

		$fee_discount = \fee_discount::find($id);
		$discount_assignment = json_decode($fee_discount->discount_assignment,true);

		if(\Input::has('key') && \Input::has('uniqid')){
			unset( $discount_assignment[ \Input::get('key') ][ \Input::get('uniqid') ] );
		}

		$fee_discount->discount_assignment = json_encode($discount_assignment);
		$fee_discount->save();

		$this->remove_discount_invoices($fee_discount->id);

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editFeeDiscount'],$this->panelInit->language['feeDiscountMod']);

	}

	function remove_discount_invoices($fee_discount_id=""){

		if(!$this->panelInit->can( "FeeDiscount.assignUser" )){
			exit;
		}
		
		$available_discount = \fee_discount::where('discount_status','1')->get();
		$section_enabeld = $this->panelInit->settingsArray['enableSections'];
		$userIds = array();
		
		$invoices = new \payments();
		if($fee_discount_id != ""){
			$invoices = $invoices->where('discount_id',$fee_discount_id);
		}
		$invoices = $invoices->get();
		foreach ($invoices as $key => $invoice) {
			$userIds[] = $invoice->paymentStudent;
		}

		//get users list
		$users = array();
		$users_list = \User::whereIn('id',$userIds)->select('studentClass','studentSection','id')->get()->toArray();
		foreach ($users_list as $key => $value) {
			$users[ $value['id'] ] = $value;
		}

		reset($invoices);
		foreach ($invoices as $key => $invoice) {
			reset($available_discount);

			$can_use_list = array();

			foreach ($available_discount as $key => $discount) {
				if($section_enabeld == true && isset($users[ $invoice->paymentStudent ]) ){
					if (strpos($discount->discount_assignment, 'cl-'.$users[ $invoice->paymentStudent ]['studentClass']."-".$users[ $invoice->paymentStudent ]['studentSection']) !== false) {
						$can_use_list[ $discount->id ] = $discount;
					}
				}
				if($section_enabeld == false && isset($users[ $invoice->paymentStudent ])){
					if (strpos($discount->discount_assignment, 'cl-'.$users[ $invoice->paymentStudent ]['studentClass']) !== false) {
						$can_use_list[ $discount->id ] = $discount;
					}
				}
				
				if (strpos($discount->discount_assignment, 'inv-'.$invoice->id) !== false) {
					$can_use_list[ $discount->id ] = $discount;
				}

				if (strpos($discount->discount_assignment, 'std-'.$invoice->paymentStudent) !== false) {
					$can_use_list[ $discount->id ] = $discount;
				}
			}

			$apply_discount = array();

			//Get max one
			if(count($can_use_list) >= 1){
				$fee_discount = array();

				foreach ($can_use_list as $key => $can_use_one) {
					$tmp_discount_calculation = $this->calculate_discount_value($can_use_one, $invoice->paymentAmount);
					if(count($fee_discount) == 0){
						$fee_discount = $tmp_discount_calculation;
					}else{
						if($tmp_discount_calculation['discount_value'] > $fee_discount['discount_value']){
							$fee_discount = $tmp_discount_calculation;
						}
					}
				}
				
			}

			if(count($can_use_list) == 0){
				\payments::where('id',$invoice->id)->update( array('paymentDiscount'=>0,'paymentDiscounted'=>$invoice->paymentAmount,'discount_id'=>0) );
			}else{
				if($fee_discount['discount_value'] > $invoice->paymentDiscount){

					$paymentDiscounted = $invoice->paymentAmount - $fee_discount['discount_value'];
					$update_invoice = array('paymentDiscount'=>$fee_discount['discount_value'],'paymentDiscounted'=>$paymentDiscounted,'discount_id'=>$fee_discount['discount_id']);
					if($paymentDiscounted == 0){
						$update_invoice['paymentStatus'] = 1;
					}

					\payments::where('id',$invoice->id)->update( $update_invoice );
				}
			}
			
			
		}

	}

	function calculate_discount_value($fee_discount,$original){
		$to_return = array();

		if($fee_discount['discount_type'] == "percentage"){
			$to_return['discount_id'] = $fee_discount['id'];
			$to_return['discount_value'] = ($original * $fee_discount['discount_value']) / 100;
			$to_return['after_discount'] = $original - ($original * $fee_discount['discount_value']) / 100;
		}
		
		if($fee_discount['discount_type'] == "fixed"){
			$to_return['discount_value'] = 0;
			if($fee_discount['discount_value'] >= $original){
				$to_return['discount_value'] = $original;
			}else{
				$to_return['discount_value'] = $fee_discount['discount_value'];
			}
			$to_return['after_discount'] = $original - $to_return['discount_value'];
			$to_return['discount_id'] = $fee_discount['id'];

		}

		return $to_return;
	}


	function uniqidReal($lenght = 13) {
	    // uniqid gives 13 chars, but you could adjust it to your needs.
	    if (function_exists("random_bytes")) {
	        $bytes = random_bytes(ceil($lenght / 2));
	    } elseif (function_exists("openssl_random_pseudo_bytes")) {
	        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
	    } else {
	        throw new Exception("no cryptographically secure random function available");
	    }
	    return substr(bin2hex($bytes), 0, $lenght);
	}
}
