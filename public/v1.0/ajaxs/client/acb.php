<?php
define("IN_SITE", true);
require_once "../../core/DB.php";
require_once "../../core/helpers.php";
require_once '../../core/class/class.smtp.php';
require_once '../../core/class/PHPMailerAutoload.php';
require_once '../../core/class/class.phpmailer.php';
require_once "../../core/class/acb.php";

$acb = new ACB;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
        if (empty($account)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập tài khoản ACB',
            ]));
        }
        if (empty($password)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập mật khẩu ACB',
            ]));
        }
        if (empty($stk)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập số tài khoản ACB',
            ]));
        }
        if ($getUser['time_momo'] < time()) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Gói API của bạn đã hết hạn sử dụng, vui lòng nâng cấp để tiếp tục sử dụng',
            ]));
        }

				$token = md5(random('QWERTYUIOPASDGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm0123456789', 6) . time());
				$response = $acb->loginAcb($account, $password);
				$accessToken = $response['accessToken'];
				$displayName = $response['identity'] ['displayName'];
				if ($response['identity'] ['active'] == 1)
				{
		
		
            $create = $NNL->insert("account_acb", [
                'user_id' => $getUser['id'],
                'phone' => $account,
                'stk' => $stk,
                'name' => $displayName,
                'password' => $password,
                'sessionId' => $accessToken,
                'deviceId' => '',
                'token' => $token,
                'time' => time(),
            ]);
            exit(json_encode(array('status' => '2', 'msg' => 'Thêm tài khoản thành công')));
				} else exit(json_encode(array('status' => '1', 'msg' => 'Tài khoản không hợp lệ')));
        
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
        $row = $NNL->get_row(" SELECT * FROM `account_acb` WHERE `id` = '$id' AND `user_id`='" . $getUser['id'] . "' ");
        if (!$row) {
            exit(json_encode(array('status' => '1', 'msg' => 'Tài khoản ACB không tồn tại!')));
        }
        $user = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $row['user_id'] . "' ");
        if (!$user) {
            exit(json_encode(array('status' => '1', 'msg' => 'Người dùng không tồn tại!')));
        }

        $guitoi = $user['email'];
        $subject = 'TOKEN ACB';
        $bcc = "API BANK";
        $hoten = 'Client';
        $token = $row['token'];
        $noi_dung = '<h3>Có ai đó vừa yêu cầu gửi token ACB bằng Email này, nếu là bạn thì hãy dùng nó để chạy api</h3>
					<table>
					<tbody>
					<tr>
						<td style="font-size:30px;"><b>TOKEN:</b></td>
						<td><b style="color:blue;font-size:20px;">' . $token . '</b></td>
					</tr>
					<tr><td colspan="2"><br></td></tr>
					<tr>
						<td style="font-size:30px;"><b>LINK API GIAO DỊCH:</b></td>
						<td><b style="color:blue;font-size:20px;">' . BASE_URL('') . "api/historyacb/" . $token . '</b></td>
					</tr>
					<tr><td colspan="2"><br></td></tr>
					<tr>
						<td style="font-size:30px;"><b>LINK API SỐ DƯ:</b></td>
						<td><b style="color:blue;font-size:20px;">' . BASE_URL('') . "api/historyacbbalance/" . $token . '</b></td>
					</tr>
					</tbody>
					</table>
					<h2>APIBANK.COM.VN - KẾT NỐI TƯƠNG LAI</h2>';
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
        $tool = $NNL->get_row(" SELECT * FROM `account_acb` WHERE `id` = '$id' AND `user_id`='" . $getUser['id'] . "' ");
        if (!$tool) {
            exit(json_encode(array('status' => '1', 'msg' => 'Định hack à không dễ vậy đâu!')));
        }
        $NNL->remove("account_acb", "`id`='" . $id . "'");
        exit(json_encode(array('status' => '2', 'msg' => 'Đã xóa tài khoản thành công!')));
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['action']) && $_GET['action'] == 'LOGIN') {
        if (empty($_GET['token'])) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        if (!$getUser = $NNL->get_row("SELECT * FROM `users` WHERE `token` = '" . xss($_GET['token']) . "' AND `banned` = '0' ")) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        $account = xss($_GET['account']);
        $password = xss($_GET['password']);
        $stk = xss($_GET['stk']);
        if (empty($account)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập tài khoản ACB',
            ]));
        }
        if (empty($password)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập mật khẩu ACB',
            ]));
        }
        if (empty($stk)) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Vui lòng nhập số tài khoản ACB',
            ]));
        }
        if ($getUser['time_momo'] < time()) {
            die(json_encode([
                'status' => '1',
                'msg' => 'Gói API của bạn đã hết hạn sử dụng, vui lòng nâng cấp để tiếp tục sử dụng',
            ]));
        }

				$token = md5(random('QWERTYUIOPASDGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm0123456789', 6) . time());
				$response = $acb->loginAcb($account, $password);
				$accessToken = $response['accessToken'];
				$displayName = $response['identity'] ['displayName'];
				if ($response['identity'] ['active'] == 1)
				{
		
		
            $create = $NNL->insert("account_acb", [
                'user_id' => $getUser['id'],
                'phone' => $account,
                'stk' => $stk,
                'name' => $displayName,
                'password' => $password,
                'sessionId' => $accessToken,
                'deviceId' => '',
                'token' => $token,
                'time' => time(),
            ]);
            exit(json_encode(array('status' => '2', 'msg' => 'Thêm tài khoản thành công')));
				} else exit(json_encode(array('status' => '1', 'msg' => 'Tài khoản không hợp lệ')));

        }
    

    if (isset($_GET['action']) && $_GET['action'] == 'SENDTOKEN') {
        if (empty($_GET['token'])) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        if (!$getUser = $NNL->get_row("SELECT * FROM `users` WHERE `token` = '" . xss($_GET['token']) . "' AND `banned` = '0' ")) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        $id = xss($_GET['id']);
        if (empty($id)) {
            exit(json_encode(array('status' => '1', 'msg' => 'Vui lòng điền thông tin!')));
        }
        $row = $NNL->get_row(" SELECT * FROM `account_acb` WHERE `id` = '$id' AND `user_id`='" . $getUser['id'] . "' ");
        if (!$row) {
            exit(json_encode(array('status' => '1', 'msg' => 'Tài khoản acb không tồn tại!')));
        }
        $user = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $row['user_id'] . "' ");
        if (!$user) {
            exit(json_encode(array('status' => '1', 'msg' => 'Người dùng không tồn tại!')));
        }

        $guitoi = $user['email'];
        $subject = 'TOKEN acb';
        $bcc = "API SYSTEM";
        $hoten = 'Client';
        $token = $row['token'];
        $noi_dung = '<h3>Có ai đó vừa yêu cầu gửi token acb bằng Email này, nếu là bạn thì token bên dưới dùng để chạy api</h3>
            <table>
            <tbody>
            <tr>
            <td style="font-size:20px;">OTP:</td>
            <td><b style="color:blue;font-size:30px;">' . $token . '</b></td>
            </tr>
            </tbody>
            </table>';
        Locdz_Email($guitoi, $hoten, $subject, $noi_dung, $bcc);
        exit(json_encode(array('status' => '2', 'msg' => 'Đã gửi token đến mail của bạn!')));
    }
    //get name
    if (isset($_GET['action']) && $_GET['action'] == 'REMOVE') {
        if (empty($_GET['token'])) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        if (!$getUser = $NNL->get_row("SELECT * FROM `users` WHERE `token` = '" . xss($_GET['token']) . "' AND `banned` = '0' ")) {
            die(json_encode(['status' => '1', 'msg' => 'Vui lòng đăng nhập']));
        }
        $id = xss($_GET['id']);
        if (empty($id)) {
            exit(json_encode(array('status' => '1', 'msg' => 'Không được!')));
        }
        $tool = $NNL->get_row(" SELECT * FROM `account_acb` WHERE `id` = '$id' AND `user_id`='" . $getUser['id'] . "' ");
        if (!$tool) {
            exit(json_encode(array('status' => '1', 'msg' => 'Định hack à không dễ vậy đâu!')));
        }
        $NNL->remove("account_acb", "`id`='" . $id . "'");
        exit(json_encode(array('status' => '2', 'msg' => 'Đã xóa tài khoản thành công!')));
    }
}