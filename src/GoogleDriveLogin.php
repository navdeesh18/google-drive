<?php

namespace Nkcx\GoogleDriveApi;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Oauth2;

class GoogleDriveLogin
{
	protected $client;

	function __construct()
    {
        $client = new Google_Client();
        $client->setAuthConfig([
            'web' => config('services.google')
        ]);
        $client->setScopes([Google_Service_Oauth2::USERINFO_EMAIL, Google_Service_Oauth2::USERINFO_PROFILE, Google_Service_Sheets::DRIVE, Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $this->client = $client;
    }

    public function getUrl()
    {
        // $accessToken = [
        //     'access_token' => auth()->user()->google_tokens['token'],
        //     'created' => auth()->user()->created_at->timestamp,
        //     'expires_in' => auth()->user()->drivegoogle_tokens['expires_in'],
        //     'refresh_token' => auth()->user()->google_tokens['refresh_token']
        // ];
        // $client->setAccessToken($accessToken);

        // Request authorization from the user.
        $authUrl = $this->client->createAuthUrl();
        return $authUrl;
    }

    public function login($authCode)
    {
        // Exchange authorization code for an access token.
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        if (array_key_exists('error', $accessToken)) {
            throw new Exception(join(', ', $accessToken));
        }
        $this->client->setAccessToken($accessToken);

        $oauth2 = new Google_Service_Oauth2($this->client);
        $userInfo = $oauth2->userinfo->get();
        return [$accessToken,$userInfo];
    }
}