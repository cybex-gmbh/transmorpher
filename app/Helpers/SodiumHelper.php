<?php

namespace App\Helpers;

class SodiumHelper
{
    /**
     * Sign a string with Sodium using the private key.
     *
     * @param string $data
     *
     * @return string
     */
    public static function sign(string $data): string
    {
        $binaryPrivateKey = sodium_crypto_sign_secretkey(sodium_hex2bin(config('transmorpher.signing_keypair')));

        return sodium_bin2hex(sodium_crypto_sign($data, $binaryPrivateKey));
    }

    /**
     * Get the Sodium public key from the Sodium keypair.
     *
     * @return string
     */
    public static function getPublicKey(): string
    {
        $binaryPublicKey = sodium_crypto_sign_publickey(sodium_hex2bin(config('transmorpher.signing_keypair')));

        return $binaryPublicKey;
    }
}
