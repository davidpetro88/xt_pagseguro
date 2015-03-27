<?php
/**
 * Created by David Petro
 * User: David Petro
 * Date: 04/06/2014
 * David Petro, david.abraao.petro@gmail.com
 */
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . "xt_pagseguro/PagSeguroLibrary/PagSeguroLibrary.php";
include_once _SRV_WEBROOT . 'xtFramework/library/phpxml/xml.php';

class xt_pagseguro {

    var $data = array();
    var $external = true;
    var $version = '1.0';
    var $subpayments = false;
    var $iframe = false;

    function __construct() {
        global $xtLink;

        $this->RETURN_URL = $xtLink->_link(array('page' => 'checkout', 'paction' => 'payment_process'));
        $this->CANCEL_URL = $xtLink->_link(array('page' => 'checkout', 'paction' => 'payment', 'params' => 'error=ERROR_PAYMENT'));
        $this->NOTIFY_URL = $xtLink->_link(array('page' => 'callback', 'paction' => 'xt_pagseguro'));
        $this->WS_URL = 'https://ws.pagseguro.uol.com.br/v2/checkout';
        $this->TARGET_URL = 'https://pagseguro.uol.com.br/v2/checkout/payment.html';
    }

    function build_payment_info($data) {
        
    }

    function pspRedirect($processed_data = array()) {
        global $xtLink, $filter, $db, $countries, $language;


        $orders_id = (int) $_SESSION['last_order_id'];

        $rs = $db->Execute("SELECT customers_id FROM " . TABLE_ORDERS . " WHERE orders_id='" . $orders_id . "'");

        $order = new order($orders_id, $rs->fields['customers_id']);


        $data = array();
        $data['currency'] = $order->order_data['currency_code'];

        $data['items'] = array();

        $products_value = 0;

        foreach ($order->order_products as $key => $arr) {
            $item['id'] = $arr['products_id'];
            $item['description'] = $arr['products_name'];
            $item['amount'] = number_format($arr['products_price']['plain'], 2);
            $products_value+=number_format($arr['products_price']['plain'], 2);
            $item['quantity'] = number_format($arr['products_quantity'], 0);
            $item['weight'] = '0';
            $data['items'][]['item'] = $item;
        }

        $products_value = round($products_value, 2);


        $data['reference'] = $orders_id;

        $sender = array();
        $sender['name'] = $order->order_data['delivery_firstname'] . ' ' . $order->order_data['delivery_lastname'];
        $sender['email'] = $order->order_data['customers_email_address'];

        $data['sender'] = $sender;



        $shipping = array();
        $shipping['street'] = $order->order_data['delivery_street_address'];
        // $shipping['number'] = '';
        $shipping['complement'] = '';
        $shipping['district'] = '';
        $shipping['postalCode'] = $order->order_data['delivery_postcode'];
        $shipping['city'] = $order->order_data['delivery_city'];
        if ($order->order_data['delivery_state'] != '')
            $shipping['state'] = $order->order_data['delivery_state'];

        if (!is_object($countries)) {
            $countries = new countries('true');
        }

        $country = $countries->_getCountryData($order->order_data['delivery_country_code']);

        $country_code = $country['countries_iso_code_3'];

        $shipping['country'] = $country_code;

        $data['shipping']['type'] = '1';
        $data['shipping']['address'] = $shipping;


        // put rest amount into shipping costs
        $shipping_costs = 0;
        // shipping
        foreach ($order->order_total_data as $key => $arr) {
            if ($arr['orders_total_key'] == 'shipping')
                $shipping_costs = $arr['orders_total_price']['plain'];
        }

        $data['shipping']['cost'] = number_format($shipping_costs, 2);
        $handling_costs = $order->order_total['total']['plain'] - $products_value - $shipping_costs;
        if ($handling_costs > 0) {
            $data['extraAmount'] = number_format($handling_costs, 2);
        }

        // products & Total
        $data['redirectURL'] = $this->RETURN_URL;
        $data['notificationURL'] = $this->NOTIFY_URL;



        $array = array();
        $array['checkout'] = $data;

        $paymentRequest = new PagSeguroPaymentRequest();

        // Sets the currency
        $paymentRequest->setCurrency($data['currency']);

        foreach ($data['items'] as $key => $item) {
            // Add an item for this payment request
            $paymentRequest->addItem($item['item']['id'], $item['item']['description'], $item['item']['quantity'], $item['item']['amount']);
        }

        // Sets a reference code for this payment request, it is useful to identify this payment in future notifications.
        $paymentRequest->setReference($data['reference']);

        // Sets shipping information for this payment request
        $paymentRequest->setShippingType(3);//frete 3
        // Sets shipping information for this payment request
        $paymentRequest->setShippingType(3);//frete 3
	    if (strlen ($data['shipping']['address']['postalCode']) == 8) {
        	$paymentRequest->setShippingAddress(
        	        $data['shipping']['address']['postalCode'], 
        	        $data['shipping']['address']['street'], 
        	        '', 
        	        $data['shipping']['address']['complement'], 
        	        '', 
        	        $data['shipping']['address']['city'], 
        	        '', 
        	        'BRA'
        	);
	    }

        $paymentRequest->setShippingCost($data['shipping']['cost']);
        
        
        // Sets your customer information.
        $paymentRequest->setSender(
                $data['sender']['name'], 
                $data['sender']['email'], 
                '', 
                '', 
                '', 
                ''
        );

        // Sets the url used by PagSeguro for redirect user after ends checkout process
        $paymentRequest->setRedirectUrl($data['redirectURL']);
        $paymentRequest->addParameter('redirectURL', $data['redirectURL']);


        // Add checkout metadata information
        // Another way to set checkout parameters
        $paymentRequest->addParameter('notificationURL', $data['notificationURL']);

        $credentials = new PagSeguroAccountCredentials(XT_PAGSEGURO_MERCHANT_MAIL, XT_PAGSEGURO_MERCHANT_TOKEN);

        // Register this payment request in PagSeguro, to obtain the payment URL for redirect your customer.
        $url = $paymentRequest->register($credentials);

        // set trans ID
        $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_data='" . $code . "' WHERE orders_id='" . $orders_id . "'");

        return $url;
    }

    function pspSuccess() {
        return true;
    }

    function _addCallbackLog($log_data) {
        global $db;
        if (is_array($log_data['callback_data']))
            $log_data['callback_data'] = serialize($log_data['callback_data']);
        if (is_array($log_data['error_data']))
            $log_data['error_data'] = serialize($log_data['error_data']);
        if ($log_data['error_data'] == '')
            $log_data['error_data'] = '';
        //$log_data['created'] =  $db->DBTimeStamp(time());
        $db->AutoExecute(TABLE_CALLBACK_LOG, $log_data, 'INSERT');
    }

}
