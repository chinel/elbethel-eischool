<?php
namespace App\Http\Controllers;

class MessagesController extends Controller {

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

	public function listMessages($page = 1)
	{
		$toReturn = array();
		$toReturn['messages'] = \DB::select(\DB::raw("SELECT messages_list.id as id,messages_list.lastMessageDate as lastMessageDate,messages_list.lastMessage as lastMessage,messages_list.messageStatus as messageStatus,users.fullName as fullName,users.id as userId from messages_list LEFT JOIN users ON users.id=IF(messages_list.userId = '".$this->data['users']->id."',messages_list.toId,messages_list.userId) where userId='".$this->data['users']->id."' order by messages_list.lastMessageDate DESC limit 20 offset " . 20 * ($page - 1) ));
		$toReturn['totalItems'] = \messages_list::where('userId',$this->data['users']->id)->count();

		foreach ($toReturn['messages'] as $key => $value) {
			$toReturn['messages'][$key]->lastMessageDate = $this->panelInit->unix_to_date($toReturn['messages'][$key]->lastMessageDate, $this->panelInit->settingsArray['dateformat']." hr:mn a");
		}

		return json_encode($toReturn);
	}

	public function fetch($messageId,$page = 1)
	{
		$toReturn = array();
		$toReturn['user'] = $this->data['users'];
		$toReturn['messageDet'] = \DB::select(\DB::raw("SELECT messages_list.id as id,messages_list.lastMessageDate as lastMessageDate,messages_list.userId as fromId,messages_list.toId as toId,users.fullName as fullName,users.id as userId,users.photo as photo from messages_list LEFT JOIN users ON users.id=messages_list.toId where messages_list.id='$messageId' AND userId='".$this->data['users']->id."' order by id DESC"));
		if(count($toReturn['messageDet']) > 0){
			$toReturn['messageDet'] = $toReturn['messageDet'][0];
		}else{
			return json_encode(array("jsTitle"=>$this->panelInit->language['readMessage'],"jsStatus"=>"0","jsMessage"=>$this->panelInit->language['messageNotExist'] ));
			exit;
		}

		$toReturn['messages'] = \DB::select(\DB::raw("SELECT messages.id as id,messages.fromId as fromId,messages.messageText as messageText,messages.dateSent as dateSent,users.fullName as fullName,users.id as userId,users.photo as photo FROM messages LEFT JOIN users ON users.id=messages.fromId where messages.userId='".$this->data['users']->id."' AND ( (messages.fromId='".$this->data['users']->id."' OR messages.fromId='".$toReturn['messageDet']->toId."' ) AND (messages.toId='".$this->data['users']->id."' OR messages.toId='".$toReturn['messageDet']->toId."' ) ) order by id DESC limit 20"));

		foreach ($toReturn['messages'] as $key => $value) {
			$toReturn['messages'][$key]->dateSentH = $this->panelInit->unix_to_date($toReturn['messages'][$key]->dateSent, $this->panelInit->settingsArray['dateformat']." hr:mn a");
		}

		return json_encode($toReturn);
	}

	public function reply($id){
		$messages = new \messages();
		$messages->messageId = $id;
		$messages->userId = $this->data['users']->id;
		$messages->fromId = $this->data['users']->id;
		$messages->toId = \Input::get('toId');
		$messages->messageText = \Input::get('reply');
		$messages->dateSent = time();
		$messages->save();

		$messages = new \messages();
		$messages->messageId = $id;
		$messages->userId = \Input::get('toId');
		$messages->fromId = $this->data['users']->id;
		$messages->toId = \Input::get('toId');
		$messages->messageText = \Input::get('reply');
		$messages->dateSent = time();
		$messages->save();

		$messagesList = \messages_list::where('userId',$this->data['users']->id)->where('toId',\Input::get('toId'));
		if($messagesList->count() == 0){
			$messagesList = new \messages_list();
			$messagesList->userId = $this->data['users']->id;
			$messagesList->toId = \Input::get('toId');
		}else{
			$messagesList = $messagesList->first();
		}
		$messagesList->lastMessage = \Input::get('reply');
		$messagesList->lastMessageDate = time();
		$messagesList->messageStatus = 0;
		$messagesList->save();

		$messagesList_ = \messages_list::where('userId',\Input::get('toId'))->where('toId',$this->data['users']->id);
		if($messagesList_->count() == 0){
			$messagesList_ = new \messages_list();
			$messagesList_->userId = \Input::get('toId');
			$messagesList_->toId = $this->data['users']->id;
		}else{
			$messagesList_ = $messagesList_->first();
		}
		$messagesList_->lastMessage = \Input::get('reply');
		$messagesList_->lastMessageDate = time();
		$messagesList_->messageStatus = 1;
		$messagesList_->save();


		//Send Push Notifications
		$tokens_list = array();
		$to_user = \User::where('id',\Input::get('toId'))->select('firebase_token')->first();
		if($to_user->firebase_token != ""){
			$tokens_list[] = $to_user->firebase_token;
			$this->panelInit->send_push_notification($tokens_list,$this->panelInit->language['newMessageFrom']." ".$this->data['users']->fullName,$this->panelInit->language['Messages'],"messages",$messagesList_->id);			
		}

		echo 1;
		exit;
	}

	public function before($from,$to,$before = 0){
		$toReturn['messages'] = \DB::select(\DB::raw("SELECT messages.id as id,messages.fromId as fromId,messages.messageText as messageText,messages.dateSent as dateSent,users.fullName as fullName,users.id as userId,users.photo as photo FROM messages LEFT JOIN users ON users.id=messages.fromId where userId='".$this->data['users']->id."' AND ( (fromId='$from' OR fromId='$to' ) AND (toId='$from' OR toId='$to' ) ) AND dateSent < '$before' order by id DESC limit 20"));
		foreach ($toReturn['messages'] as $key => $value) {
			$toReturn['messages'][$key]->dateSentH = $this->panelInit->unix_to_date($toReturn['messages'][$key]->dateSent, $this->panelInit->settingsArray['dateformat']." hr:mn a");
		}
		return $toReturn['messages'];
	}

	public function ajax($from,$to,$after = 0){
		$messagesList = \messages_list::where('userId',$this->data['users']->id)->where('toId',$to);
		if($messagesList->count() == 0){
			exit;
		}
		$messagesList = $messagesList->first();
		$messagesList->messageStatus = 0;
		$messagesList->save();
		$toReturn['messages'] = \DB::select(\DB::raw("SELECT messages.id as id,messages.fromId as fromId,messages.messageText as messageText,messages.dateSent as dateSent,users.fullName as fullName,users.id as userId,users.photo as photo FROM messages LEFT JOIN users ON users.id=messages.fromId where userId='".$this->data['users']->id."' AND ( (fromId='$from' OR fromId='$to' ) AND (toId='$from' OR toId='$to' ) ) AND dateSent>'$after' order by id DESC"));

		foreach ($toReturn['messages'] as $key => $value) {
			$toReturn['messages'][$key]->dateSentH = $this->panelInit->unix_to_date($toReturn['messages'][$key]->dateSent, $this->panelInit->settingsArray['dateformat']." hr:mn a");
		}

		return $toReturn['messages'];
	}

	public function read(){
		if(\Input::get('items')){
			if(count(\Input::get('items')) > 0){
				$items = implode(",",\Input::get('items'));
				\DB::update(\DB::raw("UPDATE messages_list SET messageStatus='0' where id IN (".$items.")"));
			}
		}

		return $this->panelInit->apiOutput(true,$this->panelInit->language['chgMessage'],$this->panelInit->language['messIsRead']);
	}

	public function unread(){
		if(\Input::get('items')){
			if(count(\Input::get('items')) > 0){
				$items = implode(",",\Input::get('items'));
				\DB::update(\DB::raw("UPDATE messages_list SET messageStatus='1' where id IN (".$items.")"));
			}
		}

		return $this->panelInit->apiOutput(true,$this->panelInit->language['chgMessage'],$this->panelInit->language['messIsUnread']);
	}

	public function delete(){
		if(\Input::get('items')){
			if(count(\Input::get('items')) > 0){
				$arr = \Input::get('items');
				foreach($arr as $value){
					$messagesList = \messages_list::where('id',$value)->first();
					\DB::delete(\DB::raw("DELETE from messages where userId='".$this->data['users']->id."' AND ( (fromId = '".$messagesList->userId."' AND toId = '".$messagesList->toId."') OR (fromId = '".$messagesList->toId."' AND toId = '".$messagesList->userId."') ) "));
					\DB::delete(\DB::raw("DELETE from messages_list where id = '$value'"));
				}
			}
		}

		return $this->panelInit->apiOutput(true,$this->panelInit->language['delMess'],$this->panelInit->language['messDel']);
	}

	public function create(){
		$users_list = array();

		if( is_array(\Input::get('toId')) ){

			$users_ids = array();
			$toId_list = \Input::get('toId');
			foreach ($toId_list as $key => $value) {
				$users_ids[] = $value['id'];
			}

			$users = \User::whereIn('id',$users_ids);
			if($users->count() == 0){
				echo $this->panelInit->language['userisntExist'];
				exit;
			}
			$users_list = $users->get();

		}else{

			$users = \User::where('username',\Input::get('toId'))->orWhere('email',\Input::get('toId'));
			if($users->count() == 0){
				echo $this->panelInit->language['userisntExist'];
				exit;
			}
			$users = $users->first();
			$users_list[] = $users;

		}

		
		foreach ($users_list as $key => $users) {

			if($users->id == $this->data['users']->id){
				continue;
			}

			$messagesList = \messages_list::where('userId',$this->data['users']->id)->where('toId',$users->id);
			if($messagesList->count() == 0){
				$messagesList = new \messages_list();
				$messagesList->userId = $this->data['users']->id;
				$messagesList->toId = $users->id;
			}else{
				$messagesList = $messagesList->first();
			}
			$messagesList->lastMessage = \Input::get('messageText');
			$messagesList->lastMessageDate = time();
			$messagesList->messageStatus = 0;
			$messagesList->save();
			$toReturnId = $messagesList->id;

			$messagesList_ = \messages_list::where('userId',$users->id)->where('toId',$this->data['users']->id);
			if($messagesList_->count() == 0){
				$messagesList_ = new \messages_list();
				$messagesList_->userId = $users->id;
				$messagesList_->toId = $this->data['users']->id;
			}else{
				$messagesList_ = $messagesList_->first();
			}
			$messagesList_->lastMessage = \Input::get('messageText');
			$messagesList_->lastMessageDate = time();
			$messagesList_->messageStatus = 1;
			$messagesList_->save();
			$toReturnId_ = $messagesList_->id;

			$messages = new \messages();
			$messages->messageId = $toReturnId;
			$messages->userId = $this->data['users']->id;
			$messages->fromId = $this->data['users']->id;
			$messages->toId = $users->id;
			$messages->messageText = \Input::get('messageText');
			$messages->dateSent = time();
			$messages->save();

			$messages = new \messages();
			$messages->messageId = $toReturnId_;
			$messages->userId = $users->id;
			$messages->fromId = $this->data['users']->id;
			$messages->toId = $users->id;
			$messages->messageText = \Input::get('messageText');
			$messages->dateSent = time();
			$messages->save();

			//Send Push Notifications
			$tokens_list = array();
			if($users->firebase_token != ""){
				$tokens_list[] = $users->firebase_token;
				$this->panelInit->send_push_notification($tokens_list,$this->panelInit->language['newMessageFrom']." ".$this->data['users']->fullName,$this->panelInit->language['Messages'],"messages",$messagesList_->id);			
			}

		}
		
		if( count($users_list) == 1 AND isset($toReturnId)){
			return json_encode(array('messageId'=>$toReturnId));
		}else{
			return json_encode(array('messageId'=>"home"));
		}
	}

	public function searchUser($user){
		$Users = \User::where('fullName','like','%'.$user.'%')->orWhere('username','like','%'.$user.'%')->orWhere('email','like','%'.$user.'%')->get();
		$retArray = array();
		foreach ($Users as $user) {
			$retArray[$user->id] = array("id"=>$user->id,"name"=>$user->fullName,"email"=>$user->email,"role"=>$user->role,"username"=>$user->username);
		}
		return json_encode($retArray);
	}

}
