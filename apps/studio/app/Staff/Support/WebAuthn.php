<?php

namespace App\Staff\Support;

use App\Models\User;
use App\Staff\Models\Passkey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Both WebAuthn ceremonies for the staff surface. The relying party is the
 * configured admin origin and never the request host: trustProxies('*') makes
 * Host attacker-influenced, and a credential is bound to the RP ID it was
 * created for.
 */
class WebAuthn
{
    public const TTL_SECONDS = 300;

    private readonly string $origin;

    private readonly string $rpId;

    private readonly SerializerInterface $serializer;

    private readonly CeremonyStepManagerFactory $ceremonies;

    public function __construct()
    {
        $this->origin = config('services.admin.url');
        $this->rpId = parse_url($this->origin, PHP_URL_HOST);
        $this->serializer = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))->create();
        $this->ceremonies = new CeremonyStepManagerFactory;
        $this->ceremonies->setAllowedOrigins([$this->origin]);
    }

    public static function stash(string $key, array $payload): void
    {
        session()->put($key, [...$payload, 'expires_at' => now()->addSeconds(self::TTL_SECONDS)->timestamp]);
    }

    /** Single use: the challenge is spent whether or not the attempt that follows succeeds. */
    public static function pull(string $key): ?array
    {
        $payload = session()->pull($key);

        return is_array($payload) && $payload['expires_at'] >= now()->timestamp ? $payload : null;
    }

    public function creationOptions(User $user): string
    {
        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('Auri CRM', $this->rpId),
            PublicKeyCredentialUserEntity::create($user->email, $user->public_id, $user->name),
            random_bytes(32),
            [PublicKeyCredentialParameters::createPk(-7), PublicKeyCredentialParameters::createPk(-257)],
            AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $user->passkeys()->get()->map(fn (Passkey $passkey) => $this->record($passkey)->getPublicKeyCredentialDescriptor())->all(),
            self::TTL_SECONDS * 1000,
        );

        return $this->json($options);
    }

    public function register(User $user, string $name, string $optionsJson, string $credentialJson): ?Passkey
    {
        try {
            $options = $this->serializer->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class, 'json');
            $credential = $this->serializer->deserialize($credentialJson, PublicKeyCredential::class, 'json');

            if (! $credential->response instanceof AuthenticatorAttestationResponse) {
                return null;
            }

            $record = AuthenticatorAttestationResponseValidator::create($this->ceremonies->creationCeremony())
                ->check($credential->response, $options, $this->rpId);

            return $user->passkeys()->create([
                'name' => $name,
                'credential_id' => Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId),
                'source' => $this->json($record),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    public function requestOptions(): string
    {
        return $this->json(PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->rpId,
            [],
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            self::TTL_SECONDS * 1000,
        ));
    }

    public function authenticate(string $optionsJson, string $credentialJson): ?Passkey
    {
        try {
            $options = $this->serializer->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
            $credential = $this->serializer->deserialize($credentialJson, PublicKeyCredential::class, 'json');

            if (! $credential->response instanceof AuthenticatorAssertionResponse) {
                return null;
            }

            $passkey = Passkey::query()
                ->with('user')
                ->where('credential_id', Base64UrlSafe::encodeUnpadded($credential->rawId))
                ->first();

            if ($passkey === null) {
                return null;
            }

            $record = AuthenticatorAssertionResponseValidator::create($this->ceremonies->requestCeremony())
                ->check($this->record($passkey), $credential->response, $options, $this->rpId, $passkey->user->public_id);

            $passkey->forceFill(['source' => $this->json($record), 'last_used_at' => now()])->save();

            return $passkey;
        } catch (Throwable) {
            return null;
        }
    }

    private function record(Passkey $passkey): CredentialRecord
    {
        return $this->serializer->deserialize($passkey->source, CredentialRecord::class, 'json');
    }

    private function json(object $value): string
    {
        return $this->serializer->serialize($value, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS => JSON_THROW_ON_ERROR,
        ]);
    }
}
