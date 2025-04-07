<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * @var array $params
 * @var array $paymentParams
 * @var string $paymentUrl
 */

$paymentUrl = $params['PAYMENT_URL'] ?? '';
$paymentParams = $params['PAYMENT_PARAMS'] ?? [];
$paymentId = $params['PAYMENT_ID'] ?? '';
$orderId = $params['ORDER_ID'] ?? '';
?>

<div class="sale-payment-jusan-form-container">
    <div class="sale-payment-jusan-description">
        <?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_JUSAN_DESCRIPTION') ?>
    </div>

    <?php if ($paymentUrl && !empty($paymentParams)): ?>
        <form id="jusan_payment_form_<?= $paymentId ?>" action="<?= htmlspecialcharsbx($paymentUrl) ?>" method="post" accept-charset="UTF-8">
            <?php foreach ($paymentParams as $name => $value): ?>
                <input type="hidden" name="<?= htmlspecialcharsbx($name) ?>" value="<?= htmlspecialcharsbx($value) ?>">
            <?php endforeach; ?>

            <div class="sale-payment-jusan-button-container">
                <input type="submit" value="<?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_JUSAN_BUTTON_PAID') ?>" class="sale-payment-jusan-button">
            </div>
        </form>
        <script type="text/javascript">
            BX.ready(function() {
                // Auto-submit the form if auto-redirect is enabled
                <?php if ($params['AUTO_REDIRECT'] === 'Y'): ?>
                setTimeout(function() {
                    document.getElementById('jusan_payment_form_<?= $paymentId ?>').submit();
                }, 1000);
                <?php endif; ?>
            });
        </script>
    <?php else: ?>
        <div class="sale-payment-jusan-error">
            <?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_JUSAN_ERROR_CONFIG') ?>
        </div>
    <?php endif; ?>

    <div class="sale-payment-jusan-payment-info">
        <p><?= Loc::getMessage('SALE_HANDLERS_PAY_SYSTEM_JUSAN_PAYMENT_INFO', [
                '#ORDER_ID#' => $orderId,
                '#PAYMENT_ID#' => $paymentId
            ]) ?></p>
    </div>
</div>

<style type="text/css">
    .sale-payment-jusan-form-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }
    .sale-payment-jusan-description {
        margin-bottom: 20px;
    }
    .sale-payment-jusan-button-container {
        margin: 20px 0;
        text-align: center;
    }
    .sale-payment-jusan-button {
        display: inline-block;
        padding: 10px 30px;
        background-color: #1e88e5;
        color: #ffffff;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
    }
    .sale-payment-jusan-button:hover {
        background-color: #1976d2;
    }
    .sale-payment-jusan-error {
        color: #e53935;
        margin-bottom: 20px;
        padding: 10px;
        border: 1px solid #e53935;
        border-radius: 4px;
    }
    .sale-payment-jusan-payment-info {
        margin-top: 20px;
        font-size: 14px;
        color: #757575;
    }
</style>
