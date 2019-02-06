<?php
/**
 * Litego API v1 wrapper
 * https://litego.io/documentation/
 * https://github.com/litegoio/litego-php
 *
 * @version 1.1.0
 */

namespace Litego;

use WebSocket\Client;

class Litego {

    const LITEGO_MAINNET_URL = 'https://api.litego.io:9000';
    const LITEGO_TESTNET_URL = 'https://sandbox.litego.io:9000';

    const WS_LITEGO_MAINNET_URL = 'wss://api.litego.io:9000';
    const WS_LITEGO_TESTNET_URL = 'wss://sandbox.litego.io:9000';

    const LITEGO_MAINNET_MODE = "live";
    const LITEGO_TESTNET_MODE = "test";

    const AUTHENTICATE_API_URL                  = '/api/v1/merchant/authenticate';
    const REFRESHTOKEN_API_URL                  = '/api/v1/merchant/me/refresh-auth';
    const CHARGES_API_URL                       = '/api/v1/charges';
    const MERCHANT_API_URL                      = '/api/v1/merchant/me';
    const WITHDRAWAL_SET_API_URL                = '/api/v1/merchant/me/withdrawal/address';
    const WITHDRAWAL_TRIGGER_API_URL            = '/api/v1/merchant/me/withdrawal/manual';
    const WITHDRAWAL_LIST_API_URL               = '/api/v1/merchant/me/withdrawals';
    const WITHDRAWAL_SETTINGS_API_URL           = '/api/v1/merchant/withdrawal/settings';
    const WEBHOOK_SET_URL_API_URL               = '/api/v1/merchant/me/notification-url';
    const WEBHOOK_LIST_RESPONSES_API_URL        = '/api/v1/merchant/me/notification-responses';
    const WEBHOOK_LIST_REF_PAYMENTS_API_URL     = '/api/v1/merchant/me/referral-payments';
    const WS_SUBSCRIBE_PAYMENTS_API_URL         = '/api/v1/payments/subscribe';

    const TIMEOUT = 10;

    //codes
    const CODE_200 = 200;
    const CODE_400 = 400;

    /**
     * @var string
     */
    private $serviceUrl;

    /**
     * websocket payment url
     * @var string
     */
    private $wsServiceUrl;

    function __construct($mode = self::LITEGO_MAINNET_MODE) {
        if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            throw new \Exception("cURL support is required, but can't be found.");
        }

        $this->serviceUrl = self::LITEGO_MAINNET_URL;
        $this->wsServiceUrl = self::WS_LITEGO_MAINNET_URL;

        if ($mode == self::LITEGO_TESTNET_MODE) {
            $this->serviceUrl = self::LITEGO_TESTNET_URL;
            $this->wsServiceUrl = self::WS_LITEGO_TESTNET_URL;
        }
    }


    /**
     * Refresh auth_token with refresh_token, if failed (refresh_token is expired) try to refresh refresh_token with secret_key
     *
     * @param string    $refreshToken   Refresh token to refresh temporary auth key
     * @param string    $merchantId     Merchant ID
     * @param string    $secretKey      Secret Key
     * @param int       $timeout
     * @return array    Array of JWT authentication: auth_token and refresh_token
     * @throws Exception
     */
    public function reauthenticate($refreshToken = "", $merchantId = "", $secretKey = "",  $timeout = self::TIMEOUT) {

        if (!$refreshToken) {
            //request to get new auth and refresh token with secret key
            $result = $this->authenticate($merchantId, $secretKey, $timeout);

            if ($result['error']) {
                throw new Exception('Litego API: requestAuthenticate error');
            }

            return array(
                'auth_token' => $result['auth_token'],
                'refresh_token' => $result['refresh_token']
            );

        }
        else {
            //request for refresh auth token with refresh token
            $result = $this->refreshAuthToken($refreshToken, $timeout);

            if ($result['error'] && $result['error_name'] == "Forbidden") {
                //try to get new auth and refresh token with secret key
                return $this->reauthenticate("", $merchantId, $secretKey, $timeout);
            }

            if ($result['error']) {
                //for display static error page without redirect (look class GeneralException)
                throw new Exception('Litego API: requestRefreshAuthToken error');
            }

            return array(
                'auth_token' => $result['auth_token'],
                'refresh_token' => $refreshToken
            );
        }
    }


    /**
     * Calls to the API are authenticated with secret API Key and merchant API ID,
     * which you can find in your account settings on litego.io
     *
     * @link https://litego.io/documentation/?php#authentication
     *
     * @param $merchantId   Merchant ID
     * @param $secretKey    Secret key
     * @param int $timeout
     *
     * @return array
     */
    public function authenticate($merchantId, $secretKey, $timeout = self::TIMEOUT) {
        $data['merchant_id'] = $merchantId;
        $data['secret_key'] = $secretKey;

        $result = $this->doApiRequest(self::AUTHENTICATE_API_URL, 'POST', $data, array(), $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }

        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'auth_token' => $result['response_result']['auth_token'],
                'refresh_token' => $result['response_result']['refresh_token'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }

    /**
     * Refresh auth_token with refresh_token. Refresh_token is inserted into Authorization request header.
     * When auth_token lifetime is over, all other API requests return authorization error
     *
     * @link https://litego.io/documentation/?php#refresh-auth-token
     *
     * @param string    $refreshToken   Refresh token key (is returned with authentication)
     * @param int       $timeout        Request timeout
     *
     * @return array    Assoc array of decoded result
     */
    public function refreshAuthToken($refreshToken, $timeout = self::TIMEOUT) {
        //authorization headers
        $headers = array(
            "Authorization: Bearer " . $refreshToken
        );

        $result = $this->doApiRequest(self::REFRESHTOKEN_API_URL, 'PUT', $data = array(), $headers, $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }

        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'auth_token' => $result['response_result']['auth_token'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * Get information about authenticated merchant
     *
     * @link https://litego.io/documentation/?php#get-information-about-authenticated-merchant
     *
     * @param string    $authToken      Authentication key
     * @param int       $timeout
     *
     * @return array
     */
    public function getMerchant($authToken, $timeout = self::TIMEOUT) {
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        $result = $this->doApiRequest(self::MERCHANT_API_URL, 'GET', array(), $headers, $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }

        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'id' => $result['response_result']['id'],
                'name' => $result['response_result']['name'],
                'available_balance_satoshi' => $result['response_result']['available_balance_satoshi'],
                'pending_withdrawal_satoshi' => $result['response_result']['pending_withdrawal_satoshi'],
                'withdrawn_total_satoshi' => $result['response_result']['withdrawn_total_satoshi'],
                'withdrawal_address' => $result['response_result']['withdrawal_address'],
                'notification_url' => $result['response_result']['notification_url'],
                'object' => $result['response_result']['object'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * Create a new charge when a payment is required
     *
     * @link https://litego.io/documentation/?php#create-a-charge
     *
     * @param string    $authToken      Authentication key
     * @param string    $description    Charge description
     * @param int       $amount_satoshi Amount
     * @param int       $timeout
     *
     * @return array
     */
    public function createCharge($authToken, $description = "", $amount_satoshi = 0, $timeout = self::TIMEOUT) {
        //authentication headers
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        //prepare mandatory params for request
        $data = array(
            "description" => $description,
            "amount_satoshi" => $amount_satoshi
        );

        $result = $this->doApiRequest(self::CHARGES_API_URL, 'POST', $data, $headers, $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }

        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'id' => $result['response_result']['id'],
                'merchant_id' => $result['response_result']['merchant_id'],
                'description' => $result['response_result']['description'],
                'amount' => $result['response_result']['amount'],
                'amount_satoshi' => $result['response_result']['amount_satoshi'],
                'payment_request' => $result['response_result']['payment_request'],
                'paid' => $result['response_result']['paid'],
                'created' => $result['response_result']['created'],
                'expiry_seconds' => $result['response_result']['expiry_seconds'],
                'object' => $result['response_result']['object'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * List charges
     *
     * @link https://litego.io/documentation/?php#list-charges
     *
     * @param string    $authToken      Authentication key
     * @param array     $data           Filter parameters. 'page','pageSize','paidOnly'
     * @param int       $timeout
     *
     * @return array
     */
    public function chargesList($authToken, $data = array(), $timeout = self::TIMEOUT) {
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );


        $result = $this->doApiRequest(self::CHARGES_API_URL, 'GET', $data, $headers, $timeout);
        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }


        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'data' => $result['response_result']['data'],
                'page' => $result['response_result']['page'],
                'page_size' => $result['response_result']['page_size'],
                'object' => $result['response_result']['object'],
                'count' => $result['response_result']['count'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * Get a single charge by its id.
     *
     * @link https://litego.io/documentation/?php#get-a-single-charge
     *
     * @param string    $authToken      Authentication key
     * @param string    $chargeId       Charge ID
     * @param int       $timeout
     * @return array
     */
    public function getCharge($authToken, $chargeId, $timeout = self::TIMEOUT) {
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        $result = $this->doApiRequest(self::CHARGES_API_URL . "/" . $chargeId, 'GET', array(), $headers, $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }


        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'id' => $result['response_result']['id'],
                'merchant_id' => $result['response_result']['merchant_id'],
                'description' => $result['response_result']['description'],
                'amount' => $result['response_result']['amount'],
                'amount_satoshi' => $result['response_result']['amount_satoshi'],
                'payment_request' => $result['response_result']['payment_request'],
                'paid' => $result['response_result']['paid'],
                'created' => $result['response_result']['created'],
                'expiry_seconds' => $result['response_result']['expiry_seconds'],
                'object' => $result['response_result']['object'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * Merchant can set withdrawal address (or extended public key). When Xpub used, a new address will be derived
     * from this Xpub after each withdrawal. This prevents address reuse and improves privacy.
     *
     * @link https://litego.io/documentation/?php#set-withdrawal-address
     *
     * @param string    $authToken      Authentication key
     * @param string    $type           Charge description
     * @param int       $timeout
     * @return array
     */
    public function setWithdrawalAddress($authToken, $type = "", $value = "", $timeout = self::TIMEOUT) {
        //authentication headers
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        $data = array(
            'type' => $type,
            'value' => $value
        );

        $result = $this->doApiRequest(self::WITHDRAWAL_SET_API_URL, 'POST', $data, $headers, $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }

        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'type' => $result['response_result']['type'],
                'value' => isset($result['response_result']['value']) ? $result['response_result']['value'] : "",
                'xpub_key' => isset($result['response_result']['xpub_key']) ? $result['response_result']['xpub_key'] : "",
                'object' => $result['response_result']['object'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * You may trigger a withdrawal manually.
     *
     * @link https://litego.io/documentation/?php#manually-trigger-a-withdrawal
     *
     * @param string    $authToken      Authentication key
     * @param int       $timeout        Request timeout
     *
     * @return array    Assoc array of decoded result
     */
    public function triggerWithdrawal($authToken, $timeout = self::TIMEOUT) {
        //authorization headers
        $headers = array(
            "Authorization: Bearer " . $authToken
        );

        $result = $this->doApiRequest(self::WITHDRAWAL_TRIGGER_API_URL, 'PUT', $data = array(), $headers, $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }

        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'transaction_id' => $result['response_result']['transaction_id'],
                'merchantId' => $result['response_result']['merchantId'],
                'status' => $result['response_result']['status'],
                'total_amount' => $result['response_result']['total_amount'],
                'relative_fee' => $result['response_result']['relative_fee'],
                'manual_fee' => $result['response_result']['manual_fee'],
                'created_at' => $result['response_result']['created_at'],
                'status_changed_at' => $result['response_result']['status_changed_at'],
                'type' => $result['response_result']['type'],
                'object' => $result['response_result']['object'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * Withdrawal list
     *
     * @link https://litego.io/documentation/?php#list-withdrawals
     *
     * @param string    $authToken      Authentication key
     * @param array     $data           Filter parameters. 'page','size','status'(created/performed/confirmed)
     * @param int       $timeout
     *
     * @return array
     */
    public function withdrawalList($authToken, $data = array(), $timeout = self::TIMEOUT) {
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        $result = $this->doApiRequest(self::WITHDRAWAL_LIST_API_URL, 'GET', $data, $headers, $timeout);
        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }


        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'data' => $result['response_result']['data'],
                'page' => $result['response_result']['page'],
                'page_size' => $result['response_result']['page_size'],
                'object' => $result['response_result']['object'],
                'count' => $result['response_result']['count'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * Merchant can set withdrawal address (or extended public key). When Xpub used, a new address will be derived
     * from this Xpub after each withdrawal. This prevents address reuse and improves privacy.
     *
     * @link https://litego.io/documentation/?php#set-notification-url
     *
     * @param string    $authToken      Authentication key
     * @param string    $url            Webhook url
     * @param int       $timeout
     *
     * @return array
     */
    public function setNotificationUrl($authToken, $url = "", $timeout = self::TIMEOUT) {
        //authentication headers
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        $data = array(
            'url' => $url
        );

        $result = $this->doApiRequest(self::WEBHOOK_SET_URL_API_URL, 'POST', $data, $headers, $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }

        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'url' => $result['response_result']['url'],
                'object' => $result['response_result']['object'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }

    /**
     * List of webhook notifications
     *
     * @link https://litego.io/documentation/?php#webhooks
     *
     * @param string    $authToken      Authentication key
     * @param array     $data           Filter parameters. 'page','page_size','count'
     * @param int       $timeout
     *
     * @return array
     */
    public function listResponsesFromWebhook($authToken, $data = array(), $timeout = self::TIMEOUT) {
        //authentication headers
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        $result = $this->doApiRequest(self::WEBHOOK_LIST_RESPONSES_API_URL, 'GET', $data, $headers, $timeout);

        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }

        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'data' => $result['response_result']['data'],
                'page' => $result['response_result']['page'],
                'page_size' => $result['response_result']['page_size'],
                'object' => $result['response_result']['object'],
                'count' => $result['response_result']['count'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }

    /**
     * Get withdrawal settings
     *
     * @since 1.1.0
     *
     * @link https://litego.io/documentation/?php#get-withdrawal-settings
     *
     * @param string    $authToken      Authentication key
     * @param int       $timeout        timeout
     *
     * @return array
     */
    public function getWithdrawalSettings($authToken, $timeout = self::TIMEOUT) {
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        $result = $this->doApiRequest(self::WITHDRAWAL_SETTINGS_API_URL, 'GET', array(), $headers, $timeout);
        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }


        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'data' => $result['response_result']['data'],
                'page' => $result['response_result']['page'],
                'page_size' => $result['response_result']['page_size'],
                'object' => $result['response_result']['object'],
                'count' => $result['response_result']['count'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }

    /**
     * List referral payments
     *
     * @since 1.1.0
     *
     * @link https://litego.io/documentation/?php#list-referral-payments
     *
     * @param string    $authToken      Authentication key
     * @param array     $data           Filter parameters. 'page','size'
     *
     * @return array
     */
    public function listReferralPayments($authToken, $data = array(), $timeout = self::TIMEOUT) {
        $headers = array(
            'Authorization: Bearer ' . $authToken
        );

        $result = $this->doApiRequest(self::WEBHOOK_LIST_REF_PAYMENTS_API_URL, 'GET', $data, $headers, $timeout);
        if ($result['response_result']) {
            $result['response_result'] = json_decode($result['response_result'],1);
        }


        if ($result['response_code'] == self::CODE_200) {
            return array(
                'code' => self::CODE_200,
                'withdrawal_fee' => $result['response_result']['withdrawal_fee'],
                'withdrawal_manual_fee' => $result['response_result']['withdrawal_manual_fee'],
                'withdrawal_min_amount' => $result['response_result']['withdrawal_min_amount'],
                'error' => 0
            );
        }
        else {
            return array(
                'code' => $result['response_code'] ? $result['response_code'] : self::CODE_400,
                'error' => 1,
                'error_name' => $result['response_result']['name'],
                'error_message' => $result['response_result']['detail'],
            );
        }
    }


    /**
     * Subscribe payments
     *
     * You may subscribe to topic with payments of all your charges. Subscription requires authentication.
     * You need to pass auth token in header to authorize.
     *
     * @since 1.1.0
     * @link https://litego.io/documentation/?php#subscribe-payments
     *
     * @param string    $authToken      Authentication key
     * @param int       $timeout        Websocket timeout
     *
     * @return string   json format
     */
    public function subscribePayments($authToken, $timeout = self::TIMEOUT) {
        $client = new Client($this->wsServiceUrl . self::WS_SUBSCRIBE_PAYMENTS_API_URL,
            array(
                "headers" => array(
                    "authorization" => "Bearer " . $authToken
                ),
                "timeout" =>$timeout
            )
        );
        $client->send("");
        $result_string = "";
        while ($result_string == "") {
            try {
                $result_string = trim($client->receive());
            } catch (Exception $e) {
                throw new Exception('Litego API: ' . $e->getCode() . " : " . $e->getMessage());
            }
        }

        return $result_string;

    }


    /**
     * Subscribe single payment
     *
     * You may subscribe to topic with payments of all your charges. Subscription requires authentication.
     * You need to pass auth token in header to authorize.
     *
     * @since 1.1.0
     *
     * @link https://litego.io/documentation/?php#subscribe-payment-of-single-charge
     *
     * @param string    $authToken      Authentication key
     * @param string    $id             Invoice/charge ID
     * @param int       $timeout        Websocket timeout
     *
     * @return string   json format
     */
    public function subscribeSinglePayment($authToken, $id, $timeout = self::TIMEOUT) {

        $client = new Client($this->wsServiceUrl . self::WS_SUBSCRIBE_PAYMENTS_API_URL . "/" . $id,
            array(
                "headers" => array(
                    "authorization" => "Bearer " . $authToken
                ),
                "timeout" =>$timeout
            )
        );

        $client->send("");
        $result_string = "";
        while ($result_string == "") {
            try {
                $result_string = trim($client->receive());
            } catch (Exception $e) {
                throw new Exception('Litego API: ' . $e->getCode() . " : " . $e->getMessage());
            }
        }

        return $result_string;
    }


    /**
     * Perform the HTTP request.
     *
     * @param $apiPart      API method to be called
     * @param $method       HTTP method to use: get, post
     * @param array $data   Assoc array of parameters to be passed
     * @param int $timeout  Request timeout
     *
     * @return array        Result array
     */
    private function doApiRequest($apiPart, $method, $data = array(), $headers = array(), $timeout = self::TIMEOUT) {
        try {
            //API Service url to be called
            $url = $this->serviceUrl . $apiPart;

            //default header options
            $httpHeaders = array(
                'Content-Type: application/json'
            );

            if (is_array($headers) && count($headers) > 0) {
                $httpHeaders = array_merge($httpHeaders, $headers);
            }

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);

            switch ($method) {
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, true);
                    $this->attachRequestPayload($ch, $data);
                    break;
                case 'GET':
                    $query = http_build_query($data, '', '&');
                    curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
                    break;
                case 'PUT':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    $this->attachRequestPayload($ch, $data);
                    break;
            }


            $response = curl_exec($ch);

            $responseCode           = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseHeaderSize     = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $responseContent        = substr($response, $responseHeaderSize);

            return array(
                'response_code' => $responseCode,
                'response_result' => $responseContent
            );

            curl_close($ch);

        } catch(Exception $e) {
            return array(
                'response_code' => $e->getCode(),
                'response_error' => $e->getMessage()
            );
        }
    }


    /**
     * Encode the data and attach it to the request
     *
     * @param   resource $ch   cURL session handle, used by reference
     * @param   array    $data Assoc array of data to attach
     */
    private function attachRequestPayload(&$ch, $data) {
        $encoded = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }
}
