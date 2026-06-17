<?php
$title = 'Lịch sử giao dịch mbbank | ' . $NNL->site('title');
$body['header'] = '
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
';
$body['footer'] = '

';
require_once __DIR__ . '/../../../core/is_user.php';
CheckLogin();
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/../../../core/class/mbbank.php';
error_reporting(0);
$mbbank = new MBBANK;
?>
<?php
if (isset($_GET['stk'])) {
    $getData = $NNL->get_row(" SELECT * FROM `account_mbbank` WHERE `stk` = '" . xss($_GET['stk']) . "' AND `user_id`='" . $getUser['id'] . "' ");
    if ($getData) {
        $lichsu = json_decode($mbbank->get_lsgd($getData['phone'], $getData['sessionId'], $getData['deviceId'], $getData['stk'], 7), true);
        //if ($getData['time'] < time() - 60) {
            if ($lichsu['result']['message'] == 'Session invalid') {
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
            }
        //}
        $tranList = array();
        foreach ($lichsu['transactionHistoryList'] as $transaction) {
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

            );

        }
        // echo json_encode($tranList);
        $json = json_encode(array(
            "status" => "success",
            "message" => "Thành công",
            "TranList" => $tranList,
        ));
        $result = json_decode($json, true);
    } else {
        nnl_error_time("Liên kết không tồn tại", BASE_URL(''), 500);
    }
} else {
    nnl_error_time("Liên kết không tồn tại", BASE_URL(''), 0);
}

?>


<!-- [ Main Content ] start -->
<section class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Lịch sử giao dịch</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Lịch sử giao dịch</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ stiped-table ] start -->
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-6">
                                <div class="justify-content-start">
                                    <h5>Lịch sử giao dịch của: <?=xss($_GET['stk'])?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table w-100" id="datatable">
                                <thead>
                                    <tr>
                                        <th class="whitespace-nowrap">THỜI GIAN</th>
                                        <th class="whitespace-nowrap">MÃ GIAO DỊCH</th>
                                        <th class="whitespace-nowrap">SỐ TIỀN CHUYỂN</th>
                                        <th class="whitespace-nowrap">SỐ TIỀN NHẬN</th>
                                        <th class="whitespace-nowrap">NỘI DUNG</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result['TranList'] as $value) {?>
                                    <tr class="intro-x">
                                        <td>
                                            <div><?=$value['transactionDate']?>
                                            </div>
                                        </td>
                                        <td style="color:blue">
                                            <div><?=$value['tranId']?></div>
                                        </td>
                                        <td style="color:red"><?=format_cash($value['debitAmount'])?>đ</td>
                                        <td style="color:green"><?=format_cash($value['creditAmount'])?>đ</td>
                                        <td><?=$value['description']?>
                                        </td>
                                    </tr>
                                    <?php }?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ stiped-table ] end -->

        </div>
        <!-- [ Main Content ] end -->
    </div>
</section>
<script>
$('#datatable').DataTable({
    language: {
        url: "<?=BASE_URL('public/assets/Vietnamese.json')?>"
    },
});
</script>
<?php require_once __DIR__ . '/footer.php';?>