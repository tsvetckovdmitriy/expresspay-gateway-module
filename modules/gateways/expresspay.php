<?php
/**
 * @package       ExpressPay Payment Module for WHMCS
 * @author        ООО "ТриИнком" <info@express-pay.by>
 * @copyright     (c) 2022 Экспресс Платежи. Все права защищены.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/expresspay/gateway.php';
require_once __DIR__ . '/expresspay/log.php';

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @return array
 */
function expresspay_MetaData()
{
    return array(
        'DisplayName' => 'ExpressPay',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function expresspay_config()
{
    
    // Detect module name from filename.
    $gatewayModuleName = basename(__FILE__, '.php');

    // Fetch gateway configuration parameters.
    $gatewayParams = getGatewayVariables($gatewayModuleName);
    $notificationURL = $gatewayParams['systemurl'] . 'modules/gateways/callback/' . $gatewayModuleName . '.php';

    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Сервис «Экспресс Платежи»',
        ),
        'isTestMode' => array(
            'FriendlyName' => 'Использовать тестовый режим',
            'Type' => 'yesno',
            'Description' => 'Установите флажок, чтобы включить тестовый режим',
        ),
        'token' => array(
            'FriendlyName' => 'Токен',
            'Type' => 'text',
            'Default' => '',
        ),
        'serviceId' => array(
            'FriendlyName' => 'Номер услуги',
            'Type' => 'text',
            'Default' => '',
        ),
        'secretWord' => array(
            'FriendlyName' => 'Секретное слово для подписи счетов',
            'Type' => 'text',
            'Default' => '',
        ),
        'isUseSignatureForNotification' => array(
            'FriendlyName' => 'Использовать цифровую подпись для уведомлений',
            'Type' => 'yesno',
            'Description' => 'Установите флажок, чтобы включить цифровую подпись для уведомлений',
        ),
        'secretWordForNotification' => array(
            'FriendlyName' => 'Секретное слово для уведомлений',
            'Type' => 'text',
            'Default' => '',
        ),
        'notificationURL' => array(
            'FriendlyName' => 'Адрес для получения уведомлений',
            'Type' => 'text',
            'Value' => $notificationURL,
            'Default' => $notificationURL,
        ),
        'isUseOnlyCard' => array(
            'FriendlyName' => 'Только интернет-эквайринг',
            'Type' => 'yesno',
            'Description' => 'Установите флажок, чтобы использовать только оплату через интернет-эквайринг',
        ),
        'isNameEditable' => array(
            'FriendlyName' => 'Разрешено изменять ФИО',
            'Type' => 'yesno',
            'Description' => 'Установите флажок, чтобы разрешить изменять ФИО при оплате',
        ),
        'isAddressEditable' => array(
            'FriendlyName' => 'Разрешено изменять адрес',
            'Type' => 'yesno',
            'Description' => 'Установите флажок, чтобы разрешить изменять адрес при оплате',
        ),
        'isAmountEditable' => array(
            'FriendlyName' => 'Разрешено изменять сумму',
            'Type' => 'yesno',
            'Description' => 'Установите флажок, чтобы разрешить изменять сумму при оплате',
        ),
        
        'apiUrl' => array(
            'FriendlyName' => 'Адрес API',
            'Type' => 'text',
            'Default' => 'https://api.express-pay.by/',
        ),
        'sandboxUrl' => array(
            'FriendlyName' => 'Адрес тестового API',
            'Type' => 'text',
            'Default' => 'https://sandbox-api.express-pay.by/',
        ),
    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 */
function expresspay_link($params)
{
    $currencyCode = $params['currency'];

    $isTestMode = ($params['isTestMode'] == 'on')?true:false;
    $isUseOnlyCard = ($params['isUseOnlyCard'] == 'on')?true:false;

    if($isTestMode) $apiUrl = $params['sandboxUrl'];
    else $apiUrl = $params['apiUrl'];
    
    if($isUseOnlyCard) {
        $postfields = ExpressPayGateway::getCardInvoiceUrl($params);
        $apiUrl .= "/v1/web_cardinvoices";
    }
    else{
        $postfields = ExpressPayGateway::getInvoiceUrlV2($params);
        $apiUrl .= "/v2/invoices";
    }

    $htmlOutput = '<form method="post" action="'.$apiUrl.'">';    
    foreach ($postfields as $k => $v) {
        $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
    }
    
    $langPayNow = $params['langpaynow'];
    $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
    $htmlOutput .= '</form>';
    return $htmlOutput;
}