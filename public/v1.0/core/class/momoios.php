<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

class VMomoIOS
{

    public $config = array();
    private $vsignAPIKey = '';


    public function __construct($vsignAPIKey)
    {
        if (empty($vsignAPIKey)) {
            throw new ErrorException('Bạn cần phải cung cấp API Key của Vsign');
        }
        $this->vsignAPIKey = $vsignAPIKey;
    }

    public function load_data($config)
    {
        $this->config = $config;
    }

    private function get_TOKEN()
    {
        return $this->generate_random(22) . ':' . $this->generate_random(9) . '-' . $this->generate_random(20) . '-' . $this->generate_random(12) . '-' . $this->generate_random(7) . '-' . $this->generate_random(7) . '-' . $this->generate_random(53) . '-' . $this->generate_random(9) . '_' . $this->generate_random(11) . '-' . $this->generate_random(4);
    }

    private function get_vsign($data)
    {
        try {
            $headers = array(
                'key:' . $this->vsignAPIKey,
                'os: ios',
                'version: 4.1.8',
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36 Edg/117.0.2045.31'
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://vsign.pro/api/v2/getVSign");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_ENCODING, "true");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            curl_close($ch);
            if (is_object(json_decode($result))) {
                $response = json_decode($result, true);
                $vsign = $response['data'];
                return $vsign;
            } else {
                return '';
            }
        } catch (Throwable $th) {
            return '';
        }
    }

    private function encode_RSA($content, $key)
    {
        require_once('./lib/Crypt/RSA.php');
        $rsa = new Crypt_RSA();
        $rsa->loadKey($key);
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        $result = base64_encode($rsa->encrypt($content));
        return $result;
    }

    private function get_microtime()
    {
        return floor(microtime(true) * 1000);
    }

    public function generate_random($length = 20)
    {
        $characters = '0123456789abcdefABCDEF';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private function get_checksum($phone, $type, $setupKey)
    {
        $checkSumSyntax = $phone . $this->get_microtime() . '000000' . $type . ($this->get_microtime() / 1000000000000.0) . 'E12';
        return $this->encrypt_decrypt($checkSumSyntax, $setupKey, 'ENCRYPT');
    }


    public function generate_UUID_v4()
    {
        // Generate 16 random bytes (128 bits)
        $randomBytes = random_bytes(16);

        // Set the version (4) and variant (random) bits
        $randomBytes[6] = chr(ord($randomBytes[6]) & 0x0F | 0x40); // Set version to 4 (0100)
        $randomBytes[8] = chr(ord($randomBytes[8]) & 0x3F | 0x80); // Set variant to RFC 4122 (1000)

        // Format the UUID
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($randomBytes), 4));

        return $uuid;
    }

    private function curl_momo($url, $headers, $data)
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }

        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = "$key: $value";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        $result = curl_exec($ch);
        curl_close($ch);
        if (is_object(json_decode($result))) {
            return json_decode($result, true);
        } else {
            return $result;
        }
    }

    public function load_db($phone, $password)
    {
        $default = array(
            'phone' => $phone,
            "rkey" => $this->generate_random(20),
            "MODELID" => strtolower($this->generate_random(64)),
            "device_token" => strtoupper($this->generate_random(64)),
            'imei' => strtoupper($this->generate_UUID_v4()),
            'device' => 'iPhone 12',
            'hardware' => 'iPhone',
            'firmware' => '17.1',
            'facture' => 'Apple',
            'TOKEN' => $this->get_TOKEN(),
            'appVer' => 41081,
            'appCode' => '4.1.8',
            'csp' => 'Vietnamobile',
            'sessionKeyTracking' => strtoupper($this->generate_UUID_v4())
        );

        try {
            $filePath = $phone . '.json';
            $file_handle = @fopen($filePath, "r");
            if (!$file_handle) {
                throw new Exception('Failed to open uploaded file');
            }
            $json = file_get_contents($filePath);
            $this->config = json_decode($json, TRUE);
        } catch (Throwable $th) {
            $this->config = $default;
        }
        $this->config['password'] = $password;
        return $this->config;
    }

    public function store_db()
    {
        $json = json_encode($this->config);
        $filePath = $this->config["phone"] . '.json';
        file_put_contents($filePath, $json);
    }

    private function encrypt_decrypt($data, $key, $mode = 'ENCRYPT')
    {
        if (strlen($key) < 32) {
            $key = str_pad($key, 32, 'x');
        }
        $key = substr($key, 0, 32);
        $iv = pack('C*', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        if ($mode === 'ENCRYPT') {
            return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));
        } else {
            return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        }
    }


    private function check_user_be_msg()
    {
        $microtime = $this->get_microtime();
        $tbid = strtoupper($this->generate_random(40));

        $data = array(
            "user" => $this->config['phone'],
            "msgType" => "CHECK_USER_BE_MSG",
            "momoMsg" => [
                "_class" => "mservice.backend.entity.msg.RegDeviceMsg",
                "number" => $this->config['phone'],
                "imei" => $this->config["imei"],
                "cname" => "Vietnam",
                "ccode" => "084",
                "device" => $this->config["device"],
                "firmware" => $this->config["firmware"],
                "hardware" => "iPhone",
                "manufacture" => $this->config["facture"],
                "csp" => 'Vietnamobile',
                "icc" => "",
                "mcc" => "452",
                "mnc" => "04",
                "device_os" => "ios",
                "secure_id" => "",
            ],
            "appVer" => $this->config["appVer"],
            "appCode" => $this->config["appCode"],
            "lang" => "vi",
            "deviceOS" => "ios",
            "channel" => "APP",
            "buildNumber" => 0,
            "appId" => "vn.momo.platform",
            "cmdId" => $microtime . "000000",
            "time" => $microtime,
        );

        $vsign = $this->get_vsign($data);

        if (empty($vsign)) {
            throw new Exception('Không Lấy Được Vsign');
            // TODO: handle không lấy dc vsign ở đây
        }

        $headers = array(
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            'agent_id' => 0,
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'msgtype' => 'CHECK_USER_BE_MSG',
            'platform-timestamp' => time(),
            'sessionkey' => '',
            'tbid' => $tbid,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
            'user_phone' => '',
            'userid' => '',
            'vsign' => $vsign,
            'vversion' => '1004',
            'ftv' => '1&1&1'
        );

        $CHECK_USER_BE_MSG_LINK = 'https://api.momo.vn/backend/auth-app/public/CHECK_USER_BE_MSG';
        $response = $this->curl_momo($CHECK_USER_BE_MSG_LINK, $headers, $data);
        print_r($response);
       // if ($response["errorCode"] < 0) {
        //    throw new Exception($response["errorDesc"]);
       // }
        $this->config['tbid'] = $tbid;
        return $response;
    }

    private function send_otp_msg()
    {
        $microtime = $this->get_microtime();
        $data = array(
            "user" => $this->config['phone'],
            "msgType" => "SEND_OTP_MSG",
            "momoMsg" => [
                "_class" => "mservice.backend.entity.msg.RegDeviceMsg",
                "number" => $this->config['phone'],
                "imei" => $this->config["imei"],
                "cname" => "Vietnam",
                "ccode" => "084",
                "device" => $this->config["device"],
                "firmware" => $this->config["firmware"],
                "hardware" => "iPhone",
                "manufacture" => $this->config["facture"],
                "csp" => 'Vietnamobile',
                "icc" => "",
                "mnc" => "04",
                "mcc" => "452",
                "device_os" => "ios",
                "secure_id" => "",
            ],
            "extra" => [
                "action" => "SEND",
                "rkey" => $this->config["rkey"],
                "IDFA" => "",
                "SIMULATOR" => false,
                "TOKEN" => $this->config["TOKEN"],
                "ONESIGNAL_TOKEN" => $this->config["TOKEN"],
                "SECUREID" => "",
                "MODELID" => $this->config["MODELID"],
                "DEVICE_TOKEN" => $this->config['device_token'],
                "isVoice" => false,
                "REQUIRE_HASH_STRING_OTP" => true,
            ],
            "appVer" => $this->config["appVer"],
            "appCode" => $this->config["appCode"],
            "lang" => "vi",
            "deviceOS" => "ios",
            "channel" => "APP",
            "buildNumber" => 0,
            "appId" => "vn.momo.platform",
            "cmdId" => $microtime . "000000",
            "time" => $microtime,
        );

        $vsign = $this->get_vsign($data);

        if (empty($vsign)) {
            throw new Exception('Không Lấy Được Vsign');
            // TODO: handle không lấy dc vsign ở đây
        }

        $headers = array(
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            'agent_id' => 0,
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'msgtype' => 'SEND_OTP_MSG',
            'platform-timestamp' => time(),
            'sessionkey' => '',
            'tbid' => $this->config['tbid'],
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
            'user_phone' => '',
            'userid' => '',
            'vsign' => $vsign,
            'vversion' => '1004',
            'ftv' => '1&1&1'
        );
        $SEND_OTP_MSG_LINK = 'https://api.momo.vn/backend/otp-app/public/SEND_OTP_MSG';
        $response = $this->curl_momo($SEND_OTP_MSG_LINK, $headers, $data);
        if ($response["errorCode"] < 0) {
            throw new Exception($response["errorDesc"]);
        }
        return $response;
    }

    private function reg_device_msg($otp)
    {

        $this->config['ohash'] = hash('sha256', $this->config["phone"] . $this->config["rkey"] . $otp);
        $ohash = $this->config['ohash'];
        $time = $this->get_microtime();
        $data = array(
            "user" => $this->config['phone'],
            "msgType" => "REG_DEVICE_MSG",
            "momoMsg" => [
                "_class" => "mservice.backend.entity.msg.RegDeviceMsg",
                "number" => $this->config['phone'],
                "imei" => $this->config['imei'],
                "cname" => "Vietnam",
                "ccode" => "084",
                "device" => $this->config['device'],
                "firmware" => $this->config['firmware'],
                "hardware" => "iPhone",
                "manufacture" => $this->config['facture'],
                "csp" => "Vietnamobile",
                "icc" => "",
                "mcc" => "452",
                "mnc" => "04",
                "device_os" => "ios",
                "secure_id" => "",
            ],
            "extra" => [
                "ohash" => $ohash,
                "IDFA" => "",
                "SIMULATOR" => false,
                "TOKEN" => $this->config['TOKEN'],
                "ONESIGNAL_TOKEN" => $this->config['TOKEN'],
                "SECUREID" => "",
                "MODELID" => $this->config['MODELID'],
                "DEVICE_TOKEN" => $this->config['device_token'],
                "isAllowNoti" => "true",
            ],
            "appVer" => $this->config['appVer'],
            "appCode" => $this->config['appCode'],
            "lang" => "vi",
            "deviceOS" => "ios",
            "channel" => "APP",
            "buildNumber" => 0,
            "appId" => "vn.momo.platform",
            "cmdId" => $time . "000000",
            "time" => $time,
        );

        $vsign = $this->get_vsign($data);
        print_r($vsign);

        if (empty($vsign)) {
            throw new Exception('Không Lấy Được Vsign');
            //TODO: handle không lấy dc vsign ở đây
        }

        $headers = array(
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            'agent_id' => 0,
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'msgtype' => 'REG_DEVICE_MSG',
            'platform-timestamp' => time(),
            'sessionkey' => '',
            'tbid' => $this->config['tbid'],
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
            'user_phone' => '',
            'userid' => '',
            'vsign' => $vsign,
            'vversion' => '1004',
            'ftv' => '1&1&1'
        );

        $REG_DEVICE_MSG_LINK = 'https://api.momo.vn/backend/otp-app/public/REG_DEVICE_MSG';
        $response = $this->curl_momo($REG_DEVICE_MSG_LINK, $headers, $data);
        if ($response["errorCode"] < 0) {
            throw new Exception($response["errorDesc"]);
        }

        $setup_key = $this->encrypt_decrypt($response["extra"]["setupKey"], $response["extra"]["ohash"], 'DECRYPT');
        $phash = $this->encrypt_decrypt("" . $this->config['imei'] . "|" . $this->config['password'] . "", $setup_key, 'ENCRYPT');
        $name = $response["extra"]["NAME"];
        $this->config['phash'] = $phash;
        $this->config['name'] = $name;
        $this->config['setup_key'] = $setup_key;
        return $response;
    }


    private function send_bank($data)
    {
        $setupKey = $data['setupKey'];
        $phone = $data['phone'];
        $bankNumber = $data['bankNumber'];
        $bankName = $data['bankName'];
        $benfPhoneNumberInput = $data['benfPhoneNumberInput'];
        $targetBankReceiverName = $data['targetBankReceiverName'];
        $memo = $data['memo'];
        $fullBankName = $data['fullBankName'];
        $bankCode = $data['bankCode'];
        $amount = $data['amount'];
        $sessionKey = $data['sessionKey'];
        $accessToken = $data['accessToken'];
        $requestEncryptKey = $data['requestEncryptKey'];
        $initResponse = null;
        $time = $this->get_microtime();
        // Calculate the checksum
        $checkSumCalculated = $this->get_checksum($phone, 'TRAN_HIS_INIT_MSG', $setupKey);
        $clientTime = $this->get_microtime() - 400;

        // Construct the body array for TRAN_HIS_INIT_MSG
        $extras = json_encode([
            "saveCard" => false,
            "bankNumber" => $bankNumber,
            "bankName" => $bankName,
            "benfPhoneNumberInput" => $benfPhoneNumberInput,
            "checkFeeCacheRefNumber" => "",
            "receiverName" => $targetBankReceiverName,
            "nickName" => "",
            "themeP2P" => "default",
            "informCardSOF" => [
                "refId" => "funds_manager",
                "ctaTitle" => "Thử ngay",
                "isShow" => true,
                "title" => "Thiết lập tài khoản ưu tiên",
                "description" => "Bạn sẽ không cần mất thời gian kiểm tra, lựa chọn tài khoản mỗi khi chuyển tiền/thanh toán.",
            ],
            "renderType" => "REFERRAL_W2W",
            "source" => "bank_input_search",
            "paymentChannel" => "bank_input_search",
            "categoryId" => null,
            "categoryGroupName" => "",
            "receiverNumber" => "",
            "receiverId" => "",
            "beneficialId" => "",
            "bankCustomerId" => "",
        ]);

        $initBody = [
            "appCode" => $this->config['appCode'],
            "appId" => "vn.momo.payment",
            "appVer" => $this->config['appVer'],
            "buildNumber" => 6734,
            "channel" => "APP",
            "cmdId" => $time . "000000",
            "time" => $time,
            "deviceOS" => "ios",
            "errorCode" => 0,
            "errorDesc" => "",
            "extra" => ["checkSum" => $checkSumCalculated],
            "lang" => "vi",
            "user" => $phone,
            "msgType" => "TRAN_HIS_INIT_MSG",
            "momoMsg" => [
                "tranType" => 8,
                "clientTime" => $clientTime,
                "extras" => '' . $extras . '',
                "comment" => $memo,
                "partnerRef" => "",
                "serviceId" => "transfer_p2b_globalsearch",
                "partnerName" => $fullBankName,
                "rowCardNum" => $bankNumber,
                "amount" => (string)$amount,
                "ownerName" => $targetBankReceiverName,
                "_class" => "mservice.backend.entity.msg.TranHisMsg",
                "partnerId" => (string)$bankCode,
                "serviceCode" => "transfer_p2b_globalsearch",
                "moneySource" => 1,
                "sourceToken" => "SOF-1",
                "partnerCode" => "momo",
                "rowCardId" => "",
                "giftId" => "",
                "useVoucher" => 0,
                "discountCode" => null,
                "prepaidIds" => "",
                "usePrepaid" => 0,
            ],
            "pass" => "",
        ];

        $rKey = $this->generate_random(32);
        $requestKey = $this->encode_RSA($rKey, $requestEncryptKey);

        $encryptedInitBody = $this->encrypt_decrypt(json_encode($initBody), $rKey, 'ENCRYPT');

        $headers = array(
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            "agent_id" => (int)$data['agentId'],
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'msgtype' => 'TRAN_HIS_INIT_MSG',
            'platform-timestamp' => time(),
            "http-process-timestamp" => time(),
            "sessionkey" => $sessionKey,
            'tbid' => $this->config['tbid'],
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
            "userid" => $phone,
            "user_phone" => $phone,
            "requestkey" => $requestKey,
            'vversion' => '1004',
            'ftv' => '1&1&1',
            "Authorization" => "Bearer " . $accessToken,
        );

        $init_response = $this->curl_momo('https://owa.momo.vn/api/TRAN_HIS_INIT_MSG', $headers, $encryptedInitBody);

        $init_response = json_decode($this->encrypt_decrypt($init_response, $rKey, 'DECRYPT'), true);

        echo 'DONE INIT TRAN_HIS_INIT_MSG' . PHP_EOL;
        sleep(2);

        if ($init_response['result']) {

            // Calculate the checksum for TRAN_HIS_CONFIRM_MSG
            $time = $this->get_microtime();
            print_r(json_encode($init_response));
            $checkSumCalculated = $this->get_checksum($phone, 'TRAN_HIS_CONFIRM_MSG', $setupKey);

            // Construct the body array for TRAN_HIS_CONFIRM_MSG
            $confirmBody = [
                "appCode" => $this->config['appCode'],
                "appId" => "vn.momo.payment",
                "appVer" => $this->config['appVer'],
                "buildNumber" => 6734,
                "channel" => "APP",
                "cmdId" => $time . "000000",
                "time" => $time,
                "deviceOS" => "ios",
                "errorCode" => 0,
                "errorDesc" => "",
                "extra" => ["checkSum" => $checkSumCalculated],
                "lang" => "vi",
                "user" => $phone,
                "msgType" => "TRAN_HIS_CONFIRM_MSG",
                "momoMsg" => $init_response['momoMsg']['tranHisMsg'],
                "pass" => $this->config["password"],
            ];


            $rKey = $this->generate_random(32);
            $requestKey = $this->encode_RSA($rKey, $requestEncryptKey);

            $headers = array(
                'app_code' => $this->config['appCode'],
                'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
                'app_version' => $this->config['appVer'],
                'lang' => 'vi',
                'channel' => 'APP',
                'vversion' => '1004',
                'ftv' => '1&1&1',
                'env' => 'production',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'accept-language' => 'vi-VN,vi;q=0.9',
                'device_os' => 'IOS',
                'accept-charset' => 'UTF-8',
                'accept' => 'application/json',
                'content-type' => 'application/json',
                "sessionkey" => $sessionKey,
                "userid" => $phone,
                "user_phone" => $phone,
                "tbid" => $data['tbid'],
                "agent_id" => (int)$data['agentId'],
                "http-process-timestamp" => $time,
                "momo-session-key-tracking" => $data['sessionKeyTracking'],
                "Authorization" => "Bearer " . $accessToken,
                "requestkey" => $requestKey,
                "msgtype" => "TRAN_HIS_CONFIRM_MSG",
            );

            // print_r($headers);
            // die();

            $encryptedConfirmBody = $this->encrypt_decrypt(json_encode($confirmBody), $rKey, 'ENCRYPT');

            $confirm_response = $this->curl_momo('https://owa.momo.vn/api/TRAN_HIS_CONFIRM_MSG', $headers, $encryptedConfirmBody);
            if (is_string($confirm_response)) {
                $confirm_response = json_decode($this->encrypt_decrypt($confirm_response, $rKey, 'DECRYPT'), true);
            } else {
                throw new Exception($confirm_response["errorDesc"]);
            }
            echo 'DONE CONFIRM TRAN_HIS_CONFIRM_MSG' . PHP_EOL;

            echo 'Chuyển tiền thành công!' . PHP_EOL;
            return $confirm_response;
        } else {
            echo 'Khỏi tạo ck bank bị lỗi ' . $init_response['errorDesc'] . PHP_EOL;
        }
    }


    // ===== main function =====

    public function send_otp()
    {
        $this->check_user_be_msg();
        $response = $this->send_otp_msg();
        return $response;
    }

    public function confirm_otp($otp)
    {
        $response = $this->reg_device_msg($otp);
        return $response;
    }

    public function login()
    {
        $linkLogin = 'https://owa.momo.vn/public/login';
        $time = time();


        // Calculate checkSum
        $phone = $this->config['phone'];
        $pass = $this->config['password'];
        $setup_key = $this->config['setup_key'];
        $phash = $this->config['phash'];
        $modelId = $this->config['MODELID'];
        $deviceToken = $this->config['device_token'];
        $APP_VER = $this->config['appVer'];
        $APP_CODE = $this->config['appCode'];

        $checkSumCalculated = $this->get_checksum($phone, 'USER_LOGIN_MSG', $setup_key);

        // Create bodyLogin array
        $data = array(
            "user" => $phone,
            "pass" => $pass,
            "msgType" => "USER_LOGIN_MSG",
            "momoMsg" => [
                "_class" => "mservice.backend.entity.msg.LoginMsg",
                "isSetup" => false,
            ],
            "extra" => [
                "pHash" => $phash,
                "IDFA" => "",
                "SIMULATOR" => false,
                "TOKEN" => $this->config['TOKEN'],
                "ONESIGNAL_TOKEN" => $this->config['TOKEN'],
                "SECUREID" => "",
                "MODELID" => $modelId,
                "DEVICE_TOKEN" => $deviceToken,
                "checkSum" => $checkSumCalculated,
            ],
            "appVer" => $APP_VER,
            "appCode" => $APP_CODE,
            "lang" => "vi",
            "deviceOS" => "ios",
            "channel" => "APP",
            "buildNumber" => 0,
            "appId" => "vn.momo.platform",
            "cmdId" => $time . "000000",
            "time" => $time,
        );

        $headers = array(
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            'agent_id' => 0,
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'msgtype' => 'USER_LOGIN_MSG',
            'platform-timestamp' => time(),
            'sessionkey' => '',
            'tbid' => $this->config['tbid'],
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
            'user_phone' => '',
            'userid' => '',
            'vversion' => '1004',
            'ftv' => '1&1&1'
        );

        $response = $this->curl_momo($linkLogin, $headers, $data);
        if ($response["errorCode"] < 0) {
            throw new Exception($response["errorDesc"]);
        }
        $this->config['access_token'] = $response["extra"]["AUTH_TOKEN"];
        $this->config['session_key'] = $response["extra"]["SESSION_KEY"];
        $this->config['agent_id'] = $response["momoMsg"]["agentId"];
        $this->config['request_encrypt_key'] = $response["extra"]["REQUEST_ENCRYPT_KEY"];
        return $response;
    }

    public function query_bank_info($to_bank_number, $bank_short_name, $bank_code)
    {
        $time = time();
        $data = [
            "appCode" => $this->config['appCode'],
            "appId" => "vn.momo.bank",
            "appVer" => $this->config['appVer'],
            "buildNumber" => 6734,
            "channel" => "APP",
            "lang" => "vi",
            "deviceOS" => "ios",
            "requestId" => $time,
            "agent" => $this->config['phone'],
            "agentId" => (int)$this->config['agent_id'],
            "coreBankCode" => "2001",
            "serviceId" => "2001",
            "benfAccount" => [
                "accId" => $to_bank_number,
                "napasBank" => [
                    "bankCode" => $bank_code,
                    "bankName" => $bank_short_name
                ],
                "nickName" => ""
            ],
            "msgType" => "CheckAccountRequestMsg"
        ];

        $vsign = $this->get_vsign($data);
        if (empty($vsign)) {
            throw new Exception('Không Lấy Được Vsign');
        }

        $headers = [
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            "agent_id" => (int)$this->config["agent_id"],
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'msgtype' => 'CheckAccountRequestMsg',
            'platform-timestamp' => time(),
            'sessionkey' => '',
            'tbid' => $this->config['tbid'],
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
            'user_phone' => '',
            'userid' => '',
            "vsign" => $vsign,
            'vversion' => '1004',
            'ftv' => '1&1&1',
            "http-process-timestamp" => time(),
            "Authorization" => "Bearer " . $this->config['access_token'],
        ];
        $SERVICE_DISPATCHER_LINK = 'https://api.momo.vn/bank/service-dispatcher';
        $response = $this->curl_momo($SERVICE_DISPATCHER_LINK, $headers, $data);
        if ($response["resultCode"] < 0) {
            throw new Exception($response["description"]);
        }
        return $response;
    }


    public function send_mm_2_bank($to_bank_number, $bank_name, $bank_short_name, $bank_code, $amount, $memo)
    {
        $bank_info = $this->query_bank_info($to_bank_number, $bank_short_name, $bank_code);
        $benfPhoneNumberInput = $bank_info['benfAccount']['benfPhoneNumberInput'];
        $receiverName = $bank_info["benfAccount"]["accName"];
        $fullBankName = $bank_info['data']['contactInfo']['bankNameServer'];
        $targetBankReceiverName = $bank_info['data']['contactInfo']['accountName'];

        $data = array(
            'phone' => $this->config['phone'],
            'requestEncryptKey' => $this->config['request_encrypt_key'],
            'accessToken' => $this->config['access_token'],
            'agentId' => (int)$this->config['agent_id'],
            'sessionKey' => $this->config['session_key'],
            'tbid' => $this->config['tbid'],
            'sessionKeyTracking' => $this->config['sessionKeyTracking'],
            'setupKey' => $this->config['setup_key'],
            'bankNumber' => $to_bank_number,
            'bankName' => $bank_name,
            'benfPhoneNumberInput' => $benfPhoneNumberInput,
            'receiverName' => $receiverName,
            'fullBankName' => $fullBankName,
            'bankCode' => $bank_code,
            'amount' => $amount,
            'targetBankReceiverName' => $targetBankReceiverName,
            'memo' => $memo,
        );
        $response = $this->send_bank($data);
        return $response;
    }

    public function query_trans_his()
    {

        $data = array(
            'appCode' => $this->config['appCode'],
            "appId" => 'vn.momo.transactionhistory',
            'appVer' => $this->config['appVer'],
            "buildNumber" => 9832,
            "channel" => 'APP',
            "lang" => 'vi',
            "deviceOS" => 'ios',
            "requestId" => (int)$this->get_microtime(),
            "client" => "sync_app",
            "offset" => 0,
            "limit" => 20,
        );

        $rKey = $this->generate_random(32);
        $requestEncryptKey = $this->config['request_encrypt_key'];

        $requestKey = $this->encode_RSA($rKey, $requestEncryptKey);

        $encryptedInitBody = $this->encrypt_decrypt(json_encode($data), $rKey, 'ENCRYPT');

        $encrypted = array("encrypted" => $encryptedInitBody);

        $vsign = $this->get_vsign($encrypted);
        if (empty($vsign)) {
            throw new Exception('Không Lấy Được Vsign');
        }

        // chỉ cần nhiều đây header là dc: app_version, content-type, vsign, Authorization


        $headers = array(
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            "agent_id" => (int)$this->config["agent_id"],
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'platform-timestamp' => $this->get_microtime(),
            'tbid' => $this->config['tbid'],
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
            "userid" => $this->config["phone"],
            "user_phone" => '',
            "vsign" => $vsign,
            "sessionkey" => $this->config['session_key'],
            'vversion' => '1004',
            'ftv' => '1&1&1',
            "http-process-timestamp" => $this->get_microtime(),
            "Authorization" => "Bearer " . $this->config['access_token'],
            "requestkey" => $requestKey,
        );


        $TRANSHIS_LINK = 'https://api.momo.vn/transhis/api/transhis/browse';

        $response = $this->curl_momo($TRANSHIS_LINK, $headers, $encryptedInitBody);
        $response = json_decode($this->encrypt_decrypt($response, $rKey, 'DECRYPT'), true);
        if ($response && array_key_exists("errorCode", $response)) {
            throw new Exception($response["errorDesc"]);
        }
        return $response;
    }


    public function query_trans_his_detail($transId, $serviceId = 'transfer_p2p')
    {
        //
        $data = array(
            'appCode' => $this->config['appCode'],
            "appId" => 'vn.momo.transactionhistory',
            'appVer' => $this->config['appVer'],
            "buildNumber" => 9832,
            "channel" => 'transaction_app',
            "lang" => 'vi',
            "deviceOS" => 'ios',
            "requestId" => (string)$this->get_microtime(),
            "transId" => (int)$transId,
            "serviceId" => $serviceId,
            "miniAppId" => "vn.momo.transactionhistory",
        );

        $rKey = $this->generate_random(32);
        $requestEncryptKey = $this->config['request_encrypt_key'];

        $requestKey = $this->encode_RSA($rKey, $requestEncryptKey);
        $adjustData = json_encode($data);

        $encryptedInitBody = $this->encrypt_decrypt($adjustData, $rKey, 'ENCRYPT');

        $encrypted = array("encrypted" => $encryptedInitBody);

        $vsign = $this->get_vsign($encrypted);

        if (empty($vsign)) {
            throw new Exception('Không Lấy Được Vsign');
        }

        // chỉ cần nhiều đây header là dc: app_version, content-type, vsign, Authorization

        $headers = array(
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            "agent_id" => (int)$this->config["agent_id"],
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'platform-timestamp' => $this->get_microtime(),
            'tbid' => $this->config['tbid'],
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/0",
            "userid" => $this->config["phone"],
            "user_phone" => '',
            "vsign" => $vsign,
            "sessionkey" => $this->config['session_key'],
            'vversion' => '1004',
            'ftv' => '1&1&1',
            "http-process-timestamp" => $this->get_microtime(),
            "Authorization" => "Bearer " . $this->config['access_token'],
            'requestkey' => $requestKey
        );

        $TRANSHIS_LINK = 'https://api.momo.vn/transhis/api/transhis/detail';

        $response = $this->curl_momo($TRANSHIS_LINK, $headers, $encryptedInitBody);
        $response = json_decode($this->encrypt_decrypt($response, $rKey, 'DECRYPT'), true);
        if ($response && array_key_exists("errorCode", $response)) {
            throw new Exception($response["errorDesc"]);
        }
        return $response;
    }


    public function confirmM2Mu($idTransaction, $tranHisMsg, $amount) {
        $time = $this->get_microtime();
        $checkSumCalculated = $this->get_checksum($this->config['phone'], 'M2MU_CONFIRM', $this->config['setup_key']);

        $confirmBody = [
            "user" => $this->config["phone"],
            "msgType" => "M2MU_CONFIRM",
            "pass" => $this->config['password'],
            "cmdId" => $time . "000000",
            "time" => $time,
            "channel" => "APP",
            "appVer" => $this->config['appVer'],
            "appCode" => $this->config['appCode'],
            "deviceOS" => "ios",
            "errorCode" => 0,
            "errorDesc" => "",
            "lang" => "vi",
            "result" => true,
            "momoMsg" => [
                "otpType" => "NA",
                "ipAddress" => "N/A",
                "_class" => "mservice.backend.entity.msg.M2MUConfirmMsg",
                "quantity" => 1,
                "idFirstReplyMsg" => $idTransaction,
                "moneySource" => 1,
                "tranHisMsgs" => [$tranHisMsg],
                "tranType" => 2018,
                "ids" => [$idTransaction],
                "amount" => $amount,
                "originalAmount" => $amount,
                "fee" => 0,
                "feeCashIn" => 0,
                "feeMoMo" => 0,
                "cashInAmount" => $amount,
                "otp" => "",
                "extras" => "{}"
            ],
            "extra" => ["checkSum" => $checkSumCalculated],

        ];

        $rKey = $this->generate_random(32);
        $requestEncryptKey = $this->config['request_encrypt_key'];

        $requestKey = $this->encode_RSA($rKey, $requestEncryptKey);
        $adjustData = json_encode($confirmBody);

        $encryptedInitBody = $this->encrypt_decrypt($adjustData, $rKey, 'ENCRYPT');

        $encrypted = array("encrypted" => $encryptedInitBody);

        $vsign = $this->get_vsign($encrypted);

        if (empty($vsign)) {
            throw new Exception('Không Lấy Được Vsign');
        }

        // chỉ cần nhiều đây header là dc: app_version, content-type, vsign, Authorization

        $headers = array(
            'Accept' => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'msgtype' => 'M2MU_CONFIRM',
            'app_code' => $this->config['appCode'],
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/". $this->config['agent_id'],
            'app_version' => $this->config['appVer'],
            'lang' => 'vi',
            'channel' => 'APP',
            'vversion' => '1004',
            'ftv' => '1&1&1',
            'vsign' => $vsign,
            'env' => 'production',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'accept-language' => 'vi-VN,vi;q=0.9',
            'device_os' => 'IOS',
            'accept-charset' => 'UTF-8',
            'sessionkey' => $this->config['session_key'],
            'requestkey' => $requestKey,
            "userid" => $this->config["phone"],
            "user_phone" => $this->config["phone"],
            'tbid' => $this->config['tbid'],
            "agent_id" => (int)$this->config["agent_id"],
            "http-process-timestamp" => $this->get_microtime() + 500,
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            "Authorization" => "Bearer " . $this->config['access_token'],
        );

        $M2MU_CONFIRM_LINK = 'https://owa.momo.vn/api/M2MU_CONFIRM';

        $response = $this->curl_momo($M2MU_CONFIRM_LINK, $headers, $encryptedInitBody);

        $response = json_decode($this->encrypt_decrypt($response, $rKey, 'DECRYPT'), true);
        print_r($response);



    }

    public function send_momo_2_momo($receiver, $amount, $memo)
    {
        $time = $this->get_microtime();
        $checkSumCalculated = $this->get_checksum($this->config['phone'], 'M2MU_INIT', $this->config['setup_key']);

        $tranList = [
            "themeUrl" => "https://img.mservice.com.vn/app/img/transfer/theme/trasua-750x260.png",
            "stickers" => '',
            "partnerName" => 'Tran Van An', // lay ten momo o API khac bo vao day
            "serviceId" => "transfer_p2p",
            "originalAmount" => $amount,
            "receiverType" => 1,
            "partnerId" => $receiver,
            "serviceCode" => "transfer_p2p",
            "_class" => "mservice.backend.entity.msg.M2MUInitMsg",
            "tranType" => 2018,
            "comment" => $memo,
            "moneySource" => 1,
            "partnerCode" => "momo",
            "rowCardId" => null,
            "sourceToken" => "SOF-1",
            "extras" => json_encode([
                "avatarUrl" => "",
                "aliasName" => "",
                "appSendChat" => false,
                "stickers" => "",
                "themeId" => 261,
                "source" => "search_p2p",
                "expenseCategory" => "66",
                "categoryName" => "Gửi tiền người thân",
                "agentId" => $this->config["agent_id"],
                "bankCustomerId" => ""
            ], JSON_UNESCAPED_UNICODE),
        ];

        $initBody = [
            "appCode" => $this->config['appCode'],
            "appId" => "vn.momo.payment",
            "appVer" => $this->config['appVer'],
            "buildNumber" => 3437,
            "channel" => "APP",
            "cmdId" => $time . "000000",
            "time" => $time,
            "deviceOS" => "ios",
            "errorCode" => 0,
            "errorDesc" => "",
            "extra" => ["checkSum" => $checkSumCalculated],
            "lang" => "vi",
            "user" => $this->config["phone"],
            "msgType" => "M2MU_INIT",
            "momoMsg" => [
                "tranType" => 2018,
                "tranList" => [$tranList],
                "clientTime" => $time,
                "serviceId" => "transfer_p2p",
                "_class" => "mservice.backend.entity.msg.M2MUInitMsg",
                "defaultMoneySource" => 1,
                "sourceToken" => "SOF-1",
                "giftId" => "",
                "useVoucher" => 0,
                "discountCode" => null,
                "prepaidIds" => "",
                "usePrepaid" => 0
            ],
            "pass" => "",
        ];

        $rKey = $this->generate_random(32);
        $requestEncryptKey = $this->config['request_encrypt_key'];

        $requestKey = $this->encode_RSA($rKey, $requestEncryptKey);
        $adjustData = json_encode($initBody, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

        $encryptedInitBody = $this->encrypt_decrypt($adjustData, $rKey, 'ENCRYPT');

        $encrypted = array("encrypted" => $encryptedInitBody);

        $vsign = $this->get_vsign($encrypted);

        if (empty($vsign)) {
            throw new Exception('Không Lấy Được Vsign');
        }

        // chỉ cần nhiều đây header là dc: app_version, content-type, vsign, Authorization

        $headers = array(
            'accept' => 'application/json',
            'accept-charset' => 'UTF-8',
            'accept-language' => 'vi-VN,vi;q=0.9',
            "agent_id" => (int)$this->config["agent_id"],
            'app_code' => $this->config['appCode'],
            'app_version' => $this->config['appVer'],
            'channel' => 'APP',
            'content-type' => 'application/json',
            'device_os' => 'IOS',
            'env' => 'production',
            'lang' => 'vi',
            'momo-session-key-tracking' => $this->config['sessionKeyTracking'],
            'platform-timestamp' => $this->get_microtime(),
            'tbid' => $this->config['tbid'],
            'timezone' => 'Asia/Ho_Chi_Minh',
            'user-agent' => "MoMoPlatform Store/" . $this->config['appVer'] . "." . $this->config['appCode'] . " CFNetwork/1335.0.3.4 Darwin/21.6.0 ( " . $this->config['device'] . " iOS/" . $this->config['firmware'] . ") AgentID/". $this->config['agent_id'],
            "userid" => $this->config["phone"],
            "user_phone" => '',
            "vsign" => $vsign,
            "sessionkey" => $this->config['session_key'],
            'vversion' => '1004',
            'ftv' => '1&1&1',
            "http-process-timestamp" => $this->get_microtime(),
            "Authorization" => "Bearer " . $this->config['access_token'],
            'requestkey' => $requestKey,
            'msgtype' => 'M2MU_INIT'
        );

        $M2MU_INIT_LINK = 'https://owa.momo.vn/api/M2MU_INIT';

        $response = $this->curl_momo($M2MU_INIT_LINK, $headers, $encryptedInitBody);
        $response = json_decode($this->encrypt_decrypt($response, $rKey, 'DECRYPT'), true);

        $checkSofInfo = json_decode($response['extra']['sofInfo'], true);

        $momoWalletInfo = array_filter($checkSofInfo, function ($item) {
            return $item['moneySource'] === 1; // Change the condition as needed
        });


        if ($momoWalletInfo[0]['balance'] < $amount) {
            echo 'Không đủ số dư để CK MOMO';
            throw new Exception('Không đủ số dư ví để CK MOMO');

        } else {
            // do confirm init

            $idTransaction = $response['momoMsg']['replyMsgs'][0]['id'];
            $tranHisMsg = $response['momoMsg']['replyMsgs'][0]['tranHisMsg'];

            $this->confirmM2Mu($idTransaction, $tranHisMsg, $amount);




        }
    }
}
