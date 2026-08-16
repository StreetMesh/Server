<?php

namespace StreetMesh\Protocol\Laravel\Permissions;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use StreetMesh\Protocol\AuthorizationRequest;
use StreetMesh\Protocol\AuthorizationServer;
use StreetMesh\Protocol\ClientAssertion;
use StreetMesh\Protocol\ClientMetadata;
use StreetMesh\Protocol\Dpop;
use StreetMesh\Protocol\Laravel\Attestations\Attestations;
use StreetMesh\Protocol\Laravel\Identity\DidResolver;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use StreetMesh\Protocol\Network;
use StreetMesh\Protocol\P256;
use StreetMesh\Protocol\Pkce;

/**
 * Asking somebody else's server for permission, and using it.
 *
 * The venue's half of the exchange, and the counterpart to `Permissions`. That
 * one answers strangers; this one goes out to a server nobody here has heard of
 * and comes back with something it can spend.
 *
 * Three things happen here that a caller should not have to think about: the
 * whole chain from a typed name to an authorization server, the nonce dance,
 * and refreshing a token before it is used rather than after it fails.
 */
final class Delegations
{
    public function __construct(
        private readonly Network $network,
        private readonly DidResolver $resolver,
        private readonly Identities $identities,
        private readonly Attestations $attestations,
    ) {}

    /**
     * Begin: from a name somebody typed to a URL to send them to.
     *
     * @param  array<int, string>  $scopes  beyond `atproto`
     * @return array{delegation: Delegation, url: string}
     */
    public function begin(string $handle, array $scopes, string $redirectUri): array
    {
        $server = AuthorizationServer::forAccount(
            $handle,
            fn (string $did): array => $this->resolver->document($did),
            $this->network,
        );

        if (! $server->accepts('ES256')) {
            throw new RuntimeException("[{$server->issuer}] will not take a signature this server can make.");
        }

        $key = P256::generate();
        $pkce = Pkce::generate();
        $state = self::secret();

        $asked = array_values(array_unique([ClientMetadata::BASE_SCOPE, ...$scopes]));

        $delegation = Delegation::create([
            'handle' => $handle,
            'issuer' => $server->issuer,
            'dpop_key' => Delegation::store($key),
            'state' => $state,
            'code_verifier' => $pkce->verifier,
            'scope' => implode(' ', $asked),
        ]);

        $answer = $this->send($server->pushedAuthorizationRequest, AuthorizationRequest::pushed(
            clientId: $this->clientId(),
            redirectUri: $redirectUri,
            state: $state,
            pkce: $pkce,
            assertion: $this->assertion($server->issuer),
            scopes: $asked,
            loginHint: $handle,
        ), $key);

        $requestUri = $answer->json('request_uri');

        if (! is_string($requestUri)) {
            $delegation->delete();

            throw new RuntimeException(
                "[{$server->issuer}] would not take the request: ".(string) $answer->json('error_description', $answer->body())
            );
        }

        return [
            'delegation' => $delegation,
            'url' => AuthorizationRequest::redirectTo($server->authorization, $requestUri, $this->clientId()),
        ];
    }

    /**
     * Finish: the person is back, with a code and the state we sent them out
     * with.
     *
     * The state is looked up rather than trusted — it is how this server knows
     * the answer belongs to a question it actually asked, and a callback
     * arriving with a state nobody issued is somebody else's business.
     */
    public function complete(string $state, string $code, string $redirectUri): Delegation
    {
        $delegation = Delegation::query()->where('state', $state)->first()
            ?? throw new RuntimeException('That answer does not match anything this server asked.');

        $server = AuthorizationServer::atOrigin($delegation->issuer, $this->network);

        $granted = $this->send($server->token, AuthorizationRequest::redeem(
            clientId: $this->clientId(),
            redirectUri: $redirectUri,
            code: $code,
            pkce: Pkce::fromVerifier((string) $delegation->code_verifier),
            assertion: $this->assertion($delegation->issuer),
        ), $delegation->key());

        return $this->keep($delegation, $granted);
    }

    /**
     * A live token, refreshed first if it is nearly out.
     *
     * Refreshing before use rather than after failure, because the failure a
     * visitor would otherwise see is one this server could have avoided by
     * looking at a clock.
     */
    public function live(Delegation $delegation): Delegation
    {
        if (! $delegation->isStale()) {
            return $delegation;
        }

        if ($delegation->refresh_token === null) {
            throw new RuntimeException('That permission has run out and there is no way to renew it.');
        }

        $server = AuthorizationServer::atOrigin($delegation->issuer, $this->network);

        return $this->keep($delegation, $this->send($server->token, AuthorizationRequest::refresh(
            clientId: $this->clientId(),
            refreshToken: $delegation->refresh_token,
            assertion: $this->assertion($delegation->issuer),
        ), $delegation->key()));
    }

    /**
     * Write a record into their store, signed by this server.
     *
     * The claims are signed before they are sent, so what arrives is checkable
     * by anybody rather than merely received from us — which is the difference
     * between giving somebody a record and giving them our word.
     *
     * @param  array<string, mixed>  $claims
     * @return array{uri: string, cid: string}
     */
    public function write(Delegation $delegation, string $collection, array $claims): array
    {
        if (! $delegation->permits($collection)) {
            throw new RuntimeException("That visitor did not agree to records of type [{$collection}].");
        }

        $delegation = $this->live($delegation);
        $identity = $this->identities->forServer();

        $answer = $this->send(
            $delegation->issuer.'/xrpc/com.atproto.repo.createRecord',
            [
                'collection' => $collection,
                'record' => [
                    'attestation' => $this->attestations->issue($claims, $identity->key(), $identity->keyId()),
                ],
            ],
            $delegation->key(),
            token: (string) $delegation->access_token,
        );

        $uri = $answer->json('uri');

        if (! is_string($uri)) {
            throw new RuntimeException(
                'That record was not accepted: '.(string) $answer->json('message', $answer->body())
            );
        }

        return ['uri' => $uri, 'cid' => (string) $answer->json('cid')];
    }

    /**
     * One request, retried once if the server hands back a new nonce.
     *
     * Nonces rotate every few minutes, so being told to use a new one is an
     * ordinary event in a working conversation rather than a failure. A client
     * that treats it as an error works until the first rotation and then breaks
     * at a boundary it cannot see — which is exactly how this was found, in a
     * script that did not retry.
     *
     * @param  array<string, mixed>  $body
     */
    private function send(string $url, array $body, P256 $key, ?string $token = null): Response
    {
        $nonce = $this->nonceFor($url);

        $attempt = fn (?string $with): Response => Http::withHeaders(array_filter([
            'DPoP' => Dpop::proof($key, 'POST', $url, nonce: $with, accessToken: $token),
            'Authorization' => $token === null ? null : 'DPoP '.$token,
        ]))->acceptJson()
            ->{$token === null ? 'asForm' : 'asJson'}()
            ->post($url, $body);

        $answer = $attempt($nonce);
        $offered = Dpop::nonceFrom($answer->headers());

        if ($offered !== null && $offered !== $nonce) {
            $this->rememberNonce($url, $offered);
        }

        if ($answer->json('error') !== 'use_dpop_nonce') {
            return $answer;
        }

        return $attempt($offered);
    }

    private function nonceFor(string $url): ?string
    {
        $held = cache()->get($this->nonceKey($url));

        return is_string($held) ? $held : null;
    }

    private function rememberNonce(string $url, string $nonce): void
    {
        // Shorter than the specification's five-minute ceiling, so a stale one
        // costs a retry rather than a failure.
        cache()->put($this->nonceKey($url), $nonce, now()->addMinutes(2));
    }

    private function nonceKey(string $url): string
    {
        return 'streetmesh:their-nonce:'.sha1((string) parse_url($url, PHP_URL_HOST));
    }

    private function assertion(string $issuer): string
    {
        $key = $this->identities->forServer()->key();

        if (! $key instanceof P256) {
            throw new RuntimeException('A client assertion has to be made on P-256.');
        }

        return ClientAssertion::for($this->clientId(), $issuer, $key);
    }

    private function clientId(): string
    {
        return route('streetmesh.client');
    }

    private function keep(Delegation $delegation, Response $granted): Delegation
    {
        $token = $granted->json('access_token');

        if (! is_string($token)) {
            throw new RuntimeException(
                'That server issued no token: '.(string) $granted->json('error_description', $granted->body())
            );
        }

        $delegation->update([
            'did' => $granted->json('sub') ?? $delegation->did,
            'access_token' => $token,
            'refresh_token' => $granted->json('refresh_token') ?? $delegation->refresh_token,
            'scope' => $granted->json('scope') ?? $delegation->scope,
            'expires_at' => now()->addSeconds((int) $granted->json('expires_in', 300)),

            /*
             * Both cleared the moment they are spent. The verifier has done its
             * work, and a state that still matches is a callback that could be
             * replayed.
             */
            'state' => null,
            'code_verifier' => null,
        ]);

        return $delegation->refresh();
    }

    private static function secret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }
}
