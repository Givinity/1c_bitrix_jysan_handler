<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$paymentUrl = $params['PAYMENT_URL'];
?>

<div class="sale-paysystem-wrapper">
    <p>Вы будете перенаправлены на страницу оплаты Jusan Bank.</p>

    <script type="text/javascript">
        window.location.href = '<?=htmlspecialcharsbx($paymentUrl)?>';
    </script>

    <p>Если вы не были перенаправлены автоматически, пожалуйста, нажмите на кнопку ниже:</p>
    <form action="<?=htmlspecialcharsbx($paymentUrl)?>" method="GET">
        <input type="submit" class="btn btn-primary" value="Перейти к оплате">
    </form>
</div>
