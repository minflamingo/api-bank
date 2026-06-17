<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$title = 'Thêm tài khoản momo | ' . $NNL->site('title');
$body['header'] = '

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
                            <h5 class="m-b-10">Bảng điều khiển</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="<?=BASE_URL('')?>">Bảng điều khiển</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ stiped-table ] start -->
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Thông tin tài khoản Vietcombank</h5>
                    </div>
                    <div class="card-body">
  <label class="floating-label">Khi bạn thêm vietcombank mà thấy thông báo "Data Invalid" thì vui lòng vào app mở khóa đăng nhập web theo hướng dẫn: Cài đặt >> Quản lý đăng nhập kênh >> Cài đặt đăng nhập VCB Digibank trên Web (Chỉ bật duy nhất cái này, tắt cái dưới)</label>
                       <div class="row">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label class="floating-label">Tài khoản Vietcombank</label>
                                    <input type="text" class="form-control" id="account"
                                        placeholder="Nhập tài khoản vietcombank">
                                    <input type="hidden" class="form-control" id="token" value="<?=$getUser['token']?>">
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label class="floating-label" for="Text">Mật khẩu Vietcombank</label>
                                    <input type="text" class="form-control" id="password"
                                        placeholder="Mật khẩu vietcombank">
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label class="floating-label" for="Text">Số tài khoản Vietcombank</label>
                                    <input type="text" class="form-control" id="stk"
                                        placeholder="Nhập số tài khoản vietcombank">
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <label class="floating-label" for="Text">OTP XÁC THỰC</label>
                                <div class="input-group mb-3">
                                    <input type="number" max="6" id="otp" class="form-control"
                                        placeholder="Nhập otp khi nhận ở sms" aria-label="Recipient's username"
                                        aria-describedby="basic-addon2">
                                    <div class="input-group-append">
                                        <button class="btn btn-dark" type="button" id="btnGetOtp">GET OTP</button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-12">
                                <button type="button" id="btnLogin" class="btn btn-success w-100">Đăng Nhập</button>
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
$("#btnGetOtp").on("click", function() {
    $('#btnGetOtp').html('<i class="fa fa-spinner fa-spin"></i> Đang xử lý...').prop('disabled',
        true);
    var myOTPData = {
        action: 'GETOTP',
        account: $("#account").val(),
        password: $("#password").val(),
        stk: $("#stk").val(),
        token: $("#token").val()
    };
    $.post("<?=BASE_URL("ajaxs/client/vcb.php");?>", myOTPData,
        function(data) {
            if (data.status == '3') {
                cuteToast({
                    type: "success",
                    message: data.msg,
                    timer: 5000
                });
                setTimeout(function() {
                    window.location = "<?=BASE_URL('client/listvcb')?>"
                }, 1000);
            }else if(data.status == '2'){
                cuteToast({
                    type: "success",
                    message: data.msg,
                    timer: 5000
                });
            } else {
                cuteToast({
                    type: "error",
                    message: data.msg,
                    timer: 5000
                });
            }
            $('#btnGetOtp').html('GET OTP').prop('disabled', false);
        }, "json");
});
$("#btnLogin").on("click", function() {
    $('#btnLogin').html('<i class="fa fa-spinner fa-spin"></i> Đang xử lý...').prop('disabled',
        true);
    var myOTPData = {
        action: 'LOGIN',
        account: $("#account").val(),
        password: $("#password").val(),
        stk: $("#stk").val(),
        otp: $("#otp").val(),
        token: $("#token").val()
    };
    $.post("<?=BASE_URL("ajaxs/client/vcb.php");?>", myOTPData,
        function(data) {
            if (data.status == '2') {
                cuteToast({
                    type: "success",
                    message: data.msg,
                    timer: 5000
                });
                setTimeout(function() {
                    window.location = "<?=BASE_URL('client/listvcb')?>"
                }, 1000);
            } else {
                cuteToast({
                    type: "error",
                    message: data.msg,
                    timer: 5000
                });
            }
            $('#btnLogin').html('Đăng Nhập').prop('disabled', false);
        }, "json");
});
</script>

<?php require_once __DIR__ . '/footer.php';?>