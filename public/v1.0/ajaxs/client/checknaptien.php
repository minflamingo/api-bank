<?php
define("IN_SITE", true);

require_once("../../core/DB.php");
require_once("../../core/helpers.php");


// Kiểm tra xem có phải là một yêu cầu AJAX không
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'checkTransaction') {
    $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
    $currentTime = isset($_GET['currentTime']) ? $_GET['currentTime'] : '';

    $query = $NNL->get_row("SELECT * FROM `invoices` WHERE `user_id` = '{$userId}' AND `create_time` > '{$currentTime}' LIMIT 1");

    if ($query != false) {
        echo $query['create_time'];
    } else {
        echo 'FAIL';
    }
    exit;
}
?>