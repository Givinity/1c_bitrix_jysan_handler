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
use Bitrix\Sale\Result;

Loc::loadMessages(__FILE__);

/**
 * Class JusanPaymentHandler
 * @package Sale\Handlers\PaySystem
 */
class JusanHandler extends PaySystem\ServiceHandler implements PaySystem\IRefund
{
    // Updated URLs according to documentation
    const PAYMENT_URL = 'https://jpay-test.jusan.kz/ecom/api';
    const REFUND_URL = 'https://ecom.jysanbank.kz/ecom/api/refund';

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
        $currency = $payment->getField('CURRENCY') ?: 'KZT';

        // Получение параметров из настроек платежной системы
        $merchantId = $this->getBusinessValue($payment, 'JUSAN_MID');
        $terminalId = $this->getBusinessValue($payment, 'JUSAN_TID');
        $descriptor = $this->getBusinessValue($payment, 'JUSAN_DESCRIPTOR') ?: 'Mebelschik.kz';

        // Формирование уникального идентификатора заказа для платежной системы
        $paymentOrderId = $orderId . '_' . $paymentId;

        // Формирование параметров платежа для передачи в систему Jusan - согласно документации
        $paymentParams = [
            'ORDER' => $paymentOrderId,
            'AMOUNT' => number_format($paymentSum, 2, '.', ''),
            'CURRENCY' => $currency,
            'MERCHANT' => $merchantId,
            'TERMINAL' => $terminalId,
            'NONCE' => $this->generateNonce(),
            'LANGUAGE' => $this->getLanguage(),
            'DESC' => $descriptor,
            'EMAIL' => $this->getEmail($payment),
            'BACKREF' => $this->getReturnUrl($payment) . (strpos($this->getReturnUrl($payment), '?') === false ? '?' : '&') . 'redirect=true',
            'DESC_ORDER' => $this->getOrderDescription($payment),
        ];

        // Добавление опциональных параметров, если они заданы
        $clientId = $this->getBusinessValue($payment, 'JUSAN_CLIENT_ID');
        if ($clientId) {
            $paymentParams['CLIENT_ID'] = $clientId;
        }

        // Генерация подписи для платежа согласно документации
        $secretKey = $this->getBusinessValue($payment, 'JUSAN_SHARED_SECRET');
        $paymentParams['P_SIGN'] = $this->generateSignature($paymentParams, $secretKey);

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

        // Формирование данных для формы
        $this->setExtraParams([
            'PAYMENT_URL' => self::PAYMENT_URL,
            'PAYMENT_PARAMS' => $paymentParams,
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
     * Генерация случайного nonce для запроса
     *
     * @return string
     */
    private function generateNonce()
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Получение языка интерфейса
     *
     * @return string
     */
    private function getLanguage()
    {
        $language = LANGUAGE_ID ?: 'ru';
        return in_array($language, ['ru', 'en']) ? $language : 'ru';
    }

    /**
     * Получение email пользователя
     *
     * @param Payment $payment
     * @return string
     */
    private function getEmail(Payment $payment)
    {
        $order = $payment->getOrder();
        $propertyCollection = $order->getPropertyCollection();
        $emailProperty = $propertyCollection->getUserEmail();

        return $emailProperty ? $emailProperty->getValue() : '';
    }

    /**
     * Получение описания заказа
     *
     * @param Payment $payment
     * @return string
     */
    private function getOrderDescription(Payment $payment)
    {
        $order = $payment->getOrder();
        $basketItems = $order->getBasket()->getBasketItems();

        $description = '';
        foreach ($basketItems as $item) {
            $description .= $item->getField('NAME') . ' x ' . $item->getQuantity() . "\n";
        }

        // Удаляем переносы строк согласно требованиям API
        return preg_replace("/\n|\r/", " ", $description);
    }

    /**
     * Генерация подписи для запроса в соответствии с документацией
     *
     * @param array $params
     * @param string $secretKey
     * @return string
     */
    private function generateSignature(array $params, $secretKey)
    {
        // Порядок параметров согласно документации
        $signatureParams = [
            'ORDER' => $params['ORDER'] ?? '',
            'AMOUNT' => $params['AMOUNT'] ?? '',
            'CURRENCY' => $params['CURRENCY'] ?? '',
            'MERCHANT' => $params['MERCHANT'] ?? '',
            'TERMINAL' => $params['TERMINAL'] ?? '',
            'NONCE' => $params['NONCE'] ?? '',
            'CLIENT_ID' => $params['CLIENT_ID'] ?? '',
            'DESC' => preg_replace("/\n|\r/", "", $params['DESC'] ?? ''),
            'DESC_ORDER' => preg_replace("/\n|\r/", "", $params['DESC_ORDER'] ?? ''),
            'EMAIL' => $params['EMAIL'] ?? '',
            'BACKREF' => $params['BACKREF'] ?? '',
            'Ucaf_Flag' => $params['Ucaf_Flag'] ?? '',
            'Ucaf_Authentication_Data' => $params['Ucaf_Authentication_Data'] ?? '',
            'RECUR_FREQ' => $params['RECUR_FREQ'] ?? '',
            'RECUR_EXP' => $params['RECUR_EXP'] ?? '',
            'INT_REF' => $params['INT_REF'] ?? '',
            'RECUR_REF' => $params['RECUR_REF'] ?? '',
            'PAYMENT_TO' => $params['PAYMENT_TO'] ?? '',
            'MK_TOKEN' => $params['MK_TOKEN'] ?? '',
            'MERCH_TOKEN_ID' => $params['MERCH_TOKEN_ID'] ?? '',
            'MERCH_PAYTO_TOKEN_ID' => $params['MERCH_PAYTO_TOKEN_ID'] ?? '',
        ];

        // Формирование строки для подписи
        $signString = $secretKey;
        foreach ($signatureParams as $value) {
            $signString .= $value . ';';
        }

        // Генерация подписи по алгоритму SHA-512 согласно документации
        return hash('sha512', $signString);
    }

    /**
     * Генерация подписи для проверки ответа
     *
     * @param array $params
     * @param string $secretKey
     * @return string
     */
    private function generateResponseSignature(array $params, $secretKey)
    {
        // Порядок параметров согласно документации для проверки ответа
        $signatureParams = [
            'order' => $params['order'] ?? '',
            'mpi_order' => $params['mpi_order'] ?? '',
            'rrn' => $params['rrn'] ?? '',
            'res_code' => $params['res_code'] ?? '',
            'amount' => $params['amount'] ?? '',
            'currency' => $params['currency'] ?? '',
            'res_desc' => preg_replace("/\n|\r/", "", $params['res_desc'] ?? ''),
        ];

        // Формирование строки для подписи
        $signString = $secretKey;
        foreach ($signatureParams as $value) {
            $signString .= $value . ';';
        }

        // Генерация подписи по алгоритму SHA-512 согласно документации
        return hash('sha512', $signString);
    }


    /**
     * URL для успешного возврата
     *
     * @param Payment $payment
     * @return string
     */
    private function getReturnUrl(Payment $payment)
    {
        // Пытаемся получить URL из настроек платежной системы
        $returnUrl = $this->getBusinessValue($payment, 'JUSAN_RETURN_URL');

        if (!$returnUrl) {
            // Формируем стандартный URL для Bitrix с ORDER_ID
            $orderId = $payment->getOrderId();
            $context = \Bitrix\Main\Application::getInstance()->getContext();
            $server = $context->getServer();
            $scheme = $context->getRequest()->isHttps() ? 'https' : 'http';
            $host = $server->getHttpHost();

            $returnUrl = $scheme . '://' . $host . '/personal/basket/order.php?ORDER_ID=' . $orderId;
        }

        return $returnUrl;
    }

    /**
     * URL для отмены платежа
     *
     * @param Payment $payment
     * @return string
     */
    /**
     * URL для отмены платежа
     *
     * @param Payment $payment
     * @return string
     */
    private function getCancelUrl(Payment $payment)
    {
        // Пытаемся получить URL из настроек платежной системы
        $cancelUrl = $this->getBusinessValue($payment, 'JUSAN_CANCEL_URL');

        if (!$cancelUrl) {
            // Формируем стандартный URL для Bitrix с ORDER_ID
            $orderId = $payment->getOrderId();
            $context = \Bitrix\Main\Application::getInstance()->getContext();
            $server = $context->getServer();
            $scheme = $context->getRequest()->isHttps() ? 'https' : 'http';
            $host = $server->getHttpHost();

            $cancelUrl = $scheme . '://' . $host . '/personal/basket/order.php?ORDER_ID=' . $orderId;
        }

        return $cancelUrl;
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

        // Извлекаем RRN из данных платежа (должен был быть сохранен при успешной оплате)
        $rrn = $payment->getField('PS_STATUS_MESSAGE');
        if (preg_match('/RRN:\s*(\d+)/', $rrn, $matches)) {
            $rrn = $matches[1];
        } else {
            $result->addError(new Error('RRN not found in payment data'));
            return $result;
        }

        // Формирование параметров для запроса возврата согласно документации
        $refundParams = [
            'ORDER' => 'REFUND_' . $originalTransaction,
            'AMOUNT' => number_format($refundableSum, 2, '.', ''),
            'CURRENCY' => $payment->getField('CURRENCY') ?: 'KZT',
            'MERCHANT' => $merchantId,
            'TERMINAL' => $terminalId,
            'NONCE' => $this->generateNonce(),
            'INT_REF' => $rrn, // Согласно документации, должен соответствовать RRN исходной операции
            'DESC' => 'Refund for order ' . $originalTransaction,
        ];

        // Генерация подписи для запроса возврата
        $secretKey = $this->getBusinessValue($payment, 'JUSAN_SHARED_SECRET');
        $refundParams['P_SIGN'] = $this->generateSignature($refundParams, $secretKey);

        try {
            // Отправка запроса на возврат
            $httpClient = new HttpClient();
            $response = $httpClient->post(self::REFUND_URL, $refundParams);
            $responseData = json_decode($response, true);

            if ($responseData && isset($responseData['res_code']) && $responseData['res_code'] === '0') {
                $result->setOperationType(ServiceResult::MONEY_LEAVING);
                $result->setPsData([
                    'PS_INVOICE_ID' => 'REFUND_' . $originalTransaction,
                    'PS_STATUS' => 'REFUNDED',
                    'PS_STATUS_CODE' => $responseData['res_code'] ?? '0',
                    'PS_STATUS_DESCRIPTION' => $responseData['res_desc'] ?? 'Refund successful',
                    'PS_STATUS_MESSAGE' => 'Refund completed. RRN: ' . ($responseData['rrn'] ?? ''),
                    'PS_SUM' => $refundableSum,
                    'PS_CURRENCY' => $payment->getField('CURRENCY'),
                    'PS_RESPONSE_DATE' => new DateTime()
                ]);
            } else {
                $errorMessage = $responseData['res_desc'] ?? 'Unknown refund error';
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
        // Сначала проверяем payment_id, который мы добавляем в notify_url
        $paymentId = $request->get('payment_id');
        if ($paymentId) {
            return $paymentId;
        }

        // Если не нашли, извлекаем из order_id (используется в callback)
        $orderId = $request->get('order');
        if ($orderId) {
            // Извлекаем ID платежа из составного order_id (orderId_paymentId)
            $parts = explode('_', $orderId);
            if (count($parts) > 1) {
                return $parts[1];
            }
        }

        return null;
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

        // Получаем код результата
        $resCode = $request->get('res_code');

        // RRN транзакции
        $rrn = $request->get('rrn') ?: '';

        // Идентификатор заказа
        $orderId = $request->get('order') ?: '';

        if ($resCode === '0') {
            // Успешная операция
            $result->setOperationType(ServiceResult::MONEY_COMING);
            $result->setPsData([
                'PS_INVOICE_ID' => $orderId,
                'PS_STATUS' => 'Y',
                'PS_STATUS_CODE' => $resCode,
                'PS_STATUS_DESCRIPTION' => $request->get('res_desc') ?? 'Payment successful',
                'PS_STATUS_MESSAGE' => 'Payment completed. RRN: ' . $rrn,
                'PS_SUM' => $request->get('amount') ?? $payment->getSum(),
                'PS_CURRENCY' => $request->get('currency') ?? $payment->getField('CURRENCY'),
                'PS_RESPONSE_DATE' => new DateTime()
            ]);
        } else {
            // Неуспешная операция
            $errorMessage = $request->get('res_desc') ?? 'Payment failed';
            $result->addError(new Error($errorMessage));

            // Обновляем статус платежа
            $result->setPsData([
                'PS_INVOICE_ID' => $orderId,
                'PS_STATUS' => 'N',
                'PS_STATUS_CODE' => $resCode,
                'PS_STATUS_DESCRIPTION' => $errorMessage,
                'PS_STATUS_MESSAGE' => 'Payment failed',
                'PS_RESPONSE_DATE' => new DateTime()
            ]);
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

        $signature = $request->get('sign');
        if (!$signature) {
            return false;
        }

        // Получение параметров из запроса для проверки подписи
        $params = $request->toArray();

        $calculatedSignature = $this->generateResponseSignature($params, $secretKey);

        return hash_equals($signature, $calculatedSignature);
    }

    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return ['KZT'];
    }

    /**
     * @param Payment $payment
     * @return bool
     */
    public function isRefundableExtended(Payment $payment)
    {
        // Проверка возможности возврата для платежа
        return $payment->getField('PS_STATUS') === 'Y';
    }

    /**
     * @return array
     */
    public static function getIndicativeFields()
    {
        return ['order', 'merchant', 'sign'];
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected static function isMyResponseExtended(Request $request)
    {
        // Определяем, относится ли запрос к нашей платежной системе
        return $request->get('order') !== null && $request->get('sign') !== null;
    }

    /**
     * @return array
     */
    protected function getUrlList()
    {
        return [
            'pay' => self::PAYMENT_URL,
            'refund' => self::REFUND_URL,
        ];
    }
}
