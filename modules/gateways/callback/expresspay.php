<?php
/**
 * WHMCS Sample Payment Callback File
 *
 * This sample file demonstrates how a payment gateway callback should be
 * handled within WHMCS.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

require_once __DIR__ . '/../expresspay/gateway.php';
require_once __DIR__ . '/../expresspay/log.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dataJSON = (isset($_POST['Data'])) ? htmlspecialchars_decode($_POST['Data']) : '';
    $signature = (isset($_POST['Signature'])) ? $_POST['Signature'] : '';
    $useSignatureForNotification = ($gatewayParams['isUseSignatureForNotification'] == 'on') ? true : false;
    $secretWordForNotification = $gatewayParams['secretWordForNotification'];
    if ($useSignatureForNotification) {

        $valid_signature = ExpressPayGateway::computeSignature(array("data" => $dataJSON), $secretWordForNotification, 'notification');
        if ($valid_signature == $signature) {
            notify_success($dataJSON);
        } else {                    
            header('HTTP/1.1 403 FORBIDDEN');
            echo 'FAILED | Access is denied';
            ExpressPayLog::log_error("notify", "Access is denied");
            return;
        }
    }
    else{
        notify_success($dataJSON);
    }
}
else{
    header('HTTP/1.1 405 Method Not Allowed');
    echo 'FAILED | request method not supported';
    return;
}

function notify_success($dataJSON)
{
    // Преобразование из json в array
    $data = array();
    try {
        $data = json_decode($dataJSON,true); 
    } 
    catch(Exception $e) {
        header('HTTP/1.1 400 Bad Request');
        echo 'FAILED | Failed to decode data';
        ExpressPayLog::log_error("notify_success", "Failed to decode data");
        return;
    }

    $accountNo = $data['AccountNo'];
    if(isset($accountNo)){
        $invoiceNo  = $data['InvoiceNo'];
        $cmdtype    = $data['CmdType'];
        $status     = $data['Status'];
        $amount     = $data['Amount'];

        switch ($cmdtype) {
            case 1:
                header("HTTP/1.1 200 OK");
                echo 'OK | the notice is processed';
                logTransaction($gatewayParams['name'], $_POST, 'Status changed to expired');
                ExpressPayLog::log_info("notify_success", "the notice is processed");
                return;
            case 2:
                header("HTTP/1.1 200 OK");
                echo 'OK | the notice is processed';
                logTransaction($gatewayParams['name'], $_POST, 'Status changed to expired');
                ExpressPayLog::log_info("notify_success", "the notice is processed");
                return;
            case 3:
                if(isset($status)){
                    switch($status){
                        case 1: // Ожидает оплату
                            logTransaction($gatewayParams['name'], $_POST, 'Status changed to pending payment');
                            break;
                        case 2: // Просрочен
                            logTransaction($gatewayParams['name'], $_POST, 'Status changed to expired');
                            break;
                        case 3: // Оплачен
                        case 6: // Оплачен с помощью банковской карты
                            $accountNo = checkCbInvoiceID($accountNo, $gatewayParams['name']);
                            checkCbTransID($invoiceNo);
                            logTransaction($gatewayParams['name'], $_POST, 'Status changed to paid');
                            /**
                             * Add Invoice Payment.
                             *
                             * Applies a payment transaction entry to the given invoice ID.
                             *
                             * @param int $invoiceId         Invoice ID
                             * @param string $transactionId  Transaction ID
                             * @param float $paymentAmount   Amount paid (defaults to full balance)
                             * @param float $paymentFee      Payment fee (optional)
                             * @param string $gatewayModule  Gateway module name
                             */
                            addInvoicePayment(
                                $accountNo,
                                $invoiceNo,
                                $amount,
                                null,
                                $gatewayModuleName
                            );
                            break;
                        case 4: // Оплачен частично
                            logTransaction($gatewayParams['name'], $_POST, 'Status changed to paid in part');
                            break;
                        case 5: // Отменен
                            logTransaction($gatewayParams['name'], $_POST, 'Status changed to canceled');
                            break;
                        case 7: // Платеж возращен
                            logTransaction($gatewayParams['name'], $_POST, 'Status changed to payment returned');
                            break;
                        default:
                            header('HTTP/1.1 400 Bad Request');
                            echo'FAILED | invalid status'; //Ошибка в параметрах
                            ExpressPayLog::log_error("notify_success", "Invalid status; Status - ".$status);
                            return;
                    }
                    header("HTTP/1.1 200 OK");
                    echo 'OK | the notice is processed';
                    ExpressPayLog::log_info("notify_success", "the notice is processed");
                    return;
                }
                break;
            default:
                header('HTTP/1.1 400 Bad Request');
                echo'FAILED | invalid cmdtype';
                ExpressPayLog::log_error("notify_success", "Invalid cmdtype; CmdType - ".$cmdtype);
                return;
            }
    }
    header('HTTP/1.1 400 Bad Request');
    echo 'FAILED | The notice is not processed';
    ExpressPayLog::log_error("notify_success", "The notice is not processed");
}