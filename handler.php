<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Error;
use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\PaySystem\ServiceResult;
use Bitrix\Sale\PsService;
use Bitrix\Sale\Result;

Loc::loadMessages(__FILE__);

/**
 * Class JusanPaymentHandler
 * @package Sale\Handlers\PaySystem
 */
class JusanPaymentHandler extends PaySystem\ServiceHandler implements PaySystem\IRefund
{
    const PAYMENT_URL = 'https://jpay.jysanbank.kz/ecom/arm1';

    /**
     * @param Payment $payment
     * @param Request|null $request
     * @return ServiceResult
     */
    public function initiatePay(Payment $payment, Request $request = null)
    {
        $result = new ServiceResult();

        // Получение параметров платежа
        $orderId = $payment->getOrderId();
        $paymentId = $payment->getId();
        $paymentSum = $payment->getSum();
        $currency = $payment->getField('CURRENCY');

        // Получение параметров из настроек платежной системы
        $merchantId = $this->getBusinessValue($payment, 'JUSAN_MID');
        $terminalId = $this->getBusinessValue($payment, 'JUSAN_TID');
        $descriptor = $this->getBusinessValue($payment, 'JUSAN_DESCRIPTOR') ?: 'Mebelschik.kz';

        // Формирование уникального идентификатора заказа для платежной системы
        // Обычно это комбинация ID заказа и ID платежа для уникальности
        $paymentOrderId = $orderId . '_' . $paymentId;

        // Формирование параметров платежа для передачи в систему Jusan
        $paymentParams = [
            'mid' => $merchantId,
            'tid' => $terminalId,
            'order_id' => $paymentOrderId,
            'amount' => number_format($paymentSum, 2, '.', ''),
            'currency' => $currency ?: 'KZT',
            'descriptor' => $descriptor,
            'timestamp' => date('YmdHis'),
            'return_url' => $this->getReturnUrl($payment),
            'cancel_url' => $this->getCancelUrl($payment),
            'notify_url' => $this->getNotifyUrl($payment),
        ];

        // Генерация подписи для платежа
        $secretKey = $this->getBusinessValue($payment, 'JUSAN_SHARED_SECRET');
        $paymentParams['signature'] = $this->generateSignature($paymentParams, $secretKey);

        // Формирование URL для редиректа
        $paymentUrl = self::PAYMENT_URL . '?' . http_build_query($paymentParams);

        // Сохранение данных о платеже
        $result->setPsData([
            'PS_INVOICE_ID' => $paymentOrderId,
            'PS_STATUS' => 'N',
            'PS_STATUS_DESCRIPTION' => 'Redirected to Jusan payment page',
            'PS_STATUS_MESSAGE' => 'Payment is waiting for processing',
            'PS_SUM' => $paymentSum,
            'PS_CURRENCY' => $currency,
            'PS_RESPONSE_DATE' => new DateTime()
        ]);

        // Подготовка шаблона для редиректа
        $this->setExtraParams([
            'PAYMENT_URL' => $paymentUrl,
            'PAYMENT_ID' => $paymentId,
            'ORDER_ID' => $orderId
        ]);

        $showTemplateResult = $this->showTemplate($payment, 'template');
        if ($showTemplateResult->isSuccess()) {
            $result->setTemplate($showTemplateResult->getTemplate());
        } else {
            $result->addErrors($showTemplateResult->getErrors());
        }

        return $result;
    }

    /**
     * Генерация подписи для запроса
     *
     * @param array $params
     * @param string $secretKey
     * @return string
     */
    private function generateSignature(array $params, $secretKey)
    {
        // Сортировка параметров по ключу для Jusan API
        ksort($params);

        // Формирование строки для подписи
        $signString = '';
        foreach ($params as $key => $value) {
            $signString .= $key . '=' . $value . ';';
        }
        $signString .= $secretKey;

        // Генерация подписи по алгоритму MD5 или SHA-256
        // (уточните в документации Jusan, какой алгоритм они используют)
        return hash('sha256', $signString);
    }

    /**
     * URL для успешного возврата
     *
     * @param Payment $payment
     * @return string
     */
    private function getReturnUrl(Payment $payment)
    {
        return $this->getBusinessValue($payment, 'JUSAN_RETURN_URL') ?: $this->getSuccessUrl();
    }

    /**
     * URL для отмены платежа
     *
     * @param Payment $payment
     * @return string
     */
    private function getCancelUrl(Payment $payment)
    {
        return $this->getBusinessValue($payment, 'JUSAN_CANCEL_URL') ?: $this->getFailUrl();
    }

    /**
     * URL для уведомлений от платежной системы
     *
     * @param Payment $payment
     * @return string
     */
    private function getNotifyUrl(Payment $payment)
    {
        $serviceUrl = $this->getServiceUrl();
        return $serviceUrl . (strpos($serviceUrl, '?') === false ? '?' : '&') . 'payment_id=' . $payment->getId();
    }

    /**
     * @param Payment $payment
     * @param $refundableSum
     * @return ServiceResult
     */
    public function refund(Payment $payment, $refundableSum)
    {
        $result = new ServiceResult();

        // Получение параметров для возврата
        $merchantId = $this->getBusinessValue($payment, 'JUSAN_MID');
        $terminalId = $this->getBusinessValue($payment, 'JUSAN_TID');
        $originalTransaction = $payment->getField('PS_INVOICE_ID');

        // Формирование параметров для запроса возврата
        $refundParams = [
            'mid' => $merchantId,
            'tid' => $terminalId,
            'original_order_id' => $originalTransaction,
            'refund_amount' => number_format($refundableSum, 2, '.', ''),
            'currency' => $payment->getField('CURRENCY') ?: 'KZT',
            'timestamp' => date('YmdHis'),
        ];

        // Генерация подписи для запроса возврата
        $secretKey = $this->getBusinessValue($payment, 'JUSAN_SHARED_SECRET');
        $refundParams['signature'] = $this->generateSignature($refundParams, $secretKey);

        // URL для запроса возврата (обычно отличается от URL для платежа)
        $refundUrl = $this->getBusinessValue($payment, 'JUSAN_REFUND_URL') ?: 'https://jpay.jysanbank.kz/ecom/refund';

        try {
            // Отправка запроса на возврат
            $httpClient = new HttpClient();
            $response = $httpClient->post($refundUrl, $refundParams);
            $responseData = json_decode($response, true);

            if ($responseData && isset($responseData['status']) && $responseData['status'] === 'success') {
                $result->setOperationType(ServiceResult::MONEY_LEAVING);
                $result->setPsData([
                    'PS_INVOICE_ID' => 'REFUND_' . $originalTransaction,
                    'PS_STATUS' => 'REFUNDED',
                    'PS_STATUS_CODE' => $responseData['code'] ?? '200',
                    'PS_STATUS_DESCRIPTION' => $responseData['message'] ?? 'Refund successful',
                    'PS_STATUS_MESSAGE' => $responseData['details'] ?? 'Payment was refunded',
                    'PS_SUM' => $refundableSum,
                    'PS_CURRENCY' => $payment->getField('CURRENCY'),
                    'PS_RESPONSE_DATE' => new DateTime()
                ]);
            } else {
                $errorMessage = $responseData['error'] ?? 'Unknown refund error';
                $result->addError(new Error('Refund failed: ' . $errorMessage));
            }
        } catch (\Exception $e) {
            $result->addError(new Error('Refund request failed: ' . $e->getMessage()));
        }

        return $result;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        $orderId = $request->get('order_id');
        if ($orderId) {
            // Извлекаем ID платежа из составного order_id (orderId_paymentId)
            $parts = explode('_', $orderId);
            if (count($parts) > 1) {
                return $parts[1];
            }
        }

        return $request->get('payment_id');
    }

    /**
     * @param Payment $payment
     * @param Request $request
     * @return ServiceResult
     */
    public function processRequest(Payment $payment, Request $request)
    {
        $result = new ServiceResult();

        // Проверка подписи
        if (!$this->checkRequestSignature($request, $payment)) {
            $result->addError(new Error('Invalid signature'));
            return $result;
        }

        $status = $request->get('status');
        $transactionId = $request->get('transaction_id') ?: $request->get('order_id');

        if ($status === 'success' || $status === 'approved') {
            $result->setOperationType(ServiceResult::MONEY_COMING);
            $result->setPsData([
                'PS_INVOICE_ID' => $transactionId,
                'PS_STATUS' => 'Y',
                'PS_STATUS_CODE' => $request->get('code') ?? '200',
                'PS_STATUS_DESCRIPTION' => $request->get('message') ?? 'Payment successful',
                'PS_STATUS_MESSAGE' => $request->get('details') ?? 'Payment was completed',
                'PS_SUM' => $request->get('amount') ?? $payment->getSum(),
                'PS_CURRENCY' => $request->get('currency') ?? $payment->getField('CURRENCY'),
                'PS_RESPONSE_DATE' => new DateTime()
            ]);
        } else {
            $errorMessage = $request->get('error_message') ?? 'Payment failed';
            $result->addError(new Error($errorMessage));
        }

        return $result;
    }

    /**
     * @param Request $request
     * @param Payment $payment
     * @return bool
     */
    private function checkRequestSignature(Request $request, Payment $payment)
    {
        $secretKey = $this->getBusinessValue($payment, 'JUSAN_SHARED_SECRET');
        if (!$secretKey) {
            return true; // Если ключ не задан, проверка не выполняется
        }

        $signature = $request->get('signature');
        if (!$signature) {
            return false;
        }

        // Получение параметров из запроса для проверки подписи
        $params = $request->toArray();
        unset($params['signature']); // Исключаем саму подпись из проверки

        $calculatedSignature = $this->generateSignature($params, $secretKey);

        return $signature === $calculatedSignature;
    }

    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return ['KZT', 'USD', 'EUR', 'RUB'];
    }

    /**
     * @param Payment $payment
     * @return bool
     */
    public function isRefundableExtended(Payment $payment)
    {
        // Проверка возможности возврата для платежа
        // В системе Jusan обычно возврат возможен только для успешных платежей
        return $payment->getField('PS_STATUS') === 'Y';
    }

    /**
     * @return array
     */
    public static function getIndicativeFields()
    {
        return ['mid', 'tid', 'order_id', 'signature'];
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function isMyResponseExtended(Request $request)
    {
        // Определяем, относится ли запрос к нашей платежной системе
        return $request->get('mid') !== null && $request->get('order_id') !== null;
    }

    /**
     * @return array
     */
    protected function getUrlList()
    {
        return [
            'pay' => self::PAYMENT_URL,
            'refund' => 'https://jpay.jysanbank.kz/ecom/refund', // URL рефанда (уточните в документации)
        ];
    }

    /**
     * @return array
     */
    public function getConfigField(Payment $payment = null)
    {
        $configFields = [
            'JUSAN_MID' => [
                'NAME' => Loc::getMessage('SALE_HPS_JUSAN_MID'),
                'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_MID_DESC'),
                'VALUE' => '',
                'GROUP' => 'JUSAN_SETTINGS',
            ],
            'JUSAN_TID' => [
                'NAME' => Loc::getMessage('SALE_HPS_JUSAN_TID'),
                'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_TID_DESC'),
                'VALUE' => '',
                'GROUP' => 'JUSAN_SETTINGS',
            ],
            'JUSAN_SHARED_SECRET' => [
                'NAME' => Loc::getMessage('SALE_HPS_JUSAN_SHARED_SECRET'),
                'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_SHARED_SECRET_DESC'),
                'VALUE' => '',
                'GROUP' => 'JUSAN_SETTINGS',
            ],
            'JUSAN_DESCRIPTOR' => [
                'NAME' => Loc::getMessage('SALE_HPS_JUSAN_DESCRIPTOR'),
                'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_DESCRIPTOR_DESC'),
                'VALUE' => 'Mebelschik.kz',
                'GROUP' => 'JUSAN_SETTINGS',
            ],
            'JUSAN_RETURN_URL' => [
                'NAME' => Loc::getMessage('SALE_HPS_JUSAN_RETURN_URL'),
                'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_RETURN_URL_DESC'),
                'VALUE' => '',
                'GROUP' => 'JUSAN_SETTINGS',
            ],
            'JUSAN_CANCEL_URL' => [
                'NAME' => Loc::getMessage('SALE_HPS_JUSAN_CANCEL_URL'),
                'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_CANCEL_URL_DESC'),
                'VALUE' => '',
                'GROUP' => 'JUSAN_SETTINGS',
            ],
        ];

        return $configFields;
    }
}
