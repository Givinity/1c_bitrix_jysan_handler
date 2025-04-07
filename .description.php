<?php
use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Sale\PaySystem;

Loc::loadMessages(__FILE__);

$data = [
    'NAME' => Loc::getMessage('SALE_HPS_JUSAN_NAME'),
    'SORT' => 500,
    'CODES' => [
        'JUSAN_MID' => [
            'NAME' => Loc::getMessage('SALE_HPS_JUSAN_MID'),
            'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_MID_DESC'),
            'SORT' => 100,
            'GROUP' => 'JUSAN_SETTINGS',
        ],
        'JUSAN_TID' => [
            'NAME' => Loc::getMessage('SALE_HPS_JUSAN_TID'),
            'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_TID_DESC'),
            'SORT' => 200,
            'GROUP' => 'JUSAN_SETTINGS',
        ],
        'JUSAN_SHARED_SECRET' => [
            'NAME' => Loc::getMessage('SALE_HPS_JUSAN_SHARED_SECRET'),
            'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_SHARED_SECRET_DESC'),
            'SORT' => 300,
            'GROUP' => 'JUSAN_SETTINGS',
        ],
        'JUSAN_DESCRIPTOR' => [
            'NAME' => Loc::getMessage('SALE_HPS_JUSAN_DESCRIPTOR'),
            'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_DESCRIPTOR_DESC'),
            'SORT' => 400,
            'GROUP' => 'JUSAN_SETTINGS',
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'Mebelschik.kz',
                'PROVIDER_KEY' => 'VALUE'
            ]
        ],
        'JUSAN_RETURN_URL' => [
            'NAME' => Loc::getMessage('SALE_HPS_JUSAN_RETURN_URL'),
            'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_RETURN_URL_DESC'),
            'SORT' => 500,
            'GROUP' => 'JUSAN_SETTINGS',
        ],
        'JUSAN_CANCEL_URL' => [
            'NAME' => Loc::getMessage('SALE_HPS_JUSAN_CANCEL_URL'),
            'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_CANCEL_URL_DESC'),
            'SORT' => 600,
            'GROUP' => 'JUSAN_SETTINGS',
        ],
        'JUSAN_CLIENT_ID' => [
            'NAME' => Loc::getMessage('SALE_HPS_JUSAN_CLIENT_ID'),
            'DESCRIPTION' => Loc::getMessage('SALE_HPS_JUSAN_CLIENT_ID_DESC'),
            'SORT' => 700,
            'GROUP' => 'JUSAN_SETTINGS',
        ],
    ]
];

// Currency compatibility settings
$data['CODES']['CURRENCY'] = [
    'NAME' => Loc::getMessage('SALE_HPS_JUSAN_CURRENCY'),
    'SORT' => 800,
    'DEFAULT' => [
        'PROVIDER_KEY' => 'PAYMENT',
        'PROVIDER_VALUE' => 'CURRENCY'
    ]
];

// Callback URL for notifications from payment system
$data['CODES']['PS_CHANGE_STATUS_PAY'] = [
    'NAME' => Loc::getMessage('SALE_HPS_JUSAN_PS_CHANGE_STATUS'),
    'SORT' => 900,
    'DEFAULT' => [
        'PROVIDER_KEY' => 'INPUT',
        'PROVIDER_VALUE' => 'Y'
    ]
];

return $data;
