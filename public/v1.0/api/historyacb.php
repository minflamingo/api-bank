<?php
define("IN_SITE", true);
require_once "../core/DB.php";
require_once "../core/helpers.php";
require_once "../core/class/acb.php";

error_reporting(0);
set_time_limit(0);
date_default_timezone_set('Asia/Ho_Chi_Minh');
$acb = new ACB;
if ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (yugetpost('type') == 'history') {
        if (empty(yugetpost('token'))) {
            die(json_encode(['status' => 'false', 'msg' => 'Thiếu Token']));
        }
        $getData = $NNL->get_row(" SELECT * FROM `account_acb` WHERE `token` = '" . xss($_GET["token"]) . "' LIMIT 1");
        if ($getData) {
            $myUser = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $getData["user_id"] . "'");
            if ($myUser['time_momo'] < time()) {
                die(json_encode(['status' => 'false', 'msg' => 'Tài khoản của bạn đã hết hạn sử dụng, vui lòng nâng cấp gói để tiếp tục sử dụng!']));
            } else {               				
				$lsgd = $acb->getTransactionHistory($getData['stk'], 20, $getData['sessionId']);
				$lsgd2 = json_decode($lsgd, true); // Giải mã JSON

				if ($lsgd2['codeStatus'] == 200) 
					{				
						print_r($lsgd);					
					} else {				
						$login = $acb->loginAcb($getData['phone'], $getData['password']);
						$accessToken = $login['accessToken'];		
						if ($login['identity'] ['active'] == 1) {
							$NNL->update("account_acb", [
								'sessionId' => $accessToken,
								'time' => time(),
							], " `phone` = '" . $getData['phone'] . "' ");	
						}
						$lsgd =  $acb->getTransactionHistory($getData['stk'],20,$accessToken);						
						//print_r($login);
						print_r($lsgd);
						//$result = json_decode($lsgd, true);								
					}			
            }
        } else {
            die(json_encode(['status' => 'false', 'msg' => 'Authorization Token not found']));
        }
    }
    if (yugetpost('type') == 'balance') {
        if (empty(yugetpost('token'))) {
            die(json_encode(['status' => 'false', 'msg' => 'Thiếu Token']));
        }
        $getData = $NNL->get_row(" SELECT * FROM `account_acb` WHERE `token` = '" . xss($_GET["token"]) . "' LIMIT 1");
        if ($getData) {
            $myUser = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $getData["user_id"] . "'");
            if ($myUser['time_momo'] < time()) {
                die(json_encode(['status' => 'false', 'msg' => 'Tài khoản của bạn đã hết hạn sử dụng, vui lòng nâng cấp gói để tiếp tục sử dụng!']));
            } else {
                

				$lsgd = $acb->getBalance($getData['sessionId']);
				$lsgd = json_decode($lsgd, true); // Giải mã JSON

				if ($lsgd['codeStatus'] == 200) {			
					//print_r ($lsgd);
					print_r(json_encode(array('status' => '200', 'SoDu' => '' . str_replace(',', '', $lsgd['data'][0]['balance']) . '')));
				} else {					
							$login = $acb->loginAcb($getData['phone'], $getData['password']);
							$accessToken = $login['accessToken'];		
							//if ($login['identity'] ['active'] == '1') {
								$NNL->update("account_acb", [
									'sessionId' => $accessToken,
									'time' => time(),
								], " `phone` = '" . $getData['phone'] . "' ");	
							//}
							$lsgd = $acb->getBalance($accessToken);
							$lsgd = json_decode($lsgd, true); // Giải mã JSON					
							//echo $lsgd['codeStatus'].'123'.$lsgd;							
							print_r(json_encode(array('status' => '200', 'SoDu' => '' . str_replace(',', '', $lsgd['data'][0]['balance']) . '')));
							//$result = json_decode($lsgd, true);											
				}
			}
        } else {
            die(json_encode(['status' => 'false', 'msg' => 'Authorization Token not found']));
        }
    
}
} 