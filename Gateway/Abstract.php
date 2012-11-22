<?php
/**
 * Gateway Service
 *
 * @category   Service
 * @package    Service_Payment
 * @copyright  Copyright David Havl
 * @license    GNU
 * @author     David Havl <info@davidhavl.com>
 */

/**
 * @category   Service
 * @package    Service_Payment
 * @copyright  Copyright David Havl
 * @license    GNU
 * @version    1.1
 */
abstract class Br_Service_Payment_Gateway_Abstract extends Zend_Service_Abstract
{

    // Constants

    // General
    const TYPE = 'type';                // The transaction type
    const PRICE = 'price';              // The total of the transactions
    const TANGIBLE = 'tangible';        // Does this contain physical items
    const LANGUAGE = 'language';        // The language of the customer
    const REGION = 'region';            // The region of the customer
    const CURRENCY = 'currency';        // The currency of the transaction

    // URLs
    const RETURN_URL = 'return_url';    // URL to return to on success
    const CANCEL_URL = 'cancel_url';    // URL to return to on failure
    const IPN_URL = 'ipn_url';          // URL to send IPN to

    // Vendor
    const VENDOR_ID = 'vendor_id';      // The identity of the vendor (us)
    const VENDOR_ORDER_ID = 'vendor_order_id'; // Custom order id # for this order
    
    // Transaction
    const TRANSACTION_TOTAL = 'total';  // The total amount customer paid


    // Items
    const ITEM_COUNT = 'item_count';    // The number of items in the transaction
    const ITEMS = 'items';              // An array of items in this transaction

    // Products
    const PRODUCT_ID = 'product_id';    // The identity of the product
    const PRODUCT_TITLE = 'product_title';
    const PRODUCT_DESCRIPTION = 'product_description';
    const PRODUCT_QUANTITY = 'product_quantity';
    const PRODUCT_PRICE = 'product_price';
    
    // Product Options
    const PRODUCT_OPTION_TITLE = 'product_option_title';
    const PRODUCT_OPTION_VALUE = 'product_option_value';
    const PRODUCT_OPTION_SURCHARGE = 'product_option_surcharge';

    // Recurring/Subscription
    const RECURRENCE = 'recurrence';    // How often is this charged?
    const DURATION = 'duration';        // For how long is this charged?
    const STARTUP_FEE = 'startup_fee';  // Startup fee
    // Extra Options
    const TEST_MODE = 'test_mode';      // Testing
    const FIXED = 'fixed';              // Customer can't change items
    const SKIP_LANDING = 'skip_landing';// To skip showing order review page.

    // Customer
    const CUSTOMER_NAME = 'customer_name';
    const CUSTOMER_NAME_FIRST = 'customer_name_first';
    const CUSTOMER_NAME_MIDDLE = 'customer_name_middle';
    const CUSTOMER_NAME_LAST = 'customer_name_last';
    const CUSTOMER_ADDRESS = 'customer_address';
    const CUSTOMER_POSTCODE = 'customer_postcode';
    const CUSTOMER_CITY = 'customer_city';
    const CUSTOMER_COUNTRY = 'customer_country';
    const CUSTOMER_EMAIL = 'customer_email';

    
    private $_vendorId = ''; // for online transactions
    private $_vendorSecret = '';  // for online transactions
    private $_loginUsername = ''; // for logging in to gateway API or online interface
    private $_loginPassword = ''; // for logging in to gateway API or online interface
    private $_gatewayPaymentUrl = '';
    private $_format = 'json'; // 'json', 'xml'
    private $_accept = 'application/json'; // 'application/json', 'application/xml', 'text/html'

    private $_log = null;

    protected $_supportedCurrencies = array(
        'ARS', // Not supported by PayPal
        'AUD',
        'BRL',
        'CAD',
        'CHF',
        'CZK', // Supported by PayPal, but not by 2Checkout
        'DKK',
        'EUR',
        'GBP',
        'HUF', // Supported by PayPal, but not by 2Checkout
        'HKD',
        'MYR', // Supported by PayPal, but not by 2Checkout
        'ILS', // Supported by PayPal, but not by 2Checkout
        'INR', // Not supported by PayPal
        'JPY',
        'MXN',
        'NOK',
        'NZD',
        'PHP', // Supported by PayPal, but not by 2Checkout
        'PLN', // Supported by PayPal, but not by 2Checkout
        'SEK',
        'SGD', // Supported by PayPal, but not by 2Checkout
        'TWD', // Supported by PayPal, but not by 2Checkout
        'THB', // Supported by PayPal, but not by 2Checkout
        'USD',
        'ZAR', // Not supported by PayPal
    );

    protected $_supportedLanguages = array(
        'zh' => 'zh',
        'da' => 'da',
        'nl' => 'nl',
        'de' => 'gr',
        'el' => 'el',
        'it' => 'it',
        'ja' => 'jp',
        'nb' => 'no',
        'pt' => 'pt',
        'sl' => 'sl',
        'sv' => 'sv',
        'en' => 'en',
        'es' => 'es_ib',
        'es_419' => 'es_la', // Latin America
        'es_ES' => 'es_ib', // Iberia
    );
    
    public function __construct($vendorId, $vendorSecret, $loginUsername = null, $loginPassword = null)
    {
        $this->_vendorId = $vendorId;
        $this->_vendorSecret = $vendorSecret;
        
        if ($loginUsername && $loginPassword) {
            $this->_loginUsername = $loginUsername;
            $this->_loginPassword = $loginPassword;
        }
    }
    
    /**
     * get constant from instance. No need in PHP 5.3 
     */
    public function getConstant($constName)
    {
        return constant('self::' . $constName);
    }
        
    public function setVendorCredentials($vendorId, $secret)
    {
        $this->_vendorId = $vendorId;
        $this->_vendorSecret = $secret;
    }

    public function setLoginCredentials($username, $password)
    {
        $this->_loginUsername = $username;
        $this->_loginPassword = $password;
    }
    
    
    public function isSupportedCurrency($currency)
    {
        if( !is_string($currency) || '' == $currency ) {
            return false;
        }

        $currency = strtoupper($currency);

        if( isset($this->_supportedCurrencies[$currency]) ) {
            return true;
        } else if( in_array($currency, $this->_supportedCurrencies) ) {
            return true;
        }

        return false;
    }

    public function isSupportedLanguage($language)
    {
        if( !is_string($language) || '' == $language ) {
            return false;
        }

        list($shortLanguage) = explode('_', str_replace('-', '_', $language));

        if( isset($this->_supportedLanguages[$language]) ) {
            return true;
        } else if( in_array($language, $this->_supportedLanguages) ) {
            return true;
        } else if( isset($this->_supportedLanguages[$shortLanguage]) ) {
            return true;
        } else if( in_array($shortLanguage, $this->_supportedLanguages) ) {
            return true;
        }
        
        return false;
        
    }
    
    
    // GETTERS, SETTERS

    public function getVendorSecret() 
    {
        return $this->_vendorSecret;
    }

    public function getVendorId()
    {
        return $this->_vendorId;
    }

    public function setVendorSecret($secret) 
    {
        $this->_vendorSecret = $secret;
        return $this;
    }

    public function setVendorId($id) 
    {
        $this->_vendorId = $id;
        return $this;
    }

    public function getGatewayPaymentUrl() 
    {
        return $this->_gatewayPaymentUrl;
    }
    
    public function getSupportedCurrencies()
    {
        return $this->_supportedCurrencies;
    }

    
    

    
    // Log

    /**
    * @return Zend_Log
    */
    public function getLog()
    {
        if( null === $this->_log ) {
            if( !defined('APPLICATION_PATH') ) {
                throw new Exception('No log defined');
            } else {
                $writer = new Zend_Log_Writer_Stream(APPLICATION_PATH . '/temporary/log/payment.log');
                $this->_log = new Zend_Log($writer);
            }
        }
        return $this->_log;
    }

    public function setLog(Zend_Log $log)
    {
        $this->_log = $log;
        return $this;
    }
    
    public function log($message, $code = Zend_Log::INFO)
    {
        $this->getLog()->log($message, $code);
    }

    
    
    // Utility

    /**
    * Get the http client, set login and default parameters
    * @return Zend_Http_Client
    */
    protected function _getHttpClient()
    {
        return $this->getHttpClient()
                    ->resetParameters()
                    ->setAuth($this->_loginUsername, $this->_loginPassword)
                    ->setHeaders('Accept', $this->_accept);
    }

    /**
    * Check for correct params 
    * 
    * @param array $params
    * @param array $requiredParams
    * @param array $supportedParams
    * @return array 
    */
    protected function _checkCorrectParams(array $params, $requiredParams = null, $supportedParams = null)
    {
        // Check params
        if( !is_array($params) ) {
            if( !empty($params) ) {
                throw new Exception('Invalid data type of params');
            } else {
                $params = array();
            }
        }

        // Check required params
        if( is_string($requiredParams) ) {
            $requiredParams = array($requiredParams);
        } else if( !is_array($requiredParams) ) {
            $requiredParams = array();
        }

        // Check supported params
        if( is_string($supportedParams) ) {
            $supportedParams = array($supportedParams);
        } else if( !is_array($supportedParams) ) {
            $supportedParams = array();
        }

        // Nothing to do
        if( empty($requiredParams) && empty($supportedParams) ) {
            return array();
        }

        // Build full supported parrams array
        $supportedParams = array_unique(array_merge($supportedParams, $requiredParams));

        // Check supported
        if( count($params) > 0 && count($unsupportedParams = array_diff(array_keys($params), $supportedParams)) > 0 ) {
            $paramStr = '';
            foreach( $unsupportedParams as $unsupportedParam ) {
                if( $paramStr != '' ) {
                    $paramStr .= ', ';
                }
                $paramStr .= $unsupportedParam;
            }
            throw new Exception(sprintf('Unknown param(s): %1$s', $paramStr));
        }

        // Check required
        if( count($requiredParams) > 0 && count($missingRequired = array_diff($requiredParams, array_keys($params))) > 0 ) {
            $paramStr = '';
            foreach( $missingRequired as $missingRequiredParam ) {
                if( $paramStr != '' ) $paramStr .= ', ';
                $paramStr .= $missingRequiredParam;
            }
            throw new Exception(sprintf('Missing required param(s): %1$s', $paramStr));
        }

        return $params;
    }
    
    
    /**
     * Convert the response in to Array
     * 
     * @param Zend_Http_Response $response
     * @return array
     * @throws Zend_Service_Exception
     */
    protected function _convertHttpResponse(Zend_Http_Response $response) {
        // Hack for logging
        if ($this->_log instanceof Zend_Log) {
            $client = $this->getHttpClient();
            $this->_log->log(sprintf("Request:\n%s\nResponse:\n%s\n", $client->getLastRequest(), $client->getLastResponse()->asString()), Zend_Log::DEBUG);
        }

        // Check response body
        $responseData = $response->getBody();
        if (!is_string($responseData) || '' === $responseData) {
            throw new Exception('HTTP Client returned an empty response');
        }

        // These are only supported using json
        if ('json' === $this->_format) {

            // Decode response body
            $responseData = Zend_Json::decode($responseData, Zend_Json::TYPE_ARRAY);
            if (!is_array($responseData)) {
                throw new Exception('HTTP Client returned invalid JSON response');
            }

            // Check for special global error keys
            if (!empty($responseData['errors'])) {
                foreach ($responseData['errors'] as $message) {
                    throw new Exception(sprintf('API Error: [%1$s] %2$s', $message['code'], $message['message']), $message['code']);
                }
            }

            // Check for warnings
            if (!empty($responseData['warnings'])) {
                foreach ($responseData['warnings'] as $message) {
                    throw new Exception(sprintf('API Warning: [%1$s] %2$s', $message['code'], $message['message']), $message['code']);
                }
            }

            // Check for response status and message
            if ('OK' !== $responseData['response_code']) {
                throw new Exception(sprintf('Response Error: [%1$s] %2$s', $responseData['response_code'], $responseData['response_message']), $responseData['response_code']);
            }
        }

        // Check HTTP Status code
        if (200 !== $response->getStatus()) {
            throw new Exception(sprintf('HTTP Client returned error status: %1$d', $response->getStatus()), 'HTTP');
        }

        return $responseData;
    }
}