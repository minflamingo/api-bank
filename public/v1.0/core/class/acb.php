<?php
class ACB {
	public $clientId = 'iuSuHYVufIUuNIREV0FB9EoLn9kHsDbm';
    
    private $URL = [
        "LOGIN" => "https://apiapp.acb.com.vn/mb/v2/auth/tokens",
        "getBalance" => "https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/transfers/list/account-payment",
        "INFO" => "https://mobile.mbbank.com.vn/retail_lite/loan/getUserInfo",
        "GET_TOKEN" => "https://mobile.mbbank.com.vn/retail_lite/loyal/getToken",
        "GET_NOTI" => "https://mobile.mbbank.com.vn/retail_lite/notification/getNotificationDataList",
        "GET_TRANS" => "https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/saving/tx-history"
    ];
    
    public function loginAcb($username, $password) {
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'Host: apiapp.acb.com.vn',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13'
        ];
        
        $data = [
            "clientId" => $this->clientId,
            "username" => $username,
            "password" => $password
        ];
        
        return $this->curlRequest("LOGIN", $header, json_encode($data));
    }
    
    public function getBalance($token) {
        $header = [
            'Content-Type: application/json;',
            'Host: apiapp.acb.com.vn',
            "Authorization: bearer $token"
        ];
        
        $result = $this->curlRequest("getBalance", $header);
        return json_encode($result);
    }
    
    public function getTransactionHistory($accountNo, $rows, $token) {
          $curl = curl_init();
		  $url2 = "https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/saving/tx-history?maxRows=".$rows."&account=".$accountNo;
		  curl_setopt_array($curl, array(
		  CURLOPT_URL => $url2, // Sử dụng biến $rows trong URL
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		  CURLOPT_HTTPHEADER => array(
			'Host: apiapp.acb.com.vn',
			'Accept: application/json, text/plain, */*',
			'User-Agent: ACB-MBA/2 CFNetwork/1474 Darwin/23.0.0',
			'Accept-Language: vi',
			"Authorization: bearer $token",
			'x-app-version: 3.12.4'
		  ),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		//echo $response;
        return $response;
    }
    
    private function curlRequest($action, $header, $data = null) {
        return $this->curlRequestDirect($this->URL[$action], $header, $data);
    }
    
    private function curlRequestDirect($url, $header, $data = null) {
        $curl = curl_init();
        $header[] = 'Content-Type: application/json;';
        $header[] = 'Accept: application/json, text/plain, */*';
        if ($data) {
            $header[] = 'Content-Length: ' . strlen($data);
        }
        
        $opt = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => $data ? true : false,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_CUSTOMREQUEST => $data ? 'POST' : 'GET',
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_ENCODING => "",
            CURLOPT_HEADER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => 20
        ];
        
        curl_setopt_array($curl, $opt);
        $body = curl_exec($curl);
        return json_decode($body, true);
    }
}