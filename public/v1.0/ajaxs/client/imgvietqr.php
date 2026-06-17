<?php
define("IN_SITE", true);
if (!defined('IN_SITE')) {
    //die('The Request Not Found');
}
//require_once __DIR__ . '/../../core/is_user.php';
require_once(__DIR__.'/../../core/DB.php');
require_once(__DIR__.'/../../core/helpers.php');
//$getUser['id']=1;
$bankId = $_GET['bankId']; 
$userid = $_GET['userid']; 

// Lấy thông tin từ CSDL dựa trên $bankId...
$row = $NNL->get_row("SELECT * FROM `bank` WHERE `id` = '{$bankId}'");

$qrDataURL = takeimageqr($row['accountNumber'], $row['accountName'], $row['codebank'], $row['noidungnap'].$userid, 'KKmbu4A');

echo $qrDataURL;
?>