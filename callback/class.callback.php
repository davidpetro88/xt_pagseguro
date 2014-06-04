<?php
//
//ini_set('display_errors', 1);
//ini_set('display_startup_erros', 1);
//error_reporting(E_ALL);
/*
  #########################################################################
  #                       xt:Commerce  4.1 Shopsoftware
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  #
  # Copyright 2007-2011 xt:Commerce International Ltd. All Rights Reserved.
  # This file may not be redistributed in whole or significant part.
  # Content of this file is Protected By International Copyright Laws.
  #
  # ~~~~~~ xt:Commerce  4.1 Shopsoftware IS NOT FREE SOFTWARE ~~~~~~~
  #
  # http://www.xt-commerce.com
  #
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  #
  # @version 1
  # @copyright xt:Commerce International Ltd., www.xt-commerce.com
  #
  # ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  #
  # xt:Commerce International Ltd., Kafkasou 9, Aglantzia, CY-2112 Nicosia
  #
  # office@xt-commerce.com
  #
  #########################################################################
 */
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . "xt_pagseguro/PagSeguroLibrary/PagSeguroLibrary.php";


defined('_VALID_CALL') or die('Direct Access is not allowed.');

class callback_xt_pagseguro extends callback {

    // doc https://pagseguro.uol.com.br/v2/guia-de-integracao/api-de-notificacoes.html

    const STATUS_ORDER_PAYMENT_RECEIVED = 23;
    const STATUS_ORDER_PAYMENT_CANCELED = 32;

    var $version = '1.0';

    function process() {
        global $filter;


        //	if (!is_array($_POST)) return;

        $this->data = array();
        foreach ($_POST as $key => $val) {
            $this->data[$key] = $filter->_filter($val);
        }

        $log_data = array();
        $log_data['module'] = 'xt_pagseguro';
        $log_data['class'] = 'callback_data_post';
        $log_data['transaction_id'] = 'x';
        $log_data['callback_data'] = serialize($this->data);
        $this->_addLogEntry($log_data);

        $this->data = array();
        foreach ($_GET as $key => $val) {
            $this->data[$key] = $filter->_filter($val);
        }

        //VALIDA TRANSAÇÃO
        self::main();
    }

    function _callbackProcess() {
        
    }

    public static function main() {

        $code = (isset($_POST['notificationCode']) && trim($_POST['notificationCode']) !== "" ?
                        trim($_POST['notificationCode']) : null);
        $type = (isset($_POST['notificationType']) && trim($_POST['notificationType']) !== "" ?
                        trim($_POST['notificationType']) : null);

        if ($code && $type) {

            $notificationType = new PagSeguroNotificationType($type);
            $strType = $notificationType->getTypeFromValue();

            switch ($strType) {

                case 'TRANSACTION':
                    self::transactionNotification($code);
                    break;

                default:
                    LogPagSeguro::error("Unknown notification type [" . $notificationType->getValue() . "]");
            }

            self::printLog($strType);
        } else {

            LogPagSeguro::error("Invalid notification parameters.");
            self::printLog();
        }
    }

    private static function transactionNotification($notificationCode) {

        /*
         * #### Credentials #####
         * Substitute the parameters below with your credentials (e-mail and token)
         * You can also get your credentials from a config file. See an example:
         * $credentials = PagSeguroConfig::getAccountCredentials();
         */
        $credentials = new PagSeguroAccountCredentials(XT_PAGSEGURO_MERCHANT_MAIL, XT_PAGSEGURO_MERCHANT_TOKEN);

        try {
            $transaction = PagSeguroNotificationService::checkTransaction($credentials, $notificationCode);
            // Do something with $transaction
            //Retorna o objeto TransactionStatus, que vamos resgatar o valor do status
            $status = $transaction->getStatus();

            /**
             * Pegamos o código que passamos por referência para o pagseguro
             */
            $idPedido = $transaction->getReference();
            
            if ($status->getValue() == 3) {

                $comments = "Pedido {$idPedido} aprovado.";
                self::updateOrder(self::STATUS_ORDER_PAYMENT_RECEIVED, $idPedido, $comments, true, $notificationCode);

            } else if ($status->getValue() == 7) {

                self::updateOrder(self::STATUS_ORDER_PAYMENT_CANCELED, $idPedido, $comments, true, $notificationCode);
            }
        } catch (PagSeguroServiceException $e) {
            die($e->getMessage());
        }
    }

    private static function printLog($strType = null) {
        $count = 4;
        echo "<h2>Receive notifications</h2>";
        if ($strType) {
            echo "<h4>notifcationType: $strType</h4>";
        }
        echo "<p>Last <strong>$count</strong> items in <strong>log file:</strong></p><hr>";
        echo LogPagSeguro::getHtml($count);
    }

    private static function updateOrder($status, $oid, $comments, $customer_notified = true, $callback_id) {
        global $system_status, $db;

        $status_name = $system_status->values['order_status'][$status]['name'];

        $show_comments = true;
        $data_array = array();
        $data_array['orders_id'] = $oid;
        $data_array['orders_status_id'] = $status;
        $data_array['customer_notified'] = $customer_notified;
        $data_array['customer_show_comment'] = $show_comments;
        $data_array['comments'] = $comments;
        $data_array['change_trigger'] = 'cron';
        $data_array['callback_id'] = $callback_id;
        //$data_array['date_added']=$db->BindDate(time());


        $db->AutoExecute(TABLE_ORDERS_STATUS_HISTORY, $data_array, 'INSERT');

        $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . (int) $status . "', last_modified = now() where orders_id = '" . (int) $oid . "'");

        //$aorder = new order($oid, -1);
        $aorder = new order($oid);

        if (!empty($aorder->customer)) {

            if ($customer_notified == true) {
                $aorder->_sendStatusMail($status_name, $comments, array(), $status);
            }
        }
    }

}

