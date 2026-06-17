<?php
define("IN_SITE", true);
require_once "../../core/DB.php";
require_once "../../core/helpers.php";
require_once "../../core/class/newmomo.php";
// nhập vsign api key, lấy trên vsign.pro
$vsignAPIKey = 'f0d9076331384303b8b27bb3d46f11c3';
// Dòng 10 là chạy vsign cho ios
$Momo = new Momo($vsignAPIKey);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'GETOTP') {
        if (empty($_POST['token'])) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        if (!$getUser = $NNL->get_row("SELECT * FROM `users` WHERE `token` = '" . xss($_POST['token']) . "' AND `banned` = '0' ")) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        $phone = xss($_POST['sdt']);
        $pass = xss($_POST['pass']);
        if (empty($phone)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng điền số điện thoại',
            ]));
        }
        if (empty($pass)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng điền mật khẩu',
            ]));
        }
        if ($getUser['time_momo'] < time()) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Gói API của bạn đã hết hạn sử dụng, vui lòng nâng cấp để tiếp tục sử dụng',
            ]));
        }
        $checkLimit = $NNL->num_rows(" SELECT * FROM `account_momo` WHERE `user_id`='" . $getUser['username'] . "'");
        if ($checkLimit > $NNL->site('limit_api_momo')) {
            exit(json_encode(array('status' => '1', 'msg' => 'Quý khách chỉ được thêm tối đa ' . $NNL->site('limit_api_momo') . ' tài khoản momo')));
        }

        // $checkphone = json_decode($Momo->namemomo($phone));
        // if ($checkphone->error == 0) {
        //     die(json_encode([
        //         'status' => '1',
        //         'msg' => '' . $checkphone->msg . '',
        //     ]));
        // }

        $getDevice = $NNL->get_row(" SELECT * FROM `device` ORDER BY RAND() LIMIT 1 ");
        if (!$NNL->get_row("SELECT * FROM `account_momo` WHERE `phone` = '" . $phone . "' ")) {
            $NNL->insert("account_momo", [
                'phone' => $phone,
				'password' => $pass,
				'tbid' => strtoupper($Momo->generate_random(40)),
                'user_id' => $getUser['username'],
                'rkey' => $Momo->generate_random(20),
				'MODELID' => strtolower($Momo->generate_random(64)),
				"device_token" => strtoupper($Momo->generate_random(64)),
				'imei' => strtoupper($Momo->generate_UUID_v4()),
				'device' => 'iPhone 12',
				'hardware' => 'iPhone',
				'firmware' => '17.1',
				'facture' => 'Apple',
				'TOKEN' => $Momo->get_TOKEN(),
				'appVer' => 41081,
				'appCode' => '4.1.8',
				'csp' => 'Vietnamobile',
				'sessionKeyTracking' => strtoupper($Momo->generate_UUID_v4())
            ]);
        }
        $Momo->config = $NNL->get_row(" SELECT * FROM `account_momo` WHERE `phone` = '" . $phone . "' LIMIT 1  ");
		//$config = $Momo->load_db($phone, $password);
        //$Momo->CHECK_USER_BE_MSG();
		//$result = $Momo->check_user_be_msg();
		//$result = $this->send_otp_msg();
		$result = $Momo->send_otp();
		//$result["errorCode"] ='1';
 
        if ($result["errorCode"] == '0') {
            die(json_encode([
                'status' => '2',
                'msg' => '' . $result["errorDesc"] . '!',
            ]));
        } else {
            die(json_encode([
                'status' => '1',
                'msg' => '' . $result["errorDesc"] . '!',
            ]));
        }
       
    }
    //đăng nhập momo
	//đăng nhập momo
    if (isset($_POST['action']) && $_POST['action'] == 'CHECKOTP') {
        if (empty($_POST['sdt'])) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng điền số điện thoại',
            ]));
        }
        if (empty($_POST['pass'])) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng điền mật khẩu',
            ]));
        }
        if (empty($_POST['otp'])) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng điền OTP',
            ]));
        }
        $phone = xss($_POST['sdt']);
        $pass = xss($_POST['pass']);
        $code = xss($_POST['otp']);
        $Momo->config = $NNL->get_row(" SELECT * FROM `account_momo` WHERE `phone` = '" . $phone . "' LIMIT 1  ");
        $Momo->config['ohash'] = hash('sha256', $Momo->config["phone"] . $Momo->config["rkey"] . $code);
        $NNL->update("account_momo", [
            'ohash' => $Momo->config['ohash'],
        ], " `phone` = '" . $phone . "' ");
		$Momo->config["password"] = $pass;
        $result = $Momo->reg_device_msg($code);
        
        //$setupKeyDecrypt = $Momo->get_setupKey($result["extra"]["setupKey"]);
		$phash = $result['phash'];
        $name = $result['name'];
        $setup_key = $result['setup_key'];
        $NNL->update("account_momo", [
            'setup_key' => $setup_key,
            'status' => 'success',
			'name' => $name,
            'phash' => $phash,
        ], " `phone` = '" . $phone . "' ");
        
       
        if ($result["errorCode"] == '0') {

            $extra = $result["extra"];
            $BankVerify = ($result['momoMsg']['bankVerifyPersonalid'] == 'null') ? '1' : '2';
            $partnerCode = $result['momoMsg']['bankCode'] ?: '';
            $NNL->update("account_momo", [
                'password' => encryptData((string)$Momo->config["password"]),
                'authorization' => $extra["AUTH_TOKEN"],
                'try' => '0',
                'BankVerify' => $BankVerify,
                'agent_id' => $result["momoMsg"]["agentId"],
                'RSA_PUBLIC_KEY' => $extra["REQUEST_ENCRYPT_KEY"],
                'Name' => $extra["FULL_NAME"],
                'BALANCE' => $extra["BALANCE"],
                'refreshToken' => $extra["REFRESH_TOKEN"],
                'sessionkey' => $extra["SESSION_KEY"],
                'partnerCode' => $partnerCode,
                'errorDesc' => $result["errorCode"],
                'status' => 'success',
                'errorDesc' => 'Thành Công',
                'TimeLogin' => time(),
            ], " `phone` = '" . $phone . "' ");
            die(json_encode([
                'status' => '2',
                'msg' => 'Xác nhận otp thành công!',
            ]));
        } else {
            die(json_encode([
                'status' => '1',
                'msg' => 'Xác nhận otp thất bại!',
            ]));
        }
    }
    //get name
    
    
}
