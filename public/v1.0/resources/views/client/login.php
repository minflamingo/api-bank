<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$title = "Đăng nhập";
$body['header'] = '

';
$body['footer'] = '

';
require_once __DIR__ . '/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-content text-center">
        <div class="card borderless">
            <div class="row align-items-center ">
                <div class="col-md-12">
                    <div class="card-body">
                        <h4 class="mb-3 f-w-400">HỆ THỐNG API TỰ ĐỘNG</h4>
                        <hr>
                        <div class="form-group mb-3">
                            <input type="text" class="form-control" id="username" placeholder="Tên đăng nhập">
                        </div>
                        <div class="form-group mb-4">
                            <input type="password" class="form-control" id="password" placeholder="Mật khẩu">
                        </div>
                        <div class="input-group mb-3">
                            <input type="text" id="captcha" class="form-control" placeholder="Nhập captcha..." maxlength="5">
                            <div class="input-group-append">
                                <img src="/captcha.php" height="40px" id="imgcaptcha" onclick="ReloadCaptcha()" data-toggle="tooltip" data-placement="top" title="" data-original-title="Click để đổi captcha khác!">
                            </div>
                        </div>

                        <button class="btn btn-block btn-primary mb-4" id="btnLogin">Đăng Nhập</button>
                        <hr>
                        <p class="mb-2 text-muted">Quên mật khẩu? <a href="<?= BASE_URL('client/forgot-password') ?>" class="f-w-400">Khôi Phục</a></p>
                        <p class="mb-0 text-muted">Chưa có tài khoản? <a href="<?= BASE_URL('client/register') ?>" class="f-w-400">Đăng Ký</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    function ReloadCaptcha() {
        document.getElementById('imgcaptcha').src = '/captcha.php';
    }
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    })
    $("#btnLogin").on("click", function() {
        $('#btnLogin').html('<i class="fa fa-spinner fa-spin"></i> Đang xử lý...').prop(
            'disabled',
            true);
        $.ajax({
            url: "<?= base_url('ajaxs/client/login.php'); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                username: $("#username").val(),
                password: $("#password").val(),
                captcha: $("#captcha").val()
            },
            success: function(respone) {
                if (respone.status == 'success') {
                    Toast.fire({
                        icon: 'success',
                        title: respone.msg
                    });
                    setTimeout("location.href = '<?= BASE_URL(''); ?>';", 100);
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: respone.msg
                    });
                }
                $('#btnLogin').html('<i class="fas fa-sign-in-alt"></i> Đăng Nhập').prop('disabled',
                    false);
            },
            error: function() {
                Toast.fire({
                    icon: 'error',
                    title: 'Không thể xử lý'
                });
                $('#btnLogin').html('<i class="fas fa-sign-in-alt"></i> Đăng Nhập').prop('disabled',
                    false);
            }

        });
    });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>