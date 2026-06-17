<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
include 'decode.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
class VCB
{
    public $browserToken = '';
    public $accountNo = '';
    public $tranId = '';
    public $otp = '';

    public function getCaptcha($key_captcha)
    {
        $curl = curl_init();
        $dataPost = array(
            "api_key" => $key_captcha,
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://ecaptcha.sieuthicode.net/api/captcha/get-vcb',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $dataPost,
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function get_captcha($captcha_id)
    {
        $headers = array(
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'Accept: image/webp,image/png,image/svg+xml,image/*;q=0.8,video/*;q=0.8,*/*;q=0.5',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-Mode: no-cors',
            'Sec-Fetch-Dest: image',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Accept-Language: en-US,en;q=0.9',
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://digiapp.vietcombank.com.vn/utility-service/v1/captcha/$captcha_id");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);

        curl_close($ch);
        return $data;
    }
    public function random($string, $int)
    {
        return substr(str_shuffle($string), 0, $int);
    }
    public function save_captcha($value)
    {
        $rand = $this->random("QWERTYUIOPASDFGHJKLZXCVBNM0123456789", 6);
        $saveFile = fopen('captcha/' . $rand . '.jpeg', 'w+');
        fwrite($saveFile, $value);
        fclose($saveFile);
    }
    public function initLoginNewBrowser()
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/api-3008";
        $headers = array(
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: vi',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Origin: https://vcbdigibank.vietcombank.com.vn',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'sec-ch-ua: "Chromium";v="104", " Not A;Brand";v="99", "Google Chrome";v="104"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer null',
        );
        $data = array(
            "user" => $this->accountNo,
            "browserToken" => $this->browserToken,
            "mid" => 3008,

        );
        $encrypt = encryptRequest($this->gen_uuid(), $data);
        return $this->CURL($url, $headers, json_encode($encrypt));
    }
    public function veryOtpLoginNewBrowser($otp, $user, $tranId, $browserToken)
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/api-3011";
        $headers = array(
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: vi',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Origin: https://vcbdigibank.vietcombank.com.vn',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'sec-ch-ua: "Chromium";v="104", " Not A;Brand";v="99", "Google Chrome";v="104"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer null',
        );
        $data = array(
            "DT" => "Windows",
            "OV" => "10",
            "PM" => "Chrome 104.0.0.0",
            "user" => $user,
            "tranId" => $tranId,
            "browserToken" => $browserToken,
            "otp" => $otp,
            "mid" => 3011,

        );
        $encrypt = encryptRequest($this->gen_uuid(), $data);
        return $this->CURL($url, $headers, json_encode($encrypt));
    }
    public function get_otp()
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/api-3010";
        $headers = array(
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: vi',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Origin: https://vcbdigibank.vietcombank.com.vn',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'sec-ch-ua: "Chromium";v="104", " Not A;Brand";v="99", "Google Chrome";v="104"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer null',
        );
        $data = array(
            "DT" => "Windows",
            "OV" => "10",
            "PM" => "Chrome 104.0.0.0",
            "user" => $this->accountNo,
            "tranId" => $this->tranId,
            "type" => "1",
            "browserToken" => $this->browserToken,
            "mid" => 3010,

        );
        $encrypt = encryptRequest($this->gen_uuid(), $data);
        return $this->CURL($url, $headers, json_encode($encrypt));
    }
    public function saveLoginNewBrowser($user, $cif, $clientId, $mobileId, $sessionId)
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/api-3009";
        $headers = array(
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: vi',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Origin: https://vcbdigibank.vietcombank.com.vn',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'sec-ch-ua: "Chromium";v="104", " Not A;Brand";v="99", "Google Chrome";v="104"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer null',
        );
        $data = array(
            "DT" => "Windows",
            "OV" => "10",
            "PM" => "Chrome 104.0.0.0",
            "user" => $user,
            "browserId" => md5($user),
            "browserName" => "Chrome " . $user,
            "mid" => 3009,
            "cif" => $cif,
            "clientId" => $clientId,
            "mobileId" => $mobileId,
            "sessionId" => $sessionId,

        );
        $encrypt = encryptRequest($this->gen_uuid(), $data);
        return $this->CURL($url, $headers, json_encode($encrypt));
    }
    public function login($username, $password, $captcha_token, $captcha_value)
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/login";
        $headers = array(
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: vi',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Origin: https://vcbdigibank.vietcombank.com.vn',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'sec-ch-ua: "Chromium";v="104", " Not A;Brand";v="99", "Google Chrome";v="104"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer null',
        );
        $data = array(
            "DT" => "Windows",
            "OV" => "10",
            "PM" => "Chrome 104.0.0.0",
            "captchaToken" => "$captcha_token",
            "captchaValue" => "$captcha_value",
            "browserId" => md5($username),
            "checkAcctPkg" => "1",
            "lang" => "vi",
            "mid" => 6,
            "password" => "$password",
            "user" => "$username",
        );
        $encrypt = encryptRequest($this->gen_uuid(), $data);
        return $this->CURL($url, $headers, json_encode($encrypt));
    }
    public function get_lsgd($username, $account, $session_id, $cif, $client_id, $mobile_id)
    {
        $url = "https://digiapp.vietcombank.com.vn/bank-service/v1/transaction-history";
        $headers = array(
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: vi',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Origin: https://vcbdigibank.vietcombank.com.vn',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'sec-ch-ua: "Chromium";v="104", " Not A;Brand";v="99", "Google Chrome";v="104"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer null',
        );
        $data = array(
            "DT" => "Windows",
            "PM" => "Chrome 104.0.0.0",
            "OV" => "10",
            "lang" => "vi",
            "accountNo" => "$account",
            "accountType" => "D",
            "fromDate" => date("d/m/Y", time() - 3600 * 24 * 7),
            "toDate" => date("d/m/Y", time()),
            "pageIndex" => 0,
            "lengthInPage" => 999999,
            "stmtDate" => "",
            "stmtType" => "",
            "mid" => 14,
            "cif" => "$cif",
            "user" => "$username",
            "mobileId" => "$mobile_id",
            "clientId" => "$client_id",
            "sessionId" => "$session_id",
        );
        $encrypt = encryptRequest($this->gen_uuid(), $data);
        return $this->CURL($url, $headers, json_encode($encrypt));
    }
    public function get_balance($username, $account, $session_id, $cif, $client_id, $mobile_id)
    {
        $url = "https://digiapp.vietcombank.com.vn/bank-service/v1/get-account-detail";
        $headers = array(
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: vi',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Origin: https://vcbdigibank.vietcombank.com.vn',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'sec-ch-ua: "Chromium";v="104", " Not A;Brand";v="99", "Google Chrome";v="104"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer null',
        );
        $data = array(
            "DT" => "Windows",
            "PM" => "Chrome 104.0.0.0",
            "OV" => "10",
            "lang" => "vi",
            "accountNo" => "$account",
            "accountType" => "D",
            "mid" => 13,
            "cif" => "$cif",
            "user" => "$username",
            "mobileId" => "$mobile_id",
            "clientId" => "$client_id",
            "sessionId" => "$session_id",
        );

        $encrypt = encryptRequest($this->gen_uuid(), $data);
        return $this->CURL($url, $headers, json_encode($encrypt));
    }
    public function CURL($Action, $header, $data)
    {
        $curl = curl_init();
        $opt = array(
            CURLOPT_URL => $Action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => empty($data) ? false : true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_CUSTOMREQUEST => empty($data) ? 'GET' : 'POST',
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_ENCODING => "",
            CURLOPT_HEADER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2,
            CURLOPT_TIMEOUT => 5,
        );
        curl_setopt_array($curl, $opt);
        $body = curl_exec($curl);
        $decode = json_decode($body, true);
        return decryptResponse($decode['k'], $decode['d']);
    }
    public function get_time_request()
    {
        $d = getdate();
        $today = $d['hours'] . $d['minutes'] . $d['seconds'];
        $day = date('Y') . date('m') . date('d');
        return $day . $today;
    }
    public function Decrypt_data($data, $keys)
    {

        $iv = pack('C*', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $keys, OPENSSL_RAW_DATA, $iv);
    }
    public function generateImei()
    {
        return $this->generateRandom(8) . '-' . $this->generateRandom(4) . '-' . $this->generateRandom(4) . '-' . $this->generateRandom(4) . '-' . $this->generateRandom(12);
    }
    public function generateRandom($length = 20)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    public function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    public function get_microtime()
    {
        return round(microtime(true) * 1000);
    }
}
