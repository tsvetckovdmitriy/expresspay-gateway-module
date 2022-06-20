<?php

/**
 * @package       ExpressPay Payment Module for WHMCS
 * @author        ООО "ТриИнком" <info@express-pay.by>
 * @copyright     (c) 2022 Экспресс Платежи. Все права защищены.
 */

class ExpressPayGateway {

    public static function getInvoiceUrlV2($params)
    {
        $isTestMode = ($params['isTestMode'] == 'on')?true:false;
    
        if($isTestMode) {
            $token = 'a75b74cbcfe446509e8ee874f421bd66';
            $serviceId = '4';
            $secretWord = 'sandbox.expresspay.by';
        }
        else {
            $token = $params['token'];
            $serviceId = $params['serviceId'];
            $secretWord = $params['secretWord'];
        }
    
        $accountNo = $params['invoiceid'].'test';
        $amount = $params['amount'];
        $description = $params["description"];
        $returnUrl = $params['returnurl'];

        $firstname = $params['clientdetails']['firstname'];
        $lastname = $params['clientdetails']['lastname'];
        $email = $params['clientdetails']['email'];
        $phone = $params['clientdetails']['phonenumber'];
        $city = $params['clientdetails']['city'];
        $phone = preg_replace("/[^0-9]/", '', $phone);

        $successUrl = $params['returnurl'] . '&paymentsuccess=true';
        $failUrl = $params['returnurl'] . '&paymentfailed=true';
        $signatureParams = array(
            "Token" => $token,
            "ServiceId" => $serviceId,
            "AccountNo" => $accountNo,
            "Amount" => $amount,
            "Currency" => 933,
            "Info" => $description,
            "Surname" => $lastname,
            "FirstName" => $firstname,
            "City" => $city,
            "IsNameEditable" => ($isNameEditable == 'on')?1:0,
            "IsAddressEditable" => ($isAddressEditable == 'on')?1:0,
            "IsAmountEditable" => ($isAmountEditable == 'on')?1:0,
            "EmailNotification" => $email,
            "ReturnUrl" => $successUrl,
            "FailUrl" => $failUrl,
            //"ReturnType" => "json"
            "ReturnType" => "redirect"
        );
        if(strlen($phone) == 12) $signatureParams['SmsPhone'] = $phone;
        $signatureParams['Signature'] = self::computeSignature($signatureParams, $secretWord, "add-invoice-v2");
        unset($signatureParams["Token"]);
    
        ExpressPayLog::log_info("getInvoiceUrlV2", "returns". json_encode($signatureParams));
        return $signatureParams;
    }

    public static function getCardInvoiceUrl($params){
        $isTestMode = $params['isTestMode'];
    
        if($isTestMode) {
            $token = 'a75b74cbcfe446509e8ee874f421bd68';
            $serviceId = '6';
            $secretWord = 'sandbox.expresspay.by';
        }
        else {
            $token = $params['token'];
            $serviceId = $params['serviceId'];
            $secretWord = $params['secretWord'];
        }
    
        $accountNo = $params['invoiceid'];
        $amount = $params['amount'];
        $description = $params["description"];

        $successUrl = $params['returnurl'] . '&paymentsuccess=true';
        $failUrl = $params['returnurl'] . '&paymentfailed=true';

        $signatureParams = array(
            "Token" => $token,
            "ServiceId" => $serviceId,
            "AccountNo" => $accountNo,
            "Amount" => $amount,
            "Currency" => 933,
            "Info" => $description,
            "ReturnUrl" => $successUrl,
            "FailUrl" => $failUrl,
            //"ReturnType" => "json"
            "ReturnType" => "redirect"
        );
        $signatureParams['Signature'] = self::computeSignature($signatureParams, $secretWord, "add-webcard-invoice");
        unset($signatureParams["Token"]);

        ExpressPayLog::log_info("getCardInvoiceUrl", "returns". json_encode($signatureParams));
        return $signatureParams;
    }

    public static function sendRequestPOST($url, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * 
     * Формирование цифровой подписи
     * 
     * @param array  $signatureParams Список передаваемых параметров
     * @param string $secretWord      Секретное слово
     * @param string $method          Метод формирования цифровой подписи
     * 
     * @return string $hash           Сформированная цифровая подпись
     * 
     */
    public static function computeSignature($signatureParams, $secretWord, $method)
    {
        $normalizedParams = array_change_key_case($signatureParams, CASE_LOWER);
        $mapping = array(
            "add-invoice"      => array(
                "token",
                "accountno",
                "amount",
                "currency",
                "expiration",
                "info",
                "surname",
                "firstname",
                "patronymic",
                "city",
                "street",
                "house",
                "building",
                "apartment",
                "isnameeditable",
                "isaddresseditable",
                "isamounteditable",
                "emailnotification",
                "returninvoiceurl"
            ),
            "add-invoice-v2"      => array(
                "token",
                "serviceid",
                "accountno",
                "amount",
                "currency",
                "expiration",
                "info",
                "surname",
                "firstname",
                "patronymic",
                "city",
                "street",
                "house",
                "building",
                "apartment",
                "isnameeditable",
                "isaddresseditable",
                "isamounteditable",
                "emailnotification",
                "smsphone",
                "returntype",
                "returnurl",
                "failurl"
            ),
            "add-webcard-invoice" => array(
                "token",
                "serviceid",
                "accountno",
                "expiration",
                "amount",
                "currency",
                "info",
                "returnurl",
                "failurl",
                "language",
                "sessiontimeoutsecs",
                "expirationdate",
                "returntype",
                "returninvoiceurl"
            ),
            "notification"         => array(
                "data"
            )
        );
        $apiMethod = $mapping[$method];
        $result = "";
        foreach ($apiMethod as $item) {
            $result .= (isset($normalizedParams[$item])) ? $normalizedParams[$item] : '';
        }
        $hash = strtoupper(hash_hmac('sha1', $result, $secretWord));
        return $hash;
    }
}