<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
require_once('lib/RSA/Crypt/RSA.php');

// get from main-es2015...js file
define("defaultPublicKey", "LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlJQklqQU5CZ2txaGtpRzl3MEJBUUVGQUFPQ0FROEFNSUlCQ2dLQ0FRRUFpa3FRckl6WkprVXZIaXNqZnU1WkNOK1RMeS8vNDNDSWM1aEpFNzA5VElLM0hiY0M5dnVjMitQUEV0STZwZVNVR3FPbkZvWU93bDNpOHJSZFNhSzE3RzJSWk4wMU1JcVJJSi82YWM5SDRMMTFkdGZRdFI3S0hxRjdLRDBmajZ2VTRrYjUrMGN3UjNSdW1CdkRlTWxCT2FZRXBLd3VFWTlFR3F5OWJjYjVFaE5HYnh4TmZiVWFvZ3V0VndHNUMxZUtZSXR6YVlkNnRhbzNncTdzd05IN3A2VWRsdHJDcHhTd0ZFdmM3ZG91RTJzS3JQRHA4MDdaRzJkRnNsS3h4bVI0V0hESFdmSDBPcHpyQjVLS1dRTnl6WHhUQlhlbHFyV1pFQ0xSeXBOcTdQKzFDeWZnVFNkUTM1ZmRPN00xTW5pU0JUMVYzM0xkaFhvNzMvOXFENWU1VlFJREFRQUIKLS0tLS1FTkQgUFVCTElDIEtFWS0tLS0t");

// generate from genKeys function
define("clientPublicKey", "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCnuY5jaHVt0cENefO8vqCIAENk2cFVB6uSDsQa32w26dZ7BI7zEhspBb5EnaSQ7aaj0tY/iOBJZ65eVkOhv7zkqqhAs6WUuEI8SYDZOd+imD6FviXcpMR52mKQ0+6xd3JzorjW7wT6ScEm+mOjKr0RgvvOjiNXRmp0xP3JazEJXwIDAQAB");

// generate from genKeys function
$clientPrivateKey = $_ENV['VCB_CLIENT_PRIVATE_KEY'] ?? getenv('VCB_CLIENT_PRIVATE_KEY') ?: '';
define("clientPrivateKey", str_replace('\n', "\n", $clientPrivateKey));

function encodeRSA($content, $key)
{
    $rsa = new Crypt_RSA();
    $rsa->loadKey($key);
    $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
    return base64_encode($rsa->encrypt($content));
}

function decodeRSA($content, $key)
{
    $rsa = new Crypt_RSA();
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
