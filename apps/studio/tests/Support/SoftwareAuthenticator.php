<?php

namespace Tests\Support;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use OpenSSLAsymmetricKey;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * An ES256 authenticator in software, so the real ceremonies run end to end:
 * the lib parses the CBOR and verifies the signature this class produces.
 * Flags are the authenticator-data bits: 0x01 user present, 0x04 user
 * verified, 0x40 attested credential data.
 */
class SoftwareAuthenticator
{
    public const UP_UV = 0x05;

    private OpenSSLAsymmetricKey $key;

    public readonly string $credentialId;

    private ?string $userHandle = null;

    public function __construct()
    {
        $this->key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $this->credentialId = random_bytes(32);
    }

    public function attestation(array $options, string $origin, int $flags = 0x45, int $counter = 0): string
    {
        $this->userHandle = Base64UrlSafe::decodeNoPadding($options['user']['id']);

        $ec = openssl_pkey_get_details($this->key)['ec'];
        $cose = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)));

        $authData = hash('sha256', $options['rp']['id'], true)
            .chr($flags).pack('N', $counter)
            .str_repeat("\0", 16).pack('n', strlen($this->credentialId)).$this->credentialId.$cose;

        $attestationObject = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return json_encode([
            ...$this->envelope(),
            'response' => [
                'clientDataJSON' => $this->clientData('webauthn.create', $options['challenge'], $origin),
                'attestationObject' => Base64UrlSafe::encodeUnpadded((string) $attestationObject),
                'transports' => ['internal'],
            ],
        ]);
    }

    public function assertion(array $options, string $origin, int $counter, int $flags = self::UP_UV, ?string $userHandle = null): string
    {
        $clientData = $this->clientData('webauthn.get', $options['challenge'], $origin);
        $authData = hash('sha256', $options['rpId'], true).chr($flags).pack('N', $counter);

        openssl_sign($authData.hash('sha256', Base64UrlSafe::decodeNoPadding($clientData), true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return json_encode([
            ...$this->envelope(),
            'response' => [
                'clientDataJSON' => $clientData,
                'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
                'signature' => Base64UrlSafe::encodeUnpadded($signature),
                'userHandle' => Base64UrlSafe::encodeUnpadded($userHandle ?? $this->userHandle),
            ],
        ]);
    }

    private function envelope(): array
    {
        $id = Base64UrlSafe::encodeUnpadded($this->credentialId);

        return ['id' => $id, 'rawId' => $id, 'type' => 'public-key', 'clientExtensionResults' => (object) []];
    }

    private function clientData(string $type, string $challenge, string $origin): string
    {
        return Base64UrlSafe::encodeUnpadded(json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $origin,
            'crossOrigin' => false,
        ]));
    }
}
