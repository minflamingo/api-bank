<?php
// Hàm để thực hiện yêu cầu CURL và trả về phản hồi
function executeCurlRequest($url) {
    $data = curl_init();
    curl_setopt($data, CURLOPT_HEADER, false);
    curl_setopt($data, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($data, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($data, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($data, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($data, CURLOPT_TIMEOUT, 60);
    curl_setopt($data, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($data, CURLOPT_URL, $url);
    $res = curl_exec($data);
    curl_close($data);
    return $res;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST["amount"];
    $receivername = urlencode($_POST["receivername"]);
    $bankname = $_POST["bankname"];
    $comment = $_POST["comment"];
    $accountnumber = $_POST["accountnumber"];
    $bankcode = $_POST["bankcode"];
    $napasbankCode = $_POST["napasbankCode"];
//$transferResponse = executeCurlRequest('https://doithetudong.vn/api/acbcuong.php?createsendmoneyACB=ok&amount=14320000&receivername=TRUONG%20NGUYEN%20KIM%20HUNG&bankname=ACB&comment=ck&accountnumber=14914161&bankcode=307&napasbankCode=970416');
    // Gọi hàm để thực hiện yêu cầu CURL với các thông tin từ form
    $transferResponse = executeCurlRequest("https://doithetudong.vn/api/acbcuong.php?createsendmoney=ok&amount=$amount&receivername=$receivername&bankname=$bankname&comment=$comment&accountnumber=$accountnumber&bankcode=$bankcode&napasbankCode=$napasbankCode");

    $transferData = json_decode($transferResponse, true);

// Kiểm tra nếu có UUID và thực hiện yêu cầu OTP
if (!empty($transferData['data']['uuid'])) {
    $uuid = $transferData['data']['uuid'];
   echo nhapotp($uuid);
    //echo "Phản hồi chuyển tiền: {$transferResponse['response']}\nPhản hồi OTP: {$otpResponse['response']}";
} else {
    echo "Không thể nhận UUID từ phản hồi chuyển tiền " . $transferResponse;
}
}


function nhapotp($uuid) {
    $previousOtpData = ''; // Biến lưu trữ giá trị $otpData trước đó
    $error = 0;

    while (true) {
        // Thực hiện curl để lấy tin nhắn OTP
        $otpData = executeCurlRequest('https://doithetudong.vn/api/acb.txt');

        // Kiểm tra xem $otpData có thay đổi so với giá trị trước đó hay không
        if ($otpData != $previousOtpData) {
            // Mã OTP chưa tồn tại trong cơ sở dữ liệu, thực hiện các bước khác
            $sendmoney = executeCurlRequest('https://doithetudong.vn/api/acbcuong.php?inputotp=ok&uuid='.$uuid.'&otp='.$otpData);
            
            // Nếu chuyển tiền thành công, thoát khỏi vòng lặp và trả kết quả
            $otpArray = json_decode($sendmoney, true);
            if ($otpArray['messageStatus'] == 'success') {
                return $sendmoney;
            }

            if ($otpArray['codeStatus'] == '4060') {
                return 'Tài khoản không đủ tiền!';
            }
            $previousOtpData = $otpData;
        }

        // Nếu không thành công, tăng số lần lỗi và kiểm tra xem có vượt quá số lần cho phép không
        $error++;

        if ($error >= 55) {
            return json_encode(["status" => 500, "message" => "Có lỗi xảy ra khi chuyển tiền! https://doithetudong.vn/api/acbcuong.php?inputotp=ok&uuid=$uuid&otp=$otpData"]);
        }

        // Đợi một khoảng thời gian trước khi thực hiện lại
        sleep(1);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chuyển Tiền Form</title>
</head>
<body>
    <form action="" method="post">
        <label for="amount">Số tiền:</label>
        <input type="text" id="amount" name="amount" required><br>

        <label for="receivername">Tên người nhận:</label>
        <input type="text" id="receivername" name="receivername" required><br>

        <label for="bankname">Ngân hàng:</label>
        <input type="text" id="bankname" name="bankname" required><br>

        <label for="comment">Ghi chú:</label>
        <input type="text" id="comment" name="comment" required><br>
<label for="accountnumber">Số tài khoản:</label>
        <input type="text" id="accountnumber" name="accountnumber" required><br>

        <label for="bankcode">Mã ngân hàng:</label>
        <input type="text" id="bankcode" name="bankcode" required><br>

        <label for="napasbankCode">Mã NAPAS:</label>
        <input type="text" id="napasbankCode" name="napasbankCode" required><br>

        <input type="submit" value="Chuyển Tiền">
    </form>
</body>
</html>
