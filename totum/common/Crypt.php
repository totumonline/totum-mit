<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 09.06.2018
 * Time: 16:12
 */

namespace totum\common;

use totum\config\Conf;

class Crypt
{
    private static function getKey($sess)
    {
        if ($sess === true) {
            if (empty($_SESSION['crypt_key'])) {
                $_SESSION['crypt_key'] = md5(microtime(true));
            }

            return $_SESSION['crypt_key'];
        } else {
            return 'Y`9~g8_cjZrZkGd!' . $sess;
        }
    }

    public static function setKeySess()
    {
        if (empty($_SESSION['crypt_key'])) {
            $_SESSION['crypt_key'] = md5(microtime(true));
        }
    }

    public static function getCrypted($string, $sess = true)
    {
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($string, $cipher, static::getKey($sess), $options = OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, static::getKey($sess), $as_binary = true);
        return base64_encode($iv . $hmac . $ciphertext_raw);
    }

    public static function getDeCrypted($string, $sess = true)
    {
        $c = base64_decode($string);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        $plaintext = openssl_decrypt($ciphertext_raw, $cipher, static::getKey($sess), $options = OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, static::getKey($sess), $as_binary = true);
        if (hash_equals($hmac, $calcmac)) {
            return $plaintext;
        }
        return false;
    }
}
