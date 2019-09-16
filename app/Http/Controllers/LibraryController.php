<?php
namespace App\Http\Controllers;

class LibraryController extends Controller {

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

	public function listAll($page = 1)
	{

		if(!$this->panelInit->can( array("Library.list","Library.addBook","Library.editBook","Library.delLibrary","Library.Download","Library.mngSub") )){
			exit;
		}

		$toReturn = array();
		$toReturn['bookLibrary'] = \book_library::orderBy('id','DESC')->take('20')->skip(20* ($page - 1) )->get()->toArray();
		$toReturn['totalItems'] = \book_library::count();
		$toReturn['userRole'] = $this->data['users']->role;
		return $toReturn;
	}

	public function search($keyword,$page = 1)
	{

		if(!$this->panelInit->can( array("Library.list","Library.addBook","Library.editBook","Library.delLibrary","Library.Download","Library.mngSub") )){
			exit;
		}

		$toReturn = array();
		$toReturn['bookLibrary'] = \book_library::where('bookName','like','%'.$keyword.'%')->orWhere('bookDescription','like','%'.$keyword.'%')->orWhere('bookAuthor','like','%'.$keyword.'%')->orderBy('id','DESC')->take('20')->skip(20* ($page - 1) )->get()->toArray();
		$toReturn['totalItems'] = \book_library::where('bookName','like','%'.$keyword.'%')->orWhere('bookDescription','like','%'.$keyword.'%')->orWhere('bookAuthor','like','%'.$keyword.'%')->count();
		return $toReturn;
	}

	public function delete($id){

		if(!$this->panelInit->can( "Library.delLibrary" )){
			exit;
		}

		if ( $postDelete = \book_library::where('id', $id)->first() )
        {
			@unlink('uploads/books/'.$postDelete->bookFile);
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delLibrary'],$this->panelInit->language['itemdel']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delLibrary'],$this->panelInit->language['itemNotExist']);
        }
	}

	public function download($id){

		if(!$this->panelInit->can( "Library.Download" )){
			exit;
		}

		$toReturn = \book_library::where('id',$id)->first();
		if(file_exists('uploads/books/'.$toReturn->bookFile)){
			$fileName = preg_replace('/[^a-zA-Z0-9-_\.]/','-',$toReturn->bookName). "." .pathinfo($toReturn->bookFile, PATHINFO_EXTENSION);
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename=" . $fileName);
			echo file_get_contents('uploads/books/'.$toReturn->bookFile);
		}else{
			echo "<br/><br/><br/><br/><br/><center>File not exist, Please contact site administrator to reupload it again.</center>";
		}
		exit;
	}

	public function create(){

		if(!$this->panelInit->can( "Library.addBook" )){
			exit;
		}

		$bookLibrary = new \book_library();
		$bookLibrary->bookName = \Input::get('bookName');
		$bookLibrary->bookDescription = \Input::get('bookDescription');
		$bookLibrary->bookAuthor = \Input::get('bookAuthor');
		$bookLibrary->bookType = \Input::get('bookType');
		$bookLibrary->bookPrice = \Input::get('bookPrice');
		$bookLibrary->bookState = \Input::get('bookState');
		if(\Input::has('bookQuantity')){
			$bookLibrary->bookQuantity = \Input::get('bookQuantity');
		}
		if(\Input::has('bookShelf')){
			$bookLibrary->bookShelf = \Input::get('bookShelf');
		}
		if(\Input::has('bookPublisher')){
			$bookLibrary->bookPublisher = \Input::get('bookPublisher');
		}
		if(\Input::get('bookISBN')){
			$bookLibrary->bookISBN = \Input::get('bookISBN');
		}
		$bookLibrary->save();

		if (\Input::hasFile('bookFile')) {
			$fileInstance = \Input::file('bookFile');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['addBook'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}

			$newFileName = "book_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/books/',$newFileName);

			$bookLibrary->bookFile = $newFileName;
			$bookLibrary->save();
		}

		return $this->panelInit->apiOutput(true,$this->panelInit->language['addBook'],$this->panelInit->language['bookAdded'],$bookLibrary->toArray() );
	}

	function fetch($id){

		if(!$this->panelInit->can( "Library.editBook" )){
			exit;
		}

		$data = \book_library::where('id',$id)->first()->toArray();
		return json_encode($data);
	}

	function edit($id){

		if(!$this->panelInit->can( "Library.editBook" )){
			exit;
		}

		$bookLibrary = \book_library::find($id);
		$bookLibrary->bookName = \Input::get('bookName');
		$bookLibrary->bookDescription = \Input::get('bookDescription');
		$bookLibrary->bookAuthor = \Input::get('bookAuthor');
		$bookLibrary->bookType = \Input::get('bookType');
		$bookLibrary->bookPrice = \Input::get('bookPrice');
		$bookLibrary->bookState = \Input::get('bookState');
		if(\Input::has('bookQuantity')){
			$bookLibrary->bookQuantity = \Input::get('bookQuantity');
		}
		if(\Input::has('bookShelf')){
			$bookLibrary->bookShelf = \Input::get('bookShelf');
		}
		if(\Input::has('bookPublisher')){
			$bookLibrary->bookPublisher = \Input::get('bookPublisher');
		}
		if(\Input::get('bookISBN')){
			$bookLibrary->bookISBN = \Input::get('bookISBN');
		}
		if (\Input::hasFile('bookFile')) {
			
			$fileInstance = \Input::file('bookFile');

			if(!$this->panelInit->validate_upload($fileInstance)){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['editBook'],"Sorry, This File Type Is Not Permitted For Security Reasons ");
			}
			@unlink("uploads/books/".$bookLibrary->bookFile);
			
			$newFileName = "book_".uniqid().".".$fileInstance->getClientOriginalExtension();
			$fileInstance->move('uploads/books/',$newFileName);

			$bookLibrary->bookFile = $newFileName;
		}
		$bookLibrary->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['editBook'],$this->panelInit->language['bookModified'],$bookLibrary->toArray() );
	}

	function library_members(){

		if(!$this->panelInit->can( "Library.mngSub" )){
			exit;
		}

		$retArray = array();
		$retArray['users'] = array();

		$user = \Input::get('user_search');
		$retArray['users'] = \User::where(function($query) use ($user){
						$query->where('fullName','like','%'.$user.'%')->orWhere('username','like','%'.$user.'%')->orWhere('email','like','%'.$user.'%');
					})->select('id','fullName','email','role_perm','library_id')->get();

		$retArray['prems'] = array();
		$perms = \roles::select('id','role_title')->get();
		foreach ($perms as $key => $value) {
			$retArray['prems'][$value->id] = $value->role_title;
		}

		return $retArray;
	}

	function library_members_set(){

		if(!$this->panelInit->can( "Library.mngSub" )){
			exit;
		}

		$User = \User::find( \Input::get('user') );
		$User->library_id = \Input::get('library_id');
		$User->save();

		return $this->panelInit->apiOutput(true,$this->panelInit->language['mngSub'],$this->panelInit->language['subChged']);
	}
}
