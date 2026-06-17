<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$title = 'Nạp tiền | ' . $NNL->site('title');
$body['header'] = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
';
$body['footer'] = '
    
';
require_once __DIR__ . '/../../../core/is_user.php';
CheckLogin();
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/nav.php';

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
                            <h5 class="m-b-10">Nạp tiền</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('client/recharge')?>">Nạp tiền</a></li>
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

                <div class="row">
				
                    <?php foreach ($NNL->get_list("SELECT * FROM `bank`") as $row) { 
					
					$qrDataURL = BASE_URL('public/assets/images/logo_light.png');
					
					?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <div style="height: 300px;">
                                        <img src="<?=$qrDataURL ?>" alt="QR Code" class="mx-auto qr-code-bank-<?= $row['id'] ?>" width="100%" height="100%">
                                    </div>
                                </div>
                                <div class="card-body text-center">							
                                    <ul class="list-group mb-2">
                                        <li class="list-group-item">Số tài khoản: <b id="copySTK11" style="color: green;"><?= $row['accountNumber'] ?></b> <button onclick="copy()" data-clipboard-target="#copySTK11" class="copy btn btn-primary btn-sm"><i class="fas fa-copy"></i></button>
                                        </li>
                                        <li class="list-group-item">Chủ tài khoản: <b><?= $row['accountName'] ?></b>
                                        </li>
                                        <li class="list-group-item">Ngân hàng: <b><?= $row['short_name'] ?></b></li>
                                        <li class="list-group-item">Nội dung nạp: <b id="copyNoiDung11" style="color: red;"><?= $row['noidungnap'], $getUser['id'] ?></b>
                                            <button onclick="copy()" data-clipboard-target="#copyNoiDung11" class="copy btn btn-primary btn-sm"><i class="fas fa-copy"></i></button>
                                        </li>
										
                                    </ul>
                                    <center><i class="transactionStatus"><i class="fa fa-spinner fa-spin"></i> Xử lý giao dịch tự động trong vài
                                            giây... </i></center>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
						<div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-6">
                                <div class="justify-content-start">
                                    <h5>Lịch sử giao dịch</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table w-100" id="datatable">
                                <thead>
                                     <tr>
											<th class="whitespace-nowrap">STT</th> 
											<th class="whitespace-nowrap">NGÂN HÀNG</th> 
											<th class="whitespace-nowrap">MÃ GIAO DỊCH</th> 
											<th class="whitespace-nowrap">SỐ TIỀN</th> 
											 
											<th class="whitespace-nowrap">THỜI GIAN</th> 
											<th class="whitespace-nowrap">NỘI DUNG NẠP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
										$counter = 1; // Khởi tạo biến đếm
										foreach ($NNL->get_list("SELECT * FROM `invoices` WHERE `user_id` = '".$getUser['id']."'") as $row) { ?>	
                                    <tr class="intro-x">
                                        <td><?php echo $counter; ?></td>
										<td>
                                            <?php echo $row['payment_method']; ?>
                                            
                                        </td>
										<td><?php echo $row['trans_id']; ?></td>
                                        <td style="color:green">
                                            <div><?php echo $row['amount']; ?>đ</div>
                                        </td>
                                         
                                        
                                        <td><?php echo date("H:i:s d-m-Y ", $row['create_time']); ?>
                                        </td>
										<td style="color:blue"><?php echo $row['description']; ?></td>
                                    </tr>
                                    <?php 
										$counter++; // Tăng biến đếm lên sau mỗi lần lặp
										} ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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

window.onload = function() {
    // Lấy tất cả các ảnh có class chứa 'qr-code-bank-'
    var imgs = document.querySelectorAll('img[class*="qr-code-bank-"]');

    imgs.forEach(function(img) {
        var bankId = img.classList[1].split('-')[3]; // Giả sử class thứ hai chứa id
		var userid = '<?php echo $getUser['id']; ?>';
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '<?=BASE_URL('ajaxs/client/imgvietqr.php')?>' + '?bankId=' + bankId + '&userid=' + userid, true);

        xhr.onload = function() {
            if (this.status == 200) {
                img.src = this.responseText; // Cập nhật src của ảnh
            } else {
                cuteToast({
                        type: "error",
                        message: 'Lỗi load ảnh!',
                        timer: 2000
                    });
            }
        };

        xhr.onerror = function() {
            // Xử lý lỗi kết nối ở đây
			cuteToast({
                        type: "error",
                        message: 'Không thể kết nối!',
                        timer: 2000
                    });
        };

        xhr.send();
    });
};


    new ClipboardJS(".copy");

    function copy() {
        cuteToast({
            type: "success",
            message: "Đã sao chép vào bộ nhớ tạm",
            timer: 5000
        });
    }



	

var userId = '<?php echo $getUser['id']; ?>';
var successCount = 0;
var lasttime = null;  // ID của giao dịch cuối cùng đã được xử lý


function fetchData() {
	var currentTime = Math.floor(Date.now() / 1000) - 10;
	var xhr = new XMLHttpRequest();
	xhr.open('GET', '<?=BASE_URL('ajaxs/client/checknaptien.php')?>' + '?action=checkTransaction&userId=' + userId + '&currentTime=' + currentTime, true);
	xhr.onreadystatechange = function() {
		if (xhr.readyState == 4 && xhr.status == 200) {
			if (xhr.responseText !== 'FAIL' && xhr.responseText !== lasttime) {
				successCount++;
				lasttime = xhr.responseText; // Cập nhật ID của giao dịch cuối cùng đã được xử lý
				//document.getElementById('transactionStatus').innerHTML = '<i class="fa fa-check-circle"></i> Đã hoàn thành ' + successCount + ' giao dịch, hãy sử dụng dịch vụ hoặc tiếp tục nạp tiền!';
				var elements = document.querySelectorAll('.transactionStatus');
                elements.forEach(function(element) {
                    element.innerHTML = '<i class="fa fa-check-circle"></i> Đã hoàn thành ' + successCount + ' giao dịch, hãy sử dụng dịch vụ hoặc tiếp tục nạp tiền!';
                });
				cuteToast({
				type: "success",
				message: 'Nạp thành công ' + successCount + ' hóa đơn.',
				timer: 10000
				});
			}
		// Xử lý các trường hợp khác nếu cần
		}
	};
	xhr.send();
}

setInterval(fetchData, 2000); // Gọi hàm fetchData mỗi 2 giây

$('#datatable').DataTable({
	"order": [[0, "desc"]],
    language: {
        url: "<?=BASE_URL('public/assets/Vietnamese.json')?>"
    },
});
</script>


<?php require_once(__DIR__ . '/footer.php'); ?>