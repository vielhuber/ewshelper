<?php
require_once __DIR__ . '/vendor/autoload.php';
use vielhuber\ewshelper\ewshelper;
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$ewshelper = new ewshelper(@$_SERVER['EWS_HOST'], @$_SERVER['EWS_USERNAME'], @$_SERVER['EWS_PASSWORD']);

$switch = @$_GET['switch'];

echo '<pre>';

if ($switch == 1) {
    $contacts = $ewshelper->getContacts();
    foreach ($contacts as $contacts__value) {
        var_dump($contacts__value);
    }
}

if ($switch == 2) {
    $response = $ewshelper->getContact(
        'AAMkAGI4NWMxMGIzLTQ5MTctNGYyNy1hY2YzLWQ1YmZmMTA5ZjI5NgBGAAAAAADwZNIaQJ0wSJhwQ+Ev0+N8BwD9iZ7Ufh2ZQ6EkqgYz5YriAAABaHWVAADGiw/HQBXsRpCB1hsLE6h3AAUitU5XAAA='
    );
    var_dump($response);
}

if ($switch == 3) {
    $response = $ewshelper->normalizeData();
    var_dump($response);
}

if ($switch == 4) {
    $response = $ewshelper->addContact([
        'first_name' => '_DIES IST',
        'last_name' => '_EIN TEST',
        'company_name' => 'TESTFIRMA',
        'emails' => ['david@vielhuber.de'],
        'phones' => [
            'private' => ['+4915158754691', '+4915158754691', '+4915158754691', '+4915158754691'],
            'business' => ['+4989546564', '+4915158754691', '+4915158754691', '+4989546564']
        ],
        'url' => 'https://www.mustermann.de',
        'categories' => ['test']
    ]);
    var_dump($response);
}

if ($switch == 5) {
    $response = $ewshelper->updateContact(
        'AAMkAGI4NWMxMGIzLTQ5MTctNGYyNy1hY2YzLWQ1YmZmMTA5ZjI5NgBGAAAAAADwZNIaQJ0wSJhwQ+Ev0+N8BwD9iZ7Ufh2ZQ6EkqgYz5YriAAABaHWVAADGiw/HQBXsRpCB1hsLE6h3AAD8XlYoAAA=',
        [
            'first_name' => 'Felix',
            'last_name' => 'Alcala',
            'company_name' => 'Agilebytes',
            'emails' => ['felix.alcala@agilebytes.de'],
            'phones' => ['private' => ['08921558216'], 'business' => ['+49/1732658121999']],
            'url' => 'https://www.mustermann.de',
            'categories' => ['test']
        ]
    );
    var_dump($response);
}

if ($switch == 6) {
    $response = $ewshelper->removeContact(
        'AAMkAGI4NWMxMGIzLTQ5MTctNGYyNy1hY2YzLWQ1YmZmMTA5ZjI5NgBGAAAAAADwZNIaQJ0wSJhwQ+Ev0+N8BwD9iZ7Ufh2ZQ6EkqgYz5YriAAABaHWVAADGiw/HQBXsRpCB1hsLE6h3AAUitU4ZAAA='
    );
    var_dump($response);
}

if ($switch == 7) {
    $response = $ewshelper->syncContacts('test', [
        [
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'company_name' => 'Musterfirma',
            'emails' => ['max@mustermann.de'],
            'phones' => ['private' => ['0123456789'], 'business' => ['9876543210']],
            'url' => 'https://www.mustermann.de',
            'categories' => ['test']
        ],
        [
            'first_name' => 'Erika',
            'last_name' => 'Mustermann',
            'company_name' => 'Musterfirma',
            'emails' => ['erika@mustermann.de'],
            'phones' => ['private' => ['0123456789'], 'business' => ['9876543210']],
            'url' => 'https://www.mustermann.de',
            'categories' => ['test']
        ],
        [
            'first_name' => 'David',
            'last_name' => 'Mustermann',
            'company_name' => 'Musterfirma',
            'emails' => [
                'erika@mustermann.de1',
                'erika@mustermann.de2',
                'erika@mustermann.de3',
                'erika@mustermann.de4',
                'erika@mustermann.de5'
            ],
            'phones' => [
                'private' => ['0123456781', '0123456782', '0123456783', '0123456784', '0123456785'],
                'business' => ['0123456781', '0123456782', '0123456783', '0123456784', '0123456785']
            ],
            'url' => 'https://www.mustermann.de',
            'categories' => ['test']
        ]
    ]);
    var_dump($response);
}

if ($switch == 8) {
    $response = $ewshelper->removeDuplicates();
    var_dump($response);
}
