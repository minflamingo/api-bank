<?php
//
echo gethostbyname(gethostname());//để lấy ip của server hosting
//chuyển tiền
$curl = curl_init();
$dataPost = array(
    "type" => "transfer",
    "token" =>"0aaaa",
    "phone"  =>"",
    "amount" => "100",
    "comment" =>"Noi dung",
    "password" => "",
    "ip" => gethostbyname(gethostname())
);
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://domain/api/historymomo',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>$dataPost,
));

$response = curl_exec($curl);

curl_close($curl);
print_r($response);

//check lsgd
$curl = curl_init();
$dataPost = array(
    "type" => "history",
    "token" =>"0edd6fcb-f6",
);
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://mienphi.sieuthicode.net/api/historymomo',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>$dataPost,
));

$response = curl_exec($curl);

curl_close($curl);
print_r($response);

//check số dư
$curl = curl_init();
$dataPost = array(
    "type" => "balance",
    "token" =>"0edd6fcb-f6",
);
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://mienphi.sieuthicode.net/api/historymomo',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>$dataPost,
));

$response = curl_exec($curl);

curl_close($curl);
print_r($response);

//check lsgd vietcombank
$curl = curl_init();
$dataPost = array(
    "type" => "history",
    "token" =>"0edd6fcb-f6",
);
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://domain/api/historyvietcombank',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>$dataPost,
));

$response = curl_exec($curl);

curl_close($curl);
print_r($response);

//check số dư vietcombank
$curl = curl_init();
$dataPost = array(
    "type" => "balance",
    "token" =>"0edd6fcb-f6",
);
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://domain/api/historyvietcombank',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>$dataPost,
));

$response = curl_exec($curl);

curl_close($curl);
print_r($response);

//check lsgd mbbank
$curl = curl_init();
$dataPost = array(
    "type" => "history",
    "token" =>"0edd6fcb-f6",
);
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://domain/api/historymbbank',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>$dataPost,
));

$response = curl_exec($curl);

curl_close($curl);
print_r($response);

//check số dư mbbank
$curl = curl_init();
$dataPost = array(
    "type" => "balance",
    "token" =>"0edd6fcb-f6",
);
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://domain/api/historymbbank',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>$dataPost,
));

$response = curl_exec($curl);

curl_close($curl);
print_r($response);

