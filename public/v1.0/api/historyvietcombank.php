<?php
define("IN_SITE", true);
require_once "../core/DB.php";
require_once "../core/helpers.php";
require_once "../core/class/vcb.php";
error_reporting(0);
set_time_limit(0);
date_default_timezone_set('Asia/Ho_Chi_Minh');
$vcb = new vcb;
if ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (yugetpost('type') == 'history') {
        if (empty(yugetpost('token'))) {
            die(json_encode(['status' => 'false', 'msg' => 'Thiếu Token']));
        }
        $getData = $NNL->get_row(" SELECT * FROM `account_vietcombank` WHERE `token` = '" . xss($_GET["token"]) . "' LIMIT 1");
        if ($getData) {
            $myUser = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $getData["user_id"] . "'");
            if ($myUser['time_momo'] < time()) {
                die(json_encode(['status' => 'false', 'msg' => 'Tài khoản của bạn đã hết hạn sử dụng, vui lòng nâng cấp gói để tiếp tục sử dụng!']));
            } else {
                $lichsu = $vcb->get_lsgd($getData['username'], $getData['account'], $getData['session_id'], $getData['cif'], $getData['client_id'], $getData['mobile_id']);
                if (json_decode($lichsu)->code != '00') {
                    $response = $vcb->getCaptcha($NNL->site('key_captcha'));
                    $captcha_id = json_decode($response, true)['data']['captcha_id'];
                    $captcha = json_decode($response, true)['data']['captcha'];
                    $token = md5(random('QWERTYUIOPASDGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm0123456789', 6) . time());
                    $login = json_decode($vcb->login($getData['username'], $getData['password'], $captcha_id, $captcha), true);
                    if ($login['code'] == '00') {
                        $NNL->update("account_vietcombank", [
                            'session_id' => $login['sessionId'],
                            'access_key' => $login['accessKey'],
                            'client_id' => $login['userInfo']['clientId'],
                            'mobile_id' => $login['userInfo']['mobileId'],
                            'cif' => $login['userInfo']['cif'],
                        ], " `username` = '" . $getData['username'] . "' ");

                    }
					$lichsu = $vcb->get_lsgd($getData['username'], $getData['account'], $login['sessionId'], $login['userInfo']['cif'], $login['userInfo']['clientId'], $login['userInfo']['mobileId']);
					print_r($lichsu);
                } else {
                    print_r($lichsu);
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
        $getData = $NNL->get_row(" SELECT * FROM `account_vietcombank` WHERE `token` = '" . xss($_GET["token"]) . "' LIMIT 1");
        if ($getData) {
            $myUser = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '" . $getData["user_id"] . "'");
            if ($myUser['time_momo'] < time()) {
                die(json_encode(['status' => 'false', 'msg' => 'Tài khoản của bạn đã hết hạn sử dụng, vui lòng nâng cấp gói để tiếp tục sử dụng!']));
            } else {
                $balance = json_decode($vcb->get_balance($getData['username'], $getData['account'], $getData['session_id'], $getData['cif'], $getData['client_id'], $getData['mobile_id']), true);
                if ($balance['code'] != '00') {
                    $response = $vcb->getCaptcha($NNL->site('key_captcha'));
                    $captcha_id = json_decode($response, true)['data']['captcha_id'];
                    $captcha = json_decode($response, true)['data']['captcha'];
                    $token = md5(random('QWERTYUIOPASDGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm0123456789', 6) . time());
                    $login = json_decode($vcb->login($getData['username'], $getData['password'], $captcha_id, $captcha), true);
                    if ($login['code'] == '00') {
                        $NNL->update("account_vietcombank", [
                            'session_id' => $login['sessionId'],
                            'access_key' => $login['accessKey'],
                            'client_id' => $login['userInfo']['clientId'],
                            'mobile_id' => $login['userInfo']['mobileId'],
                            'cif' => $login['userInfo']['cif'],
                        ], " `username` = '" . $getData['username'] . "' ");
                    }
					$balance = json_decode($vcb->get_balance($getData['username'], $getData['account'], $login['sessionId'], $login['userInfo']['cif'], $login['userInfo']['clientId'], $login['userInfo']['mobileId']), true);					
					 exit(json_encode(array('status' => '200', 'SoDu' => '' . str_replace(',', '', $balance['accountDetail']['availBalance']) . '')));
                } else {

                    if (isset($balance['code']) == '00') {
                        exit(json_encode(array('status' => '200', 'SoDu' => '' . str_replace(',', '', $balance['accountDetail']['availBalance']) . '')));
                    } else {
                        exit(json_encode(array('status' => '99', 'SoDu' => '0')));
                    }
                }
              
               
            }
        } else {
            die(json_encode(['status' => 'false', 'msg' => 'Authorization Token not found']));
        }
    }
} 