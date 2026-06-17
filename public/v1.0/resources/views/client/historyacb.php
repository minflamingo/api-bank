<?php
$title = 'Lịch sử giao dịch ACB | ' . $NNL->site('title');
$body['header'] = '
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
';
$body['footer'] = '

';
define("IN_SITE", true);
require_once __DIR__ . "/../../../core/DB.php";
require_once __DIR__ . "/../../../core/helpers.php";
require_once __DIR__ . '/../../../core/is_user.php';
CheckLogin();
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/../../../core/class/acb.php';
error_reporting(0);
$acb = new ACB;

if (isset($_GET['stk'])) {
    $getData = $NNL->get_row(" SELECT * FROM `account_acb` WHERE `stk` = '" . xss($_GET['stk']) . "' AND `user_id`='" . $getUser['id'] . "' ");
    if ($getData) {

			 
			$lsgd =  $acb->getTransactionHistory($_GET['stk'],20,$getData['sessionId'] );
			
			if ($lsgd['codeStatus'] == '200'){
			
			$result = json_decode($lsgd, true);
			
			} else {
			
				$login = $acb->loginAcb($getData['phone'], $getData['password']);
				$accessToken = $login['accessToken'];
			
			if ($login['identity'] ['active'] == 1) {
                $NNL->update("account_acb", [
                    'sessionId' => $accessToken,
					'time' => time(),
                ], " `username` = '" . $getData['username'] . "' ");

            
				$lsgd =  $acb->getTransactionHistory($getData['stk'],20,$accessToken);
				
				
				$result = json_decode($lsgd, true);
				
			} 	
				
			}

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
                                        <th class="whitespace-nowrap">Thời gian</th>
                                        <th class="whitespace-nowrap">Loại</th>
                                        <th class="whitespace-nowrap">Mã giao dịch</th>
                                        <th class="whitespace-nowrap">Số tiền</th>
                                        <th class="whitespace-nowrap">Nội dung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result['data'] as $value) {?>
                                    <tr class="intro-x">
                                        <td>
                                            <div><?=date("H:i:s d-m-Y ", $value['effectiveDate']/1000)?>
                                            </div>
                                        </td>
										<td><?= $value['type'] == 'IN' ? 'Nhận tiền' : 'Trừ tiền' ?></td>
                                        <td style="color:blue">
                                            <div><?=$value['transactionNumber']?></div>
                                        </td>
                                         
                                        <td style="color:green"><?=format_cash($value['amount'])?>đ</td>
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
	"order": [[0, "desc"]],
    language: {
        url: "<?=BASE_URL('public/assets/Vietnamese.json')?>"
    },
});
</script>
<?php require_once __DIR__ . '/footer.php';?>