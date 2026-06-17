<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$title = 'Danh sách tài khoản Mbbank | ' . $NNL->site('title');
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
                            <h5 class="m-b-10">Danh sách tài khoản Mbbank</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Danh sách tài khoản Mbbank</a></li>
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
                                    <h5>Tài khoản Mbbank</h5>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-end">
                                    <a href="<?=BASE_URL('client/add-account-mbbank')?>" class="btn btn-success btn-sm">Thêm Mbbank</a>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table w-100" id="datatable">
                                <thead>
                                    <tr>
                                    <th>TÀI KHOẢN</th>
                                    <th>SỐ TÀI KHOẢN KHOẢN</th>
                                    <th>CHỦ TÀI KHOẢN</th>
                                    <th>SỐ DƯ</th>
                                    <th>THỜI GIAN THÊM</th>
                                    <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($NNL->get_list("SELECT * FROM `account_mbbank` WHERE `user_id`='" . $getUser['id'] . "'") as $row) { ?>
                                        <tr>
                                            <td><?= $row['phone'] ?></td>
                                            <td><?= $row['stk'] ?></td>
                                            <td><?= $row['name'] ?></td>
                                            <!--<td><?//=format_cash(getMoney_mbbank($row['token']))?>đ</td>-->
											<td class="money-mbbank" data-token="<?= $row['token'] ?>">Đang tải...</td>
                                            <td><?= date('h:i:s d-m-Y',$row['time']) ?></td>
                                            <td class="table-action">
                                                <input type="hidden" class="form-control" id="token" value="<?= $getUser['token'] ?>">
                                                <a href="<?= BASE_URL('client/viewhismbbank/'), $row['stk'] ?>"><button class="btn btn-success btn-xs" type="button"><i class="fa fa-list"></i> Lịch sử giao dịch</button></a>
                                                <button class="btn btn-warning btn-xs" onclick="GetToken(<?= $row['id'] ?>)" type="button"><i class="fa fa-power-off"></i> Lấy Token</button>
                                                <button class="btn btn-danger btn-xs" onclick="Delete(<?= $row['id'] ?>)" type="button"><i class="fa fa-trash"></i> Xóa</button>
                                            </td>
                                        </tr>
                                    <?php } ?>
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

<script type="text/javascript">
    $('#datatable').DataTable({
        language: {
            url: "<?= BASE_URL('public/assets/Vietnamese.json') ?>"
        },
    });
	var ajaxRequests = []; // Mảng lưu trữ các yêu cầu AJAX

	function abortAllAjaxRequests() {
		ajaxRequests.forEach(function(request) {
			request.abort();
		});
		ajaxRequests = [];
	}

$('a').on('click', function(e) {
    var targetUrl = $(this).attr('href'); // Lấy URL của liên kết

    // Hủy bỏ tất cả các yêu cầu AJAX
    abortAllAjaxRequests();

    // Chuyển hướng người dùng ngay lập tức
    window.location.href = targetUrl;

    //e.preventDefault(); // Ngăn chặn hành động mặc định của liên kết
});


	function updateBalances() {
		$('.money-mbbank').each(function() {
			var element = $(this);
			var token = element.data('token');
			var apiUrl = "<?= BASE_URL('api/historymbbankbalance/') ?>" + token;

			var ajaxRequest = $.ajax({
				type: "GET",
				url: apiUrl,
				dataType: "json",
				success: function(response) {
					if (response.status === "200") {
						var formattedBalance = parseInt(response.SoDu).toLocaleString('en-US');
						element.html(formattedBalance + 'đ');
					} else {
						element.html('Lỗi khi tải dữ liệu');
					}
				},
				error: function() {
					element.html('Lỗi kết nối');
				}
			});

			ajaxRequests.push(ajaxRequest); // Lưu trữ yêu cầu AJAX vào mảng
		});
	}

	updateBalances();
	setInterval(updateBalances, 10000);

    function Delete(id) {
        cuteAlert({
            type: "question",
            title: "Xác nhận xóa tài khoản",
            message: "Bạn có chắc chắn muốn xóa không ?",
            confirmText: "Đồng Ý",
            cancelText: "Huỷ"
        }).then((e) => {
            if (e) {
                $.ajax({
                    type: "post",
                    url: "<?= BASE_URL("ajaxs/client/mbbank.php"); ?>",
                    dataType: "json",
                    data: {
                        action: "REMOVE",
                        id: id,
                        token: $("#token").val()
                    },
                    success: function(data) {
                        if (data.status == '2') {
                            cuteToast({
                                type: "success",
                                message: data.msg,
                                timer: 5000
                            });
                            setTimeout(function() {
                                window.location = "<?= BASE_URL('client/list-mbbank') ?>"
                            }, 1000);
                        } else {
                            cuteToast({
                                type: "error",
                                message: data.msg,
                                timer: 5000
                            });
                        }
                    }
                });
            }
        })
    }

    function GetToken(id) {
        cuteAlert({
            type: "question",
            title: "Xác nhận lấy token",
            message: "Bạn có chắc chắn muốn lấy token qua Email không ?",
            confirmText: "Đồng Ý",
            cancelText: "Huỷ"
        }).then((e) => {
            if (e) {
                $.ajax({
                    type: "post",
                    url: "<?= BASE_URL("ajaxs/client/mbbank.php"); ?>",
                    dataType: "json",
                    data: {
                        action: "SENDTOKEN",
                        id: id,
                        token: $("#token").val()
                    },
                    success: function(data) {
                        if (data.status == '2') {
                            cuteToast({
                                type: "success",
                                message: data.msg,
                                timer: 5000
                            });
                            setTimeout(function() {
                                window.location = "<?= BASE_URL('client/list-mbbank') ?>"
                            }, 1000);
                        } else {
                            cuteToast({
                                type: "error",
                                message: data.msg,
                                timer: 5000
                            });
                        }
                    },
                });
            }
        })
    }
</script>
<?php require_once __DIR__ . '/footer.php'; ?>