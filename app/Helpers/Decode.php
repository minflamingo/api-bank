<?php

namespace App\Helpers;

/*
 * File decode (toàn bộ nội dung cũ)
 * ---------------------------------------------
 * Bỏ qua phần if (!defined('IN_SITE')) { ... } vì Laravel không cần
 * Giữ nguyên DEFINE, Hàm, v.v... để code chạy y hệt
 */



// get from main-es2015...js file
define("defaultPublicKey", getenv('VCB_DEFAULT_PUBLIC_KEY') ?: "");
define("clientPublicKey", getenv('VCB_CLIENT_PUBLIC_KEY') ?: "");
define("clientPrivateKey", str_replace('\n', "\n", getenv('VCB_CLIENT_PRIVATE_KEY') ?: ""));

// Keycaptcha
define("CAPTCHA_KEY", getenv('VCB_CAPTCHA_API_KEY') ?: "");

// Các hàm encodeRSA, decodeRSA, encryptAES, decryptAES, decryptResponse, encryptKey, encryptRequest
// (giữ nguyên y hệt)

function encodeRSA($content, $key)
{
    $rsa = new \Crypt_RSA();
    $rsa->loadKey($key);
    $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
    return base64_encode($rsa->encrypt($content));
}

function decodeRSA($content, $key)
{
    $rsa = new \Crypt_RSA();
    $rsa->loadKey($key);
    $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
    return $rsa->decrypt(base64_decode($content));
}

function encryptAES($key, $data)
{
    $key = substr($key, 0, 32);
    $iv = substr($key, 0, 16);

    if (is_array($data)) {
        $data['clientPubKey'] = clientPublicKey;
        $data = json_encode($data);
    }

    $result = openssl_encrypt($data, 'AES-256-CTR', $key, OPENSSL_RAW_DATA, $iv);

    return base64_encode($iv . $result);
}

function decryptAES($key, $data)
{
    $key = base64_decode($key);
    $data = base64_decode($data);

    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16, strlen($data));

    return openssl_decrypt($ciphertext, 'AES-256-CTR', $key, OPENSSL_RAW_DATA, $iv);
}

function decryptResponse($key, $data)
{
    return decryptAES(decodeRSA($key, clientPrivateKey), $data);
}

function encryptKey($key)
{
    $key = substr($key, 0, 32);
    $key = base64_encode($key);

    $publicKey = base64_decode(defaultPublicKey);
    return encodeRSA($key, $publicKey);
}

function encryptRequest($key, $data)
{
    return array("k" => encryptKey($key), "d" => encryptAES($key, $data));
}
