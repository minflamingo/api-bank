<?php
define("IN_SITE", true);
require_once("../core/DB.php");
require_once("../core/helpers.php");

$result = curl_get(BASE_URL('') . "api/historyacb/".$NNL->site('token_acb'));
$result = json_decode($result, true);
//print_r($result);
$getbank = $NNL->get_row(" SELECT * FROM `bank` WHERE `codebank` = '970416' ");
foreach ($result['data'] as $data) {
    $loai	        = $data['type'];               // NHẬN TIỀN HAY CHUYỂN TIỀN
    $comment        = $data['description'];                 // NỘI DUNG CHUYỂN TIỀN
    $tranId         = $data['transactionNumber'].".".date("dmy", intval($data['postingDate']) / 1000);                  // MÃ GIAO DỊCH
    //$partnerName    = $data['partnerName'];             // TÊN CHỦ VÍ
    $amount         = str_replace(",", "", $data['amount']);                  // SỐ TIỀN CHUYỂN
    $user_id        = parse_order_id($comment, $getbank['noidungnap']);              // TÁCH NỘI DUNG CHUYỂN TIỀN
    // XỬ LÝ AUTO
    if ($getUser = $NNL->get_row(" SELECT * FROM `users` WHERE `id` = '$user_id' ")) {
        if ($NNL->num_rows(" SELECT * FROM `invoices` WHERE `trans_id` = '$tranId' ") == 0) {
            $insertSv2 = $NNL->insert("invoices", array(
                'trans_id'               => $tranId,
                'payment_method'    => "ACB",
                'user_id'           => $getUser['id'],
                'description'       => $comment,
                'amount'            => $amount,
                'status'            => 1,
                'create_time'       => time()
            ));
            if ($insertSv2) {
                $isCong = PlusCredits($getUser['id'], ($loai == "IN") ? $amount : -$amount, ($loai == "IN") ? ("Nạp tiền tự động qua ACB (#$tranId *Nội dung: $comment *Số tiền: $amount)") : ("Trừ tiền tự động do hoàn tiền từ ACB (#$tranId *Nội dung: $comment *Số tiền: -$amount)"));
                if ($isCong) {
                    echo 'OK';
					$txttele = ' GIAO DỊCH NẠP TIỀN \n Người dùng: '.$getUser['id'].'\n Số Tiền: ' . format_cash($amount)  . '\n Mã GD: ' . $tranId . '\n Nội dung: ' . $comment .'\n Lúc: ' . date("H:i d-m-Y");
					odertele($txttele);
                } else echo 'Nạp tiền thất bại liên hệ admin.';
            }
        }
    }
}

