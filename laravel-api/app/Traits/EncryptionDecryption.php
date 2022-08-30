<?php
namespace App\Traits;
trait EncryptionDecryption {

    /**
     * @param string $plainText
     * @return string
     */
    public function encryption ($plainText = "") {
        $password = '3sc3RLrpd17';
        $method = 'aes-256-cbc';
        $password = substr(hash('sha256', $password, true), 0, 32);
        $iv = chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0) . chr(0x0);
        $encrypted = base64_encode(openssl_encrypt($plainText, $method, $password, OPENSSL_RAW_DATA, $iv));
        return $encrypted;
    }

    /**
     * @param string $encrypted
     * @return string
     */
    public function decryptio ($encrypted = "") {
        $string = 'code@123';
        $output = false;

        $encrypt_method = "AES-256-CBC";
        $secret_key = '3sc3RLrpd17';

        $secret_iv=chr(1).chr(2).chr(3).chr(4).chr(5).chr(6).chr(7).chr(8);


        $key = substr(hash('sha1', $secret_key), 0, 32);

        $iv = substr(hash('sha1', $secret_iv), 0, 16);
        $outputen1 = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        $outputen1 = base64_encode($outputen1);

        $outputde1 = openssl_decrypt(base64_decode($outputen1), $encrypt_method, $key, 0, $iv);
        return $outputde1;
    }
}