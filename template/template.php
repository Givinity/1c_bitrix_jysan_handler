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



<?if($_GET['res_code'] !== '0') {?>
<div class="sale-payment-jusan-form-container">
	<?if($_GET['res_code'] !== '0' and !empty($_GET['res_desc'])) {?>
	<?$resDesc = urldecode($_GET['res_desc']);?>
	<div class="jusan-payment-error">
		<h3><?=Loc::getMessage('JUSAN_PAYMENT_ERROR_HEADER')?></h3>
		<div class="alert alert-danger">
			<?=htmlspecialcharsbx($resDesc ?? $params['ERROR_MESSAGE'])?>
		</div>
	   <br>
		<a href="/personal/profile/orders/" class="btn btn-primary">
			<?=Loc::getMessage('JUSAN_RETURN_TO_ORDERS')?>
		</a>
	</div>
	<?}?>
    <div class="sale-payment-jusan-description">
        <?=Loc::getMessage('JUSAN_PAYMENT_ORDER_NUMBER')?>: <?=$params['ORDER_ID']?><br>
        <?=Loc::getMessage('JUSAN_PAYMENT_ID')?>: <?=$params['PAYMENT_ID']?>
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
<?}?>

<?if($_GET['res_code'] === '0') {?>

<div class="jusan-payment-success">
    <div class="alert alert-success">
        <h3><?=Loc::getMessage('JUSAN_PAYMENT_SUCCESS')?></h3>
    </div>
    
    <div class="payment-details">
        <p><strong><?=Loc::getMessage('JUSAN_ORDER_NUMBER')?>:</strong> <?=$params['ORDER_ID']?></p>
        <p><strong><?=Loc::getMessage('JUSAN_PAYMENT_AMOUNT')?>:</strong> <?=$params['AMOUNT']?> <?=$params['CURRENCY']?></p>

        <?php if (!empty($params['RRN'])): ?>
            <p><strong><?=Loc::getMessage('JUSAN_PAYMENT_RRN')?>:</strong> <?=$params['RRN']?></p>
        <?php endif; ?>
    </div>
    
    <div class="actions">
        <a href="/personal/profile/orders/" class="btn btn-primary">
            <?=Loc::getMessage('JUSAN_RETURN_TO_ORDERS')?>
        </a>
        <a href="/" class="btn btn-default">
            <?=Loc::getMessage('JUSAN_RETURN_TO_HOME')?>
        </a>
    </div>
</div>

<style>
.jusan-payment-success {
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
}
.alert-success {
    color: #3c763d;
    background-color: #dff0d8;
    border-color: #d6e9c6;
    padding: 15px;
    margin-bottom: 20px;
}
.payment-details {
    margin-bottom: 20px;
}
.payment-details p {
    margin: 10px 0;
}
.actions {
    margin-top: 20px;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    font-size: 16px;
    border-radius: 4px;
    text-decoration: none;
    margin-right: 10px;
}
.btn-primary {
    color: #fff;
    background-color: #337ab7;
    border-color: #2e6da4;
}
.btn-default {
    color: #333;
    background-color: #fff;
    border: 1px solid #ccc;
}
</style>

<?}?>

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
