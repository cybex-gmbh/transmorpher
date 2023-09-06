<?php

namespace App\Helpers;

class SigningHelper
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
        return sodium_bin2hex(sodium_crypto_sign($data, static::getPrivateKey()));
    }

    /**
     * Decrypt a signed string with Sodium using the public key.
     *
     * @param string $signed
     *
     * @return bool|string
     */
    public static function decrypt(string $signed): bool|string
    {
        return sodium_crypto_sign_open(sodium_hex2bin($signed), self::getPublicKey());
    }

    /**
     * Get the Sodium private key from the Sodium keypair.
     *
     * @return string Binary string of the private key.
     */
    protected static function getPrivateKey(): string
    {
        return sodium_crypto_sign_secretkey(sodium_hex2bin(config('transmorpher.signing_keypair')));
    }

    /**
     * Get the Sodium public key from the Sodium keypair.
     *
     * @return string Binary string of the public key.
     */
    public static function getPublicKey(): string
    {
        return sodium_crypto_sign_publickey(sodium_hex2bin(config('transmorpher.signing_keypair')));
    }
}
