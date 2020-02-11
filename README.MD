# 📇 ewshelper 📇

ewshelper is a little wrapper around [php-ews](https://github.com/jamesiarmes/php-ews) and helps you manage your exchange contacts via php.\
it can handle very big lists also helps you normalize your contacts data.

## installation

install once with [composer](https://getcomposer.org/):

```
composer require vielhuber/ewshelper
```

then add this to your files:

```php
require __DIR__ . '/vendor/autoload.php';
use vielhuber\ewshelper\ewshelper;
$ewshelper = new ewshelper('**host**', '**username**', '**password**');
```

## usage

#### get all contacts

```php
$ewshelper->getContacts();
```

#### get contact by id

```php
$ewshelper->getContact('**id**');
```

#### normalize name and phones

```php
$ewshelper->normalizeData();
```

#### add a new contact

```php
$ewshelper->addContact([
    'first_name' => 'Max',
    'last_name' => 'Mustermann',
    'company_name' => 'Musterfirma',
    'emails' => ['max@mustermann.de'],
    'phones' => ['private' => ['0123456789'], 'business' => ['9876543210']],
    'url' => 'https://www.mustermann.de',
    'categories' => ['test']
]);
```

#### update a contact

```php
$ewshelper->updateContact('**id**', [
    'first_name' => 'Max',
    'last_name' => 'Mustermann',
    'company_name' => 'Musterfirma',
    'emails' => ['max@mustermann.de'],
    'phones' => ['private' => ['0123456789'], 'business' => ['9876543210']],
    'url' => 'https://www.mustermann.de',
    'categories' => ['test']
]);
```

#### remove a contact

```php
$ewshelper->removeContact('**id**');
```

#### sync contacts

the following helper function gets all exchange contacts in category `test`,\
deletes those that are not present in the provided array and creates those that don't exist yet in exchange.

```php
$ewshelper->syncContacts('test', [
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
    ]
]);
```

## note on outlook

when deleting phone numbers or making a lot of changes to existing contacts,\
it is recommended to use the function [clear offline items](https://www.extendoffice.com/documents/outlook/1749-outlook-clear-offline-items-undo.html).
