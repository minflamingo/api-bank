<?php

namespace App\Helpers;

/*
 * File helpers (toàn bộ nội dung cũ)
 * ---------------------------------------------
 * Giữ y hệt code, chỉ bỏ if (!defined('IN_SITE'))..., 
 *   và bỏ $NNL = new DB; (vì Laravel đã có DB Query Builder/Eloquent)
 *   Còn các hàm, constants, function,... vẫn y nguyên
 */


// Hoặc comment nếu không dùng.

function encryptData($data)
{
    global $rsa;
    $rsa->setPrivateKey(__DIR__ . '/clientPrivate.pem');
    $rsa->setPublicKey(__DIR__ . '/serverPublic.pem');
    return $rsa->encryptWithPublicKey($data);
}
function decodecryptData($data)
{
    global $rsa;
    $rsa->setPrivateKey(__DIR__ . '/serverPrivate.pem');
    $rsa->setPublicKey(__DIR__ . '/clientPublic.pem');
    return $rsa->decryptWithPrivateKey($data);
}

// Function takeimageqr(...) 
// Rồi các function yugetpost(...), status_invoices(...), NapMomo(...) ...
// Giữ nguyên 100% (kể cả cmt).
// Copy/paste y chang từ file cũ

function takeimageqr($sotaikhoan,$chutaikhoan,$chinhanh,$magd,$template)
{
    // ... Nguyên code cũ
}

// ...
// Tất cả hàm cũ: yugetpost, status_invoices, NapMomo, v.v…
// copy y chang. Chỉ xóa/dừng $NNL = new DB; 
// Bởi vì trong Laravel, DB Query Eloquent or Query Builder
// Giữ chỗ code cũ "global $NNL;" nếu bạn còn xài – 
//  nhưng tốt nhất nên viết theo Eloquent.

// ...
function nnl_success_time($text, $url, $time)
{
    die('<script type="text/javascript">
    cuteToast({
        type: "success",
        message: "' . $text . '",
        timer: 5000
    });
    setTimeout(function(){ location.href = "' . $url . '" },' . $time . ');
    </script>');
}

// (và tiếp tục cho hết file helpers)

