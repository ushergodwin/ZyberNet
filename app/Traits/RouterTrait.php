<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

trait RouterTrait
{
    function encryptRouterPassword($password)
    {
        $encrypt_method = 'AES-256-CBC';
        $secret_key = env('SECRET_HASHING_KEY'); // user define private key
        $secret_iv = env('SECRET_HASHING_IV'); // user define secret key
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16); // sha256 is hash_hmac_algo
        $output = openssl_encrypt($password, $encrypt_method, $key, 0, $iv);

        return base64_encode($output);
    }

    function decryptPassword($hash)
    {
        $encrypt_method = 'AES-256-CBC';
        $secret_key = env('SECRET_HASHING_KEY'); // user define private key
        $secret_iv = env('SECRET_HASHING_IV'); // user define secret key
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16); // sha256 is hash_hmac_algo

        return openssl_decrypt(base64_decode($hash), $encrypt_method, $key, 0, $iv);
    }
}
