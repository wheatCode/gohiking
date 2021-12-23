<?php
// 參考：https://www.positronx.io/laravel-rest-api-with-passport-authentication-tutorial/

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

class PassportAuthController extends Controller
{
    public function register(Request $request)
    { // 對帳密等資料的長短限制可再討論
        $this->validate($request, [
            'email' => 'required|email',
            // 'password' => 'required|min:8', // 加上長度限制的寫法
            'password' => 'required',
        ]);

        $findUser = User::where('email', $request->email)->first();

        if ($findUser) {
            return response()->json(['error' => '此電子郵件地址已經被註冊了！'], 404);
        } else {
            $user = User::create([
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'image' => 'https://via.placeholder.com/500x400', // 先使用空白圖片
            ]);

            // 預先產生與回傳前端存取需驗證身分的API時，於headers攜帶的token，即可註冊後直接登入使用
            $token = $user->createToken('LaravelAuthApp')->accessToken;
            return response()->json(['token' => $token, 'userId' => $user->id, 'expireTime' => 3600000], 200);
        }
    }

    public function createProfile(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'gender' => 'required',
            'phone_number' => 'required',
            'county_id' => 'required',
            'birth' => 'required',
            'country_code_id' => 'required',
        ]);

        $findUser = User::where('id', $request->user()->id)->first();

        $findPhone = User::where('phone_number', $request->phone_number)->first();

        // 帳號已經註冊，且手機號碼沒被重複使用過，才可建立個人資料
        if (!$findUser) { // 帳號尚未註冊
            return response()->json(['error' => '對應的帳號未找到！'], 401);
        } else if ($findPhone) { // 手機號碼已被使用
            return response()->json(['error' => '此電話號碼已經被登記了！'], 401);
        } else {
            User::where('email', $findUser->email)->update([
                'name' => $request->name,
                'gender' => $request->gender,
                'phone_number' => $request->phone_number,
                'county_id' => $request->county_id,
                'birth' => $request->birth,
                'country_code_id' => $request->country_code_id,
            ]);
            return response()->json(['status' => '您的個人資料已經成功建立！'], 200);
        }
    }

    public function login(Request $request)
    {
        $logInData = [
            'email' => $request->email,
            'password' => $request->password
        ];

        if (auth()->attempt($logInData)) {
            $token = auth()->user()->createToken('LaravelAuthApp')->accessToken;
            return response()->json(['token' => $token, 'userId' => auth()->user()->id, 'expireTime' => 3600000], 200);
        } else {
            return response()->json(['error' => '電子郵件地址或密碼錯誤！'], 401);
        }
    }

    public function forgetPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required',
        ]);

        $findUser = User::where('email', $request->email)->first();

        if ($findUser) {
            // 產生驗證碼
            function randomCode()
            {
                return rand(0, 9);
            }

            $verificationCodes = [randomCode(), randomCode(), randomCode(), randomCode()];
            error_log($verificationCodes[0] . ', ' . $verificationCodes[1] . ', ' . $verificationCodes[2] . ', ' . $verificationCodes[3]);

            // 寄送驗證碼信件，參考：https://ithelp.ithome.com.tw/articles/10252073
            $email = $request->email;
            // $userToken = $findUser->remember_token;
            $url =  config('mail.email_url')."/Verification";
            $text = '你的驗證碼是：' . $verificationCodes[0].$verificationCodes[1].$verificationCodes[2].$verificationCodes[3].'；若確定要更改密碼，請回到APP輸入上述4位數字：' . $url."?email=".$email;

            // 將驗證碼寫入資料庫的使用者表格，以便在驗證時對應
            User::where('email', $findUser->email)->update([
                'verification_code_0' => $verificationCodes[0],
                'verification_code_1' => $verificationCodes[1],
                'verification_code_2' => $verificationCodes[2],
                'verification_code_3' => $verificationCodes[3],
           ]);

            try {
                Mail::raw($text, function ($message) use ($email) {
                    $message->to($email)->subject('請確認修改密碼');
                });
                error_log('Successfully send to ' . $email);
            } catch (Exception $e) {
                error_log('fail!');
            }
            return response()->json(['message' => '已寄送驗證碼到指定信件！'])->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        }else{
            return response()->json(['error' => '電子郵件地址不存在！'], 401);
	}
    }

    public function confirmVerificationCodes(Request $request)
    {      
        $validator = Validator::make($request->all(),  
        [
            'email' => 'required',
            'verificationCode0' => 'required',
            'verificationCode1' => 'required',
            'verificationCode2' => 'required',
            'verificationCode3' => 'required',
        ],  
        [
            'email.required' => '信箱必填',
            'verificationCode0.required' => '第一位必填',
            'verificationCode1.required' => '第二位必填',
            'verificationCode2.required' => '第三位必填',
            'verificationCode3.required' => '第四位必填',
        ]);

        if ($validator->fails()) {
            $error = "";
            $errors = $validator->errors();
            foreach ($errors->all() as $message)
                $error .= $message . "\n";
            return response()->json(['error' => $error], 401);
        }
        $findUser = User::where('email', $request->email)->where('verification_code_0', $request->verificationCode0)->where('verification_code_1', $request->verificationCode1)->where('verification_code_2', $request->verificationCode2)->where('verification_code_3', $request->verificationCode3)->first();

        if ($findUser) {
            $token = $findUser->createToken('LaravelAuthApp')->accessToken;
            return response()->json(['token' => $token], 200);
        } else {
            return response()->json(['error' => '錯誤的驗證碼！'], 401);
        }
    }

    public function changePassword(Request $request)
    {
        $this->validate($request, [
            'password' => 'required',
        ]);

        try {
            User::where('id', $request->user()->id)->first()->update([
                'password' => bcrypt($request->password)
            ]);
            return response()->json(['status' => '您的密碼已經成功變更！'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => '密碼變更失敗！'], 401);
        }
    }
}
