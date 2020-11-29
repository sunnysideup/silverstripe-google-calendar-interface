<?php

/*
    see:
    https://developers.google.com/calendar/quickstart/php
    https://github.com/bpineda/google-calendar-connect-class/blob/master/src/Google/Calendar/GoogleCalendarClient.php
 */

class GoogleInterface extends Google_Client
{

    /**
     * @var String
     */
    private static $application_name = "";

    /**
     * @var String
     */
    private static $credentials_path = "";


    /**
     * @var String
     */
    private static $client_secret_path = "";


    /**
     * @var String
     */
    private static $client_access_type = "offline";


    /**
     * @var String
     */
    private static $time_zone = "Pacific/Auckland";

    protected $scopes;

    /**
     * Constructor for the class. We call the parent constructor, set
     * scopes array and service calendar instance that we will use
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Class configurator
     * @param null $verification_code Verification code we will use
     * to create our authentication credentials
     * @return bool
     */
    public function config($verification_code = null)
    {
        $base_folder = Director::baseFolder().'/';
        $this->setApplicationName(
            Config::inst()->get('GoogleCalendarInterface', 'application_name')
        );
        $this->setScopes($this->scopes);
        $this->setAuthConfigFile(
            $base_folder . Config::inst()->get('GoogleCalendarInterface', 'client_secret_path')
        );
        $this->setAccessType(
            Config::inst()->get('GoogleCalendarInterface', 'client_access_type')
        );
        $this->setApprovalPrompt('force');


        $credential_file = $base_folder . Config::inst()->get('GoogleCalendarInterface', 'credentials_path');
        $accessToken = [];

        if (file_exists($credential_file)) {
            $accessToken = json_decode(file_get_contents($credential_file), 1);
        }

        if (! file_exists($credential_file) || isset($accessToken['error'])) {
            if (empty($verification_code)) {
                return false;
            }
            $accessToken = $this->fetchAccessTokenWithAuthCode($verification_code);
            if (!is_null($accessToken)) {
                file_put_contents($credential_file, json_encode($accessToken));
            }
            if (isset($accessToken['error'])) {
                return false;
            }
        }

        $this->setAccessToken($accessToken);
        // Refresh the token if it's expired.
        if ($this->isAccessTokenExpired()) {

            // save refresh token to some variable
            $refreshTokenSaved = $this->getRefreshToken();

            // update access token
            $this->fetchAccessTokenWithRefreshToken($refreshTokenSaved);

            // pass access token to some variable
            $accessTokenUpdated = $this->getAccessToken();

            // append refresh token
            $accessTokenUpdated['refresh_token'] = $refreshTokenSaved;

            //Set the new acces token
            $accessToken = $refreshTokenSaved;
            $this->setAccessToken($accessToken);

            if (!is_null($accessTokenUpdated)) {
                file_put_contents($credential_file, json_encode($accessTokenUpdated));
            }
        }
        return true;
    }

    /**
     * Get error message string
     * @return html
     */
    public function getAuthLink()
    {
        $authUrl = $this->createAuthUrl();
        return '<a href="' . $authUrl . '" target="_blank">Retrieve Verification Code</a>';
    }
}

