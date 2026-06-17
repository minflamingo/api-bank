<?php
define("IN_SITE", true);
require_once "../core/DB.php";
require_once "../core/helpers.php";
require_once "../core/class/mbbank.php";
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
set_time_limit(0);
date_default_timezone_set('Asia/Ho_Chi_Minh');
$mbbank = new MBBANK;
if ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (yugetpost('type') == 'history') {
        if (empty(yugetpost('token'))) {
            die(json_encode(['status' => 'false', 'msg' => 'Thiếu Token']));
        }
        $getData = $NNL->get_row(" SELECT * FROM `account_mbbank` WHERE `token` = '" . xss(yugetpost('token')) . "' LIMIT 1");
        if ($getData) {
            $myUser = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $getData["user_id"] . "'");
            if ($myUser['time_momo'] < time()) {
                die(json_encode(['status' => 'false', 'msg' => 'Tài khoản của bạn đã hết hạn sử dụng, vui lòng nâng cấp gói để tiếp tục sử dụng!']));
            } else {

                $lichsu = $mbbank->get_lsgd($getData['phone'], $getData['sessionId'], $getData['deviceId'], $getData['stk'], 7);
				$lichsu2 = json_decode($lichsu, true);
                //if ($getData['time'] < time() - 60) {
                    if ($lichsu2['result']['message'] != 'OK') {
                        $mbbank->deviceIdCommon_goc = $mbbank->generateImei();
                        $mbbank->user = check_string($getData['phone']);
                        $mbbank->pass = $getData['password'];
                        $text_captcha = $mbbank->bypass_captcha_stc($NNL->site('key_captcha'));
                        $login = json_decode($mbbank->login($text_captcha), true); //responseCode 283 lỗi captcha, GW21 thông tin sai
                        if ($login['result']['message'] == "Capcha code is invalid") {
                            exit(json_encode(array('status' => '1', 'msg' => 'Captcha không chính xác')));
                        } else if ($login['result']['message'] == 'Customer is invalid') {
                            exit(json_encode(array('status' => '1', 'msg' => 'Thông tin không chính xác')));
                        } else {
                            $NNL->update("account_mbbank", [
                                'name' => $login['cust']['nm'],
                                'password' => $getData['password'],
                                'sessionId' => $login['sessionId'],
                                'deviceId' => $mbbank->deviceIdCommon_goc,
                                'time' => time(),
                            ], " phone = '" . $getData['phone'] . "' ");

                        }
                    $lichsu = $mbbank->get_lsgd($getData['phone'], $login['sessionId'], $mbbank->deviceIdCommon_goc, $getData['stk'], 7);
					$lichsu2 = json_decode($lichsu, true);
					
					}
                //}
				//print_r($lichsu2['transactionHistoryList'][0][accountNo]);
				//print_r($lichsu2);
                $tranList = array();
				foreach ($lichsu2['transactionHistoryList'] as $transaction) {
					$tranList[] = array(
						"tranId" => $transaction['refNo'],
						"postingDate" => $transaction['postingDate'],
						"transactionDate" => $transaction['transactionDate'],
						"accountNo" => $transaction['accountNo'],
						"creditAmount" => $transaction['creditAmount'],
						"debitAmount" => $transaction['debitAmount'],
						"currency" => $transaction['currency'],
						"description" => $transaction['description'],
						"availableBalance" => $transaction['availableBalance'],
						"beneficiaryAccount" => $transaction['beneficiaryAccount'],
						// Thêm các trường khác nếu cần
					);
				}
                //echo $tranList;
                print_r(json_encode(array(
                    "status" => "success",
                    "message" => "Thành công",
                    "TranList" => $tranList,
                )));

            }

        } else {
            die(json_encode(['status' => 'false', 'msg' => 'Authorization Token not found']));
        }
    }
    if (yugetpost('type') == 'balance') {
        if (empty(yugetpost('token'))) {
            die(json_encode(['status' => 'false', 'msg' => 'Thiếu Token']));
        }
        $getData = $NNL->get_row(" SELECT * FROM `account_mbbank` WHERE `token` = '" . xss(yugetpost('token')) . "' LIMIT 1");
        if ($getData) {
            $myUser = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $getData["user_id"] . "'");
            if ($myUser['time_momo'] < time()) {
                die(json_encode(['status' => 'false', 'msg' => 'Tài khoản của bạn đã hết hạn sử dụng, vui lòng nâng cấp gói để tiếp tục sử dụng!']));
            } else {
                $balance = $mbbank->get_balance($getData['phone'], $getData['sessionId'], $getData['deviceId']);
				$balance2 = json_decode($balance, true);
                //if ($getData['time'] < time() - 60) {
                    if ($balance2['result']['message'] == 'Session invalid') {
                        $mbbank->deviceIdCommon_goc = $mbbank->generateImei();
                        $mbbank->user = check_string($getData['phone']);
                        $mbbank->pass = $getData['password'];
                        $text_captcha = $mbbank->bypass_captcha_stc($NNL->site('key_captcha'));
                        $login = json_decode($mbbank->login($text_captcha), true); //responseCode 283 lỗi captcha, GW21 thông tin sai
                        if ($login['result']['message'] == "Capcha code is invalid") {
                            exit(json_encode(array('status' => '1', 'msg' => 'Captcha không chính xác')));
                        } else if ($login['result']['message'] == 'Customer is invalid') {
                            exit(json_encode(array('status' => '1', 'msg' => 'Thông tin không chính xác')));
                        } else {
                            $NNL->update("account_mbbank", [
                                'name' => $login['cust']['nm'],
                                'password' => $getData['password'],
                                'sessionId' => $login['sessionId'],
                                'deviceId' => $mbbank->deviceIdCommon_goc,
                                'time' => time(),
                            ], " phone = '" . $getData['phone'] . "' ");

                        }
                    
					$balance = $mbbank->get_balance($getData['phone'], $login['sessionId'], $mbbank->deviceIdCommon_goc);
					$balance2 = json_decode($balance, true);
					}
					//print_r($balance);
                //}
                if ($balance2['result']['message'] == 'OK') {
                    foreach ($balance2['acct_list'] as $data) {
                        if ($data['acctNo'] == $getData['stk']) {
                            $status = true;
                            $message = 'Giao dịch thành công';
                            exit(json_encode(array('status' => '200', 'SoDu' => '' . $data['currentBalance'] . '')));
                        }
                    }

                } else {
                    exit(json_encode(array('status' => '99', 'SoDu' => '0')));
                }
            }
        } else {
            die(json_encode(['status' => 'false', 'msg' => 'Authorization Token not found']));
        }
    }
}


