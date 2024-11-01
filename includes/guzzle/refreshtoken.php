<?php

namespace WodenEvents\Includes\Guzzle;

use Psr\Http\Message\RequestInterface;

class RefreshToken {
    private $guzzle;

    private $token;

    public function __construct(\GuzzleHttp\Client $client) {
        $this->guzzle = $client;
        $this->token = [];
    }

    public function __invoke(callable $next) {
        return function (RequestInterface $request, array $options = []) use ($next) {
            $request = $this->applyToken($request);
            return $next($request, $options);
        };
    }

    protected function applyToken(RequestInterface $request) {
        if (! $this->hasValidToken()) {
            $this->acquireAccessToken();
        }

        return \GuzzleHttp\Psr7\modify_request($request, [
            'set_headers' => [
                'Authorization' => 'Bearer ' . $this->getToken()['idToken'],
            ],
        ]);
    }

    private function hasValidToken() {
        return (!empty($this->token['idToken'])
            && !$this->isRefreshableToken()
            && !empty( get_option( 'wodenevents_firestore_user_id' ) ) //We force the refresh to get the user_id
        );
    }

    private function acquireAccessToken() {
        $parameters = $this->getTokenRequestParameters();

        $response = $this->guzzle->request('POST', WODEN_EVENTS_REFRESH_ENDPOINT, [
            'form_params' => $parameters,
            // We'll use the default handler so we don't rerun our middleware
            'handler' => \GuzzleHttp\choose_handler(),
        ]);

        if ( ! $response->getStatusCode() === 200 ) {
            return;
        }

        $response = \GuzzleHttp\json_decode((string) $response->getBody(), true);

        $this->token['idToken'] = isset($response['id_token']) ? $response['id_token'] : '';
        $this->token['refreshToken'] = isset($response['refresh_token']) ? $response['refresh_token']: '';
        $this->token['expiresIn'] = isset($response['expires_in']) ? $response['expires_in'] : '';
        $this->token['userId'] = isset($response['user_id']) ? $response['user_id'] : '';

        update_option( 'wodenevents_firestore_id', $this->token['idToken']);
        update_option( 'wodenevents_firestore_refresh', $this->token['refreshToken']);
        update_option( 'wodenevents_firestore_expires_in', $this->token['expiresIn']);
        update_option( 'wodenevents_firestore_user_id', $this->token['userId']);
    }

    private function getTokenRequestParameters()
    {
        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => get_option( 'wodenevents_firestore_refresh' )
        ];
    }

    public function getToken() {
        return $this->token;
    }

    public function isRefreshableToken() {
        if (!$this->token['expiresIn']) {
            return false;
        }

        return ((( intval( get_option( 'wodenevents_firestore_expires_in' ) ) + $this->token['expiresIn']) - time()) < 60);
    }

    public static function resetFirestoreAuth()
    {
        update_option( 'wodenevents_firestore_api_key', '' );
        update_option( 'wodenevents_firestore_id', '' );
        update_option( 'wodenevents_firestore_refresh', '' );
        update_option( 'wodenevents_firestore_expires_in', '' );

        return true;
    }
}