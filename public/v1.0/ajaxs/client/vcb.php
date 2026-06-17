<?php
define("IN_SITE", true);
require_once "../../core/DB.php";
require_once "../../core/helpers.php";
require_once '../../core/class/class.smtp.php';
require_once '../../core/class/PHPMailerAutoload.php';
require_once '../../core/class/class.phpmailer.php';
require_once "../../core/class/vcb.php";
$vcb = new vcb;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'GETOTP') {
        if (empty($_POST['token'])) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        if (!$getUser = $NNL->get_row("SELECT * FROM `users` WHERE `token` = '" . xss($_POST['token']) . "' AND `banned` = '0' ")) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        $account = xss($_POST['account']);
        $password = xss($_POST['password']);
        $stk = xss($_POST['stk']);
        if (empty($account)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập tài khoản vietcombank',
            ]));
        }
        if (empty($password)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập mật khẩu vietcombank',
            ]));
        }
        if (empty($stk)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập số tài khoản vietcombank',
            ]));
        }
        if ($getUser['time_momo'] < time()) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Gói API của bạn đã hết hạn sử dụng, vui lòng nâng cấp để tiếp tục sử dụng',
            ]));
        }
        $response = $vcb->getCaptcha($NNL->site('key_captcha'));
        $captcha_id = json_decode($response, true)['data']['captcha_id'];
        $captcha = json_decode($response, true)['data']['captcha'];

        $token = md5(random('QWERTYUIOPASDGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm0123456789', 6) . time());
        $login = json_decode($vcb->login($account, $password, $captcha_id, $captcha), true);
        if ($login['code'] == '00') {
            $NNL->insert("account_vietcombank", [
                'name' => $login['userInfo']['cusName'],
                'user_id' => $getUser['id'],
                'username' => $account,
                'password' => $password,
                'account' => $stk,
                'session_id' => $login['sessionId'],
                'access_key' => $login['accessKey'],
                'client_id' => $login['userInfo']['clientId'],
                'mobile_id' => $login['userInfo']['mobileId'],
                'cif' => $login['userInfo']['cif'],
                'token' => $token,
                'create_date' => gettime(),
            ]);
            exit(json_encode(array('status' => '3', 'msg' => 'Đăng nhập thành công')));
        }elseif($login['code'] != '00'){
            $vcb->accountNo = $account;
            $vcb->browserToken = $login['browserToken'];
            $check_device = json_decode($vcb->initLoginNewBrowser(),true);
            if($check_device['code'] == "00"){
                $vcb->tranId = $check_device['transaction']['tranId'];
                $getotp = json_decode($vcb->get_otp(),true);
                if($getotp['code'] == "00"){
                     $NNL->insert("account_vietcombank", [
                        'user_id'  => $getUser['id'],
                        'username' => $account,
                        'password'             => $password,
                        'account'           => $stk,
                        'tranId'               => $vcb->tranId,
                        'browserToken'                 => $vcb->browserToken,
                        'token'                => $token,
                        'create_date'               => gettime(),
                    ]);
                    exit(json_encode(array('status' => '2', 'msg' => 'Đã gửi OTP về số điện thoại của bạn')));
                }else{
                    exit(json_encode(array('status' => '1', 'msg' => ''.$getotp['des'].'')));
                }
              
            }else{
              exit(json_encode(array('status' => '1', 'msg' => ''.$check_device['des'].'')));
            }

        } else {
            exit(json_encode(array('status' => '1', 'msg' => '' . $login['des'] . '')));
        }
    }
    if (isset($_POST['action']) && $_POST['action'] == 'LOGIN') {
        if (empty($_POST['token'])) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        if (!$getUser = $NNL->get_row("SELECT * FROM `users` WHERE `token` = '" . xss($_POST['token']) . "' AND `banned` = '0' ")) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        $account = xss($_POST['account']);
        $password = xss($_POST['password']);
        $stk = xss($_POST['stk']);
        $otp = xss($_POST['otp']);
        if (empty($account)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập tài khoản vietcombank',
            ]));
        }
        if (empty($password)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập mật khẩu vietcombank',
            ]));
        }
        if (empty($stk)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập số tài khoản vietcombank',
            ]));
        }
        if (empty($otp)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập OTP',
            ]));
        }
        if ($getUser['time_momo'] < time()) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Gói API của bạn đã hết hạn sử dụng, vui lòng nâng cấp để tiếp tục sử dụng',
            ]));
        }
        $data = $NNL->get_row(" SELECT * FROM `account_vietcombank` WHERE `user_id`='" . $getUser['id'] . "' AND `username` = '$account'");
        $verify = json_decode($vcb->veryOtpLoginNewBrowser($otp,$account,$data['tranId'],$data['browserToken']),true);
        if($verify['code'] == "00"){
             $NNL->update("account_vietcombank", [
                'name'               => $verify['userInfo']['cusName'],
                'session_id'           => $verify['sessionId'],
                'access_key'                => $verify['accessKey'],
                'client_id'           => $verify['userInfo']['clientId'],
                'mobile_id'           => $verify['userInfo']['mobileId'],
                'cif'                => $verify['userInfo']['cif'],
            ], " `username` = '" . $account . "' ");
            
            $vcb->saveLoginNewBrowser($account,$verify['userInfo']['cif'],$verify['userInfo']['clientId'],$verify['userInfo']['mobileId'],$verify['sessionId']);
            exit(json_encode(array('status' => '2', 'msg' => 'Đăng nhập thành công')));
        }else{
             exit(json_encode(array('status' => '1', 'msg' => ''.$verify['des'].'')));
        }

        // $token = md5(random('QWERTYUIOPASDGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm0123456789', 6) . time());
        // $login = json_decode($vcb->login($account, $password, $captcha_id, $captcha), true);
        // if ($login['code'] == '00') {
        //     $NNL->insert("account_vietcombank", [
        //         'name' => $login['userInfo']['cusName'],
        //         'user_id' => $getUser['id'],
        //         'username' => $account,
        //         'password' => $password,
        //         'account' => $stk,
        //         'session_id' => $login['sessionId'],
        //         'access_key' => $login['accessKey'],
        //         'client_id' => $login['userInfo']['clientId'],
        //         'mobile_id' => $login['userInfo']['mobileId'],
        //         'cif' => $login['userInfo']['cif'],
        //         'token' => $token,
        //         'create_date' => gettime(),
        //     ]);
        //     exit(json_encode(array('status' => '2', 'msg' => 'Đăng nhập thành công')));
        // } else {
        //     exit(json_encode(array('status' => '1', 'msg' => '' . $login['des'] . '')));
        // }
    }
    if (isset($_POST['action']) && $_POST['action'] == 'SENDTOKEN') {
        if (empty($_POST['token'])) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        if (!$getUser = $NNL->get_row("SELECT * FROM `users` WHERE `token` = '" . xss($_POST['token']) . "' AND `banned` = '0' ")) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        $id = xss($_POST['id']);
        if (empty($id)) {
            exit(json_encode(array('status' => '1', 'msg' => 'Vui lòng điền thông tin!')));
        }
        $row = $NNL->get_row(" SELECT * FROM `account_vietcombank` WHERE `id` = '$id' AND `user_id`='" . $getUser['id'] . "' ");
        if (!$row) {
            exit(json_encode(array('status' => '1', 'msg' => 'Tài khoản vietcombank không tồn tại!')));
        }
        $user = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $row['user_id'] . "' ");
        if (!$user) {
            exit(json_encode(array('status' => '1', 'msg' => 'Người dùng không tồn tại!')));
        }

        $guitoi = $user['email'];
        $subject = 'TOKEN VIETCOMBANK';
        $bcc = "API BANK";
        $hoten = $user['username'];
        $token = $row['token'];
        $noi_dung = '<h3>Có ai đó vừa yêu cầu gửi token vietcombank bằng Email này, nếu là bạn thì token bên dưới dùng để chạy api</h3>
						<table>
						<tbody>
						<tr>
							<td style="font-size:20px;">TOKEN:</td>
							<td><b style="color:blue;font-size:30px;">' . $token . '</b></td>
						</tr>
						<tr><td colspan="2"><br></td></tr>
						<tr>
							<td style="font-size:20px;">LINK API GIAO DỊCH:</td>
							<td><b style="color:blue;font-size:30px;">' . BASE_URL('') . "api/historyvietcombank/" . $token . '</b></td>
						</tr>
						<tr><td colspan="2"><br></td></tr>
						<tr>
							<td style="font-size:20px;">LINK API SỐ DƯ:</td>
							<td><b style="color:blue;font-size:30px;">' . BASE_URL('') . "api/historyvietcombankbalance/" . $token . '</b></td>
						</tr>
						</tbody>
						</table>';
        Locdz_Email($guitoi, $hoten, $subject, $noi_dung, $bcc);
        exit(json_encode(array('status' => '2', 'msg' => 'Đã gửi token đến mail của bạn!')));
    }
    //get name
    if (isset($_POST['action']) && $_POST['action'] == 'REMOVE') {
        if (empty($_POST['token'])) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        if (!$getUser = $NNL->get_row("SELECT * FROM `users` WHERE `token` = '" . xss($_POST['token']) . "' AND `banned` = '0' ")) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        $id = xss($_POST['id']);
        if (empty($id)) {
            exit(json_encode(array('status' => '1', 'msg' => 'Không được!')));
        }
        $tool = $NNL->get_row(" SELECT * FROM `account_vietcombank` WHERE `id` = '$id' AND `user_id`='" . $getUser['id'] . "' ");
        if (!$tool) {
            exit(json_encode(array('status' => '1', 'msg' => 'Định hack à không dễ vậy đâu!')));
        }
        $NNL->remove("account_vietcombank", "`id`='" . $id . "'");
        exit(json_encode(array('status' => '2', 'msg' => 'Đã xóa tài khoản thành công!')));
    }
}
