<?php

/*
 ************************************************************************
 PagSeguro Config File
 ************************************************************************
 */

$PagSeguroConfig = array();

//$PagSeguroConfig['environment'] = array();
//$PagSeguroConfig['environment'] = "production"; // production, sandbox
//$PagSeguroConfig['environment'] = "sandbox"; // production, 
$PagSeguroConfig['environment'] = "production"; // production, 

$PagSeguroConfig['credentials'] = array();
//$PagSeguroConfig['credentials']['email'] = "forttiori@forttiori.com.br";
$PagSeguroConfig['credentials']['email'] = XT_PAGSEGURO_MERCHANT_MAIL;
//


//$PagSeguroConfig['credentials']['token']['production'] = "your_production_pagseguro_token";
//$PagSeguroConfig['credentials']['token']['sandbox'] = "2A8C60457B08471D8122D7111C804BD3";
$PagSeguroConfig['credentials']['token']['sandbox'] = XT_PAGSEGURO_MERCHANT_TOKEN;

$PagSeguroConfig['application'] = array();
$PagSeguroConfig['application']['charset'] = "UTF-8"; // UTF-8, ISO-8859-1

$PagSeguroConfig['log'] = array();
$PagSeguroConfig['log']['active'] = TRUE;
$PagSeguroConfig['log']['fileLocation'] = _SRV_WEBROOT . _SRV_WEB_PLUGINS . "xt_pagseguro/log/log.txt";