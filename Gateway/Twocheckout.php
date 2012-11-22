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
class Br_Service_Payment_Twocheckout extends Br_Service_Payment_Gateway_Abstract 
{
    private $_gatewayPaymentUrl = 'https://www.2checkout.com/checkout/spurchase?';//'http://dev1.dancesport.co.uk/order/payment/test';
    private $_gatewayPaymentMultiPageUrl = 'https://www.2checkout.com/checkout/purchase?';

    protected $_supportedCurrencies = array(
        'ARS', // Not supported by PayPal
        'AUD',
        'BRL',
        'CAD',
        'CHF',
        //'CZK', // Supported by PayPal, but not by 2Checkout
        'DKK',
        'EUR',
        'GBP',
        //'HUF', // Supported by PayPal, but not by 2Checkout
        'HKD',
        //'MYR', // Supported by PayPal, but not by 2Checkout
        //'ILS', // Supported by PayPal, but not by 2Checkout
        'INR', // Not supported by PayPal
        'JPY',
        'MXN',
        'NOK',
        'NZD',
        //'PHP', // Supported by PayPal, but not by 2Checkout
        //'PLN', // Supported by PayPal, but not by 2Checkout
        'SEK',
        //'SGD', // Supported by PayPal, but not by 2Checkout
        //'TWD', // Supported by PayPal, but not by 2Checkout
        //'THB', // Supported by PayPal, but not by 2Checkout
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
    

    /**
     * Validate returned data from getaway
     * @param array $params (request data from gateway)
     * @return boolean 
     */
    public function validatePaymentReturn(array $params) 
    {
        $givenHash = strtolower($params['key']);
        $expectedHash = strtolower(md5(
                        $this->getVendorSecret() .
                        $this->getVendorIdentity() . // $params['sid']
                        $params['order_number'] .
                        $params['total']
                ));

        if ($givenHash !== $expectedHash) {
            return false;
        }

        return true;
    }

    
    
    // 2CO API CALLS
    
    /**
    * Used to get a list of past vendor payments
    * @link http://www.2checkout.com/documentation/api/acct-list_payments/
    * @return array 
    */
    public function listPayments()
    {
        $client = $this->_getHttpClient();
        $client->setUri('https://www.2checkout.com/api/acct/list_payments')
               ->setMethod(Zend_Http_Client::GET);

        $response = $client->request();
        $responseData = $this->_convertHttpResponse($response);

        if( is_array($responseData) ) {
            return $responseData['payments'];
        } else {
            return $responseData;
        }
    }

    /**
    * Used to retrieve a summary of all sales or only those matching a variety
    * of sale attributes.
    * @link http://www.2checkout.com/documentation/api/sales-list_sales/
    * @param array $params
    * @return array
    */
    public function listSales(array $params = array())
    {
        // Check params
        $params = $this->_checkCorrectParams($params, null, array(
        'sale_id', 'invoice_id', 'customer_name', 'customer_email',
        'customer_phone', 'vendor_product_id', 'ccard_first6', 'ccard_last2',
        'sale_date_begin', 'sale_date_end', 'declined_recurrings',
        'active_recurrings', 'refunded', 'cur_page', 'pagesize', 'sort_col',
        'sort_dir',
        ));

        // Send request
        $client = $this->_getHttpClient();
        $client->setUri('https://www.2checkout.com/api/sales/list_sales')
               ->setMethod(Zend_Http_Client::GET)
               ->setParameterGet($params);

        // Process response
        $response = $client->request();
        try {
            $responseData = $this->_convertHttpResponse($response);
        } catch( Exception $e ) {
            if( $e->getCode() === 203 ) { // if no records
                return array();
            } else {
                throw $e;
            }
        }

        if( is_array($responseData) ) {
            return $responseData['sale_summary'];
        } else {
            return $responseData;
        }
    }


    /**
    * Used to retrieve information about a specific sale.
    * @link http://www.2checkout.com/documentation/api/sales-detail_sale/
    * @param mixed $saleId
    * @return array
    */
    public function detailSale($saleId)
    {
        // Build params
        if( is_array($saleId) ) {
            $params = $saleId;
        } else {
            $params = array();
            $params['sale_id'] = $saleId;
        }

        // Check params
        $params = $this->_checkCorrectParams($params, 'sale_id');

        // Send request
        $client = $this->_getHttpClient();
        $client->setUri('https://www.2checkout.com/api/sales/detail_sale')
               ->setMethod(Zend_Http_Client::GET)
               ->setParameterGet($params);

        // Process response
        $response = $client->request();
        $responseData = $this->_convertHttpResponse($response);

        if( is_array($responseData) ) {
            return $responseData['sale'];
        } else {
            return $responseData;
        }
    }


    /**
    * Used to add a comment to a specified sale.
    * @link http://www.2checkout.com/documentation/api/sales-create_comment/
    * @param mixed   $saleId       The order number/sale ID of a sale to look
    *                              for. Required.
    * @param string  $saleComment  String value of comment to be submitted.
    *                              Required.
    * @param boolean $ccVendor     Set to 1 to have a copy sent to the vendor.
    *                              Optional.
    * @param boolean $ccCustomer   Set to 1 to have the customer sent an email
    *                              copy. Optional.
    */
    public function createComment($saleId, $saleComment = null, $ccVendor = null, $ccCustomer = null)
    {
        // Build params
        if( is_array($saleId) ) {
            $params = $saleId;
        } else {
            $params = array();
            $params['sale_id'] = $saleId;
            if( null !== $saleComment ) {
                $params['sale_comment'] = $saleComment;
            }
            if( null !== $ccVendor ) {
                $params['cc_vendor'] = $ccVendor;
            }
            if( null !== $ccCustomer ) {
                $params['cc_customer'] = $ccCustomer;
            }
        }

        // Check params
        $params = $this->_checkCorrectParams($params, array('sale_id', 'sale_comment'), array('cc_vendor', 'cc_customer'));

        // Send request
        $client = $this->_getHttpClient();
        $client->setUri('https://www.2checkout.com/api/sales/create_comment')
               ->setMethod(Zend_Http_Client::POST)
               ->setParameterPost($params);

        // Process response
        $response = $client->request();
        $responseData = $this->_convertHttpResponse($response);

        // no exceptions so return true
        return true;
    }
    
    
    /**
     * Used to retrieve the details for a single product.
     * @link http://www.2checkout.com/documentation/api/products-detail_product/
     * @param mixed $productId ID of product to retrieve details for. Required.
     * @return array
     */
    public function getProductDetails($productId) {
        // Build params
        if (is_array($productId)) {
            $params = $productId;
        } else {
            $params = array();
            $params['product_id'] = $productId;
        }

        // Check params
        $params = $this->_checkCorrectParams($params, 'product_id');

        // Send request
        $client = $this->_getHttpClient();
        $client->setUri('https://www.2checkout.com/api/products/detail_product')
               ->setMethod(Zend_Http_Client::GET)
               ->setParameterGet($params);

        // Process response
        $response = $client->request();
        $responseData = $this->_convertHttpResponse($response);

        if (is_array($responseData)) {
            return $responseData['product'];
        } else {
            return $responseData;
        }
    }

    /**
     * Gets product details by vendor product id
     * @param string $vendorProductId
     * @return array
     */
    public function getVendorProductDetails($vendorProductId) {
        if (!is_array($vendorProductId)) {
            $vendorProductId = array(
                'vendor_product_id' => $vendorProductId,
            );
        }

        $productList = $this->listProducts($vendorProductId);

        if (empty($productList['products'])) {
            return false;
        } else if (count($productList['products']) > 1) {
            return false; // Too many!
        }

        $productInfo = array_shift($productList['products']);

        return $productInfo;
    }

    /**
     * Used to retrieve list of all products in account.
     * @link http://www.2checkout.com/documentation/api/products-list_products/
     * @param array $params
     * @return array
     */
    public function listProducts(array $params = array()) {
        // Check params
        $params = $this->_checkCorrectParams($params, null, array(
            // These seem to not be working
            //'2COID', 'product_id', 'product_name',

            'vendor_product_id',
            'cur_page', 'pagesize', 'sort_col', 'sort_dir',
                ));

        // Send request
        $client = $this->_getHttpClient();
        $client
                ->setUri('https://www.2checkout.com/api/products/list_products')
                ->setMethod(Zend_Http_Client::GET)
                ->setParameterGet($params)
        ;

        // Process response
        $response = $client->request();
        $responseData = $this->_convertHttpResponse($response);

        return $responseData;
    }

    
    /**
     * Build transaction Url (for browser rediect to payment gateway).
     * @param array $params
     * @param string $singlePage single or multi page checkout
     * @link  https://www.2checkout.com/blog/knowledge-base/merchants/tech-support/3rd-party-carts/parameter-sets/pass-through-product-parameter-set/
     * @return string
     * @throws Exception 
     */
    public function buildTransactionUrl($params = array(), $singlePage = true) 
    {
        $url = '';
        if ($singlePage) {
            $url = $this->_gatewayPaymentUrl;
        } else {
            $url = $this->_gatewayPaymentMultiPageUrl;
        }
        
        if (!empty($params)) {


            // Add vendor identity
            $data['sid'] = $this->getVendorId();
            // parameter set
            $data['mode'] = '2CO';
            
            // Add product/s
            if( isset($params['vendor_product_id']) ) {
                $productInfo = $this->getVendorProductDetails($params['vendor_product_id']);
                $data['product_id'] = $productInfo['assigned_product_id'];
            } else if( isset($params[self::PRODUCT_ID]) ) {
                $productInfo = $this->getProductDetails($params[self::PRODUCT_ID]);
                $data['product_id'] = $productInfo['assigned_product_id'];
            } else if (isset($params['products']) && is_array($params['products'])) {
                $index = 0;
                foreach ($params['products'] as $product) {
                    // type 
                    if (!empty($product[self::TYPE])) {
                        $data['li_' . $index . '_type'] = $product[self::TYPE];
                    }
                    // title
                    if (!empty($product[self::PRODUCT_TITLE])) {
                        $data['li_' . $index . '_name'] = $product[self::PRODUCT_TITLE];
                    }
                    // description
                    if (isset($product[self::PRODUCT_DESCRIPTION])) {
                        $data['li_' . $index . '_description'] = $product[self::PRODUCT_DESCRIPTION];
                    }
                    // product id
                    if (!empty($product[self::PRODUCT_ID])) {
                        $data['li_' . $index . '_product_id'] = $product[self::PRODUCT_ID];
                    }
                    // quantity
                    if (isset($product[self::PRODUCT_QUANTITY])) {
                        $data['li_' . $index . '_quantity'] = $product[self::PRODUCT_QUANTITY];
                    }
                    // price
                    if (isset($product[self::PRODUCT_PRICE])) {
                        $data['li_' . $index . '_price'] = $product[self::PRODUCT_PRICE];
                    }
                    // tangible
                    if (!empty($product[self::TANGIBLE])) {
                        $data['li_' . $index . '_tangible'] = $product[self::TANGIBLE];
                    } else {
                        $data['li_' . $index . '_tangible'] = 'N';
                    }
                    // recurrence
                    if (isset($product[self::RECURRENCE])) {
                        $data['li_' . $index . '_recurrence'] = $product[self::RECURRENCE];
                    }
                    // duration of recurrence
                    if (isset($product[self::RECURRENCE]) && isset($product[self::DURATION])) {
                        $data['li_' . $index . '_duration'] = $product[self::DURATION];
                    }
                    // startup fee
                    if (isset($product[self::STARTUP_FEE]) && isset($product[self::STARTUP_FEE])) {
                        $data['li_' . $index . '_startup_fee'] = $product[self::STARTUP_FEE];
                    }
                    // options
                    if (isset($product['options']) && is_array($product['options'])) {
                        $optionIndex = 0;
                        foreach ($product['options'] as $option) {
                            // title
                            if (!empty($option[self::PRODUCT_OPTION_TITLE])) {
                                $data['li_' . $index . '_option_' . $optionIndex . '_name'] = $option[self::PRODUCT_OPTION_TITLE];
                            }
                            // value
                            if (!empty($option[self::PRODUCT_OPTION_VALUE])) {
                                $data['li_' . $index . '_option_' . $optionIndex . '_value'] = $option[self::PRODUCT_OPTION_VALUE];
                            }
                            // surcharge
                            if (!empty($option[self::PRODUCT_OPTION_SURCHARGE])) {
                                $data['li_' . $index . '_option_' . $optionIndex . '_surcharge'] = $option[self::PRODUCT_OPTION_SURCHARGE];
                            }
                            $optionIndex++;
                        }
                    }
                    $index++;
                }
            }

            // Add test mode. Rather do via account setting
            //if( $this->getTestMode() ) {
            //    $data['demo'] = 'Y';
            //}


            // Add language
            if( isset($params[self::LANGUAGE]) && ($language = $this->isSupportedLanguage($params[self::LANGUAGE]))) {
                $data['lang'] = $language;
            }

            // Add currency - 2 checkout does not support it
            //if( !empty($params['currency']) && ($currency = $this->isSupportedCurrency($params['currency'])) ||
            //     ($currency = $this->getCurrency()) ) {
            //}

            // Add return_url
            if( isset($params[self::RETURN_URL]) ) {
                $data['x_receipt_link_url'] = $params[self::RETURN_URL];
            }

            // Add merchant_order_id
            if( isset($params[self::VENDOR_ORDER_ID]) ) {
                if( strlen($params[self::VENDOR_ORDER_ID]) > 50 ) {
                    throw new Exception('Merchant Order ID cannot be longer than 50 character.');
                }
                $data['merchant_order_id'] = $params[self::VENDOR_ORDER_ID];
            }

            // Add pay_method
            if( isset($params['payment_method']) ) {
                if( in_array($params['payment_method'], array('CC', 'PPI')) ) {
                    $data['payment_method'] = $params['payment_method'];
                }
            }

            // Add skip_landing
            if( !empty($params['skip_landing']) ) {
                $data['skip_landing'] = 1;
            }
            
            
            // Add customer data if present
            if( !empty($params[self::CUSTOMER_NAME]) ) {
                if( strlen($params[self::CUSTOMER_NAME]) < 128 ) {
                    $data['card_holder_name'] = $params[self::CUSTOMER_NAME];
                }
            }
            if( !empty($params[self::CUSTOMER_ADDRESS]) ) {
                if( strlen($params[self::CUSTOMER_ADDRESS]) < 64 ) {
                    $data['street_address'] = $params[self::CUSTOMER_ADDRESS];
                }
            }
            if( !empty($params[self::CUSTOMER_CITY]) ) {
                if( strlen($params[self::CUSTOMER_CITY]) < 64 ) {
                    $data['city'] = $params[self::CUSTOMER_CITY];
                }
            }
            if( !empty($params[self::CUSTOMER_COUNTRY]) ) {
                if( strlen($params[self::CUSTOMER_COUNTRY]) < 64 ) {
                    $data['country'] = $params[self::CUSTOMER_COUNTRY];
                }
            }
            if( !empty($params[self::CUSTOMER_POSTCODE]) ) {
                if( strlen($params[self::CUSTOMER_POSTCODE]) < 16 ) {
                    $data['zip'] = $params[self::CUSTOMER_POSTCODE];
                }
            }
            if( !empty($params[self::CUSTOMER_EMAIL]) ) {
                if( strlen($params[self::CUSTOMER_EMAIL]) < 64 ) {
                    $data['email'] = $params[self::CUSTOMER_EMAIL];
                }
            }
            
        }
        
        $url .= '?' . http_build_query($data, '', '&');
        
        return $url;
    }

    // GETTERS, SETTERS
    

    
    
    // LOG

    /**
    * @return Zend_Log
    */
    public function getLog()
    {
        if( null === $this->log ) {
            if( !defined('APPLICATION_PATH') ) {
                throw new Exception('No log defined');
            } else {
                $writer = new Zend_Log_Writer_Stream(APPLICATION_PATH . '/temporary/log/2co-payment.log');
                $this->log = new Zend_Log($writer);
            }
        }
        return $this->log;
    }


}

