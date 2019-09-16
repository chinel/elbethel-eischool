<?php namespace App\Http\Controllers;

use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(){
		$this->panelInit = new \DashboardInit();
		$this->data['panelInit'] = $this->panelInit;
	}

    public function authenticate(Request $request)
    {
        if (filter_var(\Input::get('username'), FILTER_VALIDATE_EMAIL)) {
            $credentials = $request->only('username', 'password');
            $credentials['email'] = $credentials['username'];
            unset($credentials['username']);
        }else{
            $credentials = $request->only('username', 'password');
        }
        
        $credentials['activated'] = 1;
        $credentials['account_active'] = 1;

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        $this->android_token(\Input::get('android_token'),JWTAuth::toUser($token));

        // if no errors are encountered we can return a JWT
        return response()->json(compact('token'));
    }

    public function getAuthenticatedUser()
    {
        try {

            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }

        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return response()->json(['token_expired'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return response()->json(['token_invalid'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {

            return response()->json(['token_absent'], $e->getStatusCode());

        }

        return response()->json(compact('user'));
    }

    public function android_token($token,$user){
        $token_exist = false;

        if($token == null || $token == "null"){
            return;
        }

        $old_token = \User::where('firebase_token','LIKE','%"'.$token.'"%')->select('id','firebase_token');
        if($old_token->count() > 0){
            $old_token = $old_token->get();
            foreach ($old_token as $one) {
                if($one->id == $user->id){
                    $token_exist = true;
                    continue;
                }
                $current_token = json_decode($one->firebase_token,true);
                foreach($current_token as $key => $value){
                    if($value == $token){
                        unset($current_token[$key]);
                    }
                }
                $update_tokens = array( 'firebase_token' => json_encode($current_token) );
                \User::where('id',$one->id)->update($update_tokens);
            }
        }

        if($token_exist == false){
            $current_token = $user->firebase_token;
            if($current_token == ""){
                $current_token = array();
                $current_token[] = $token;
                $update_tokens = array( 'firebase_token' => json_encode($current_token) );
                \User::where('id',$user->id)->update($update_tokens);
            }else{
                if (strpos($current_token, $token) !== true) {
                    $current_token = json_decode($current_token,true);
                    $current_token[] = $token;
                    $update_tokens = array( 'firebase_token' => json_encode($current_token) );
                    \User::where('id',$user->id)->update($update_tokens);
                }
            }
        }

    }
    
    public function register(Request $request){

        $newuser= $request->all();
        $password=Hash::make($request->input('password'));

        $newuser['password'] = $password;

        return User::create($newuser);
    }
}

?>
