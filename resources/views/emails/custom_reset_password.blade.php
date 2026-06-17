<!-- resources/views/emails/custom_reset_password.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu</title>
</head>
<body style="margin:0px;padding:0px;background-color:#e6e9eb;width:100%;height:100%">
    <table style="border-collapse:collapse;width:100%">
        <tbody>
            <tr>
                <td style="width:200px;padding:25px 0 32px;background-color:#121f57"></td>
                <td style="width:440px;padding:25px 0 32px;background-color:#121f57;
                           vertical-align:middle;font-size:24px;font-weight:bold;color:#fff;
                           text-align:left;font-family:Helvetica,Arial,sans-serif">
                    <!-- LOGO của bạn -->
                    <img src="{{ $logo }}" alt="Logo" style="max-width:200px;">
                </td>
                <td style="width:140px;padding:25px 0 32px;background-color:#121f57;
                           vertical-align:middle;font-size:20px;font-weight:bold;color:#fff;
                           text-align:right;font-family:Helvetica,Arial,sans-serif">
                    <!-- Nút "Đăng nhập" (tùy chọn) -->
                    <a href="{{ $loginUrl }}" style="color:#fff;text-decoration:none" target="_blank">
                        Đăng nhập
                    </a>
                </td>
                <td style="width:200px;padding:25px 0 32px;background-color:#121f57"></td>
            </tr>
            
            <tr>
                <td style="width:200px;padding:0 0 10px;background-color:#e6e9eb;vertical-align:top">
                    <div style="background-color:#121f57;height:235px"></div>
                </td>
                <td style="width:500px;padding:0;background-color:#fff;vertical-align:middle;
                           font-size:18px;font-weight:400;color:#616366;font-family:Helvetica,Arial,sans-serif"
                    colspan="2">
                    
                    <!-- Nội dung chính -->
                    <div style="padding:20px 40px 40px 40px">
                        Xin chào,
                        <br><br>
                        Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đăng ký mới cho tài khoản của bạn.
                        <br><br>
                        Vui lòng nhấp vào đường dẫn dưới đây để đặt lại:
                        <br><br>
                        <!-- Link đặt lại mật khẩu -->
                        <a href="{{ $resetUrl }}"
                           style="font-weight:bold; color:#3594e8; text-decoration:none;"
                           target="_blank">
                            Tạo mật khẩu mới
                        </a>
                        <br><br>
                        Nếu không phải bạn yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.
                    </div>
                </td>
                <td style="width:200px;padding:0 0 10px;background-color:#e6e9eb;vertical-align:top">
                    <div style="background-color:#121f57;height:235px"></div>
                </td>
            </tr>
            
            <tr>
                <td style="width:200px;padding:0 0 10px;background-color:#e6e9eb"></td>
                <td style="width:500px;padding:0 0 10px;background-color:#fff;border-bottom:1px solid #e6e9eb"
                    colspan="2">
                </td>
                <td style="width:200px;padding:0 0 10px;background-color:#e6e9eb"></td>
            </tr>
            
            <tr>
                <td style="width:200px;padding:40px 0 50px;background-color:#e6e9eb"></td>
                <td style="width:500px;padding:40px;background-color:#fff;vertical-align:middle;
                           font-size:16px;font-weight:700;color:#2d363d;text-align:center;
                           font-family:Helvetica,Arial,sans-serif"
                    colspan="2">
                    Cảm ơn bạn đã tin tưởng 
                    <a href="https://apibank.com.vn"
                       style="color:#3594e8;font-family:Helvetica,Arial,sans-serif;
                              font-size:16px;font-weight:700;text-decoration:none"
                       target="_blank">
                        API Bank
                    </a>!
                </td>
                <td style="width:200px;padding:40px 0 50px;background-color:#e6e9eb"></td>
            </tr>
            
            <tr>
                <td style="width:200px;padding:28px 0 40px;background-color:#e6e9eb"></td>
                <td style="width:428px;padding:28px 0 40px 12px;background-color:#e6e9eb;vertical-align:middle;
                           font-size:14px;font-weight:normal;color:#949da8;text-align:left;
                           font-family:Helvetica,Arial,sans-serif">
                    Copyright © {{ date('Y') }}
                    <strong>API BANK</strong>. All rights reserved.
                </td>
                <td style="width:140px;padding:28px 0 40px;background-color:#e6e9eb;vertical-align:middle;
                           font-size:14px;font-weight:normal;color:#949da8;text-align:right;
                           font-family:Helvetica,Arial,sans-serif">
                </td>
                <td style="width:200px;padding:28px 0 40px;background-color:#e6e9eb"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
