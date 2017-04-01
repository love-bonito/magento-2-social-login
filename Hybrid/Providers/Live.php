<?php

/* !
 * HybridAuth
 * http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
 * (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
 */

namespace Mageplaza\SocialLogin\Hybrid\Providers;

use Mageplaza\SocialLogin\Hybrid\Exception;
use Mageplaza\SocialLogin\Hybrid\Logger;
use Mageplaza\SocialLogin\Hybrid\ProviderModelOAuth2;
use Mageplaza\SocialLogin\Hybrid\UserContact;

/**
 * Windows Live OAuth2 Class
 *
 * @package             HybridAuth providers package
 * @author              Lukasz Koprowski <azram19@gmail.com>
 * @version             0.2
 * @license             BSD License
 */

/**
 * Live - Windows Live provider adapter based on OAuth2 protocol
 */
class Live extends ProviderModelOAuth2 {

    /**
     * {@inheritdoc}
     */
    public $scope = "wl.basic wl.contacts_emails wl.emails wl.signin wl.share wl.birthday";

    /**
     * {@inheritdoc}
     */
    function initialize() {
        parent::initialize();

        // Provider api end-points
        $this->api->api_base_url = 'https://apis.live.net/v5.0/';
        $this->api->authorize_url = 'https://login.live.com/oauth20_authorize.srf';
        $this->api->token_url = 'https://login.live.com/oauth20_token.srf';

        $this->api->curl_authenticate_method = "GET";

        // Override the redirect uri when it's set in the config parameters. This way we prevent
        // redirect uri mismatches when authenticating with Live.com
        if (isset($this->config['redirect_uri']) && !empty($this->config['redirect_uri'])) {
            $this->api->redirect_uri = $this->config['redirect_uri'];
        }
    }

    /**
     * {@inheritdoc}
     */
    function getUserProfile() {
        $data = $this->api->get("me");

        if (!isset($data->id)) {
            throw new Exception("User profile request failed! {$this->providerId} returned an invalid response: " . Logger::dumpData( $data ), 6);
        }

        $this->user->profile->identifier = (property_exists($data, 'id')) ? $data->id : "";
        $this->user->profile->firstName = (property_exists($data, 'first_name')) ? $data->first_name : "";
        $this->user->profile->lastName = (property_exists($data, 'last_name')) ? $data->last_name : "";
        $this->user->profile->displayName = (property_exists($data, 'name')) ? trim($data->name) : "";
        $this->user->profile->gender = (property_exists($data, 'gender')) ? $data->gender : "";

        //wl.basic
        $this->user->profile->profileURL = (property_exists($data, 'link')) ? $data->link : "";

        //wl.emails
        $this->user->profile->email = (property_exists($data, 'emails')) ? $data->emails->account : "";
        $this->user->profile->emailVerified = (property_exists($data, 'emails')) ? $data->emails->account : "";

        //wl.birthday
        $this->user->profile->birthDay = (property_exists($data, 'birth_day')) ? $data->birth_day : "";
        $this->user->profile->birthMonth = (property_exists($data, 'birth_month')) ? $data->birth_month : "";
        $this->user->profile->birthYear = (property_exists($data, 'birth_year')) ? $data->birth_year : "";

        return $this->user->profile;
    }

    /**
     * Windows Live api does not support retrieval of email addresses (only hashes :/)
     * {@inheritdoc}
     */
    function getUserContacts() {
        $response = $this->api->get('me/contacts');

        if ($this->api->http_code != 200) {
            throw new Exception('User contacts request failed! ' . $this->providerId . ' returned an error: ' . $this->errorMessageByStatus($this->api->http_code));
        }

        if (!isset($response->data) || ( isset($response->errcode) && $response->errcode != 0 )) {
            return array();
        }

        $contacts = array();

        foreach ($response->data as $item) {
            $uc = new UserContact();

            $uc->identifier = (property_exists($item, 'id')) ? $item->id : "";
            $uc->displayName = (property_exists($item, 'name')) ? $item->name : "";
            $uc->email = (property_exists($item, 'emails')) ? $item->emails->preferred : "";
            $contacts[] = $uc;
        }

        return $contacts;
    }

}