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
    'phones' => ['private' => ['0123456789'], 'business' => ['9876543210']]
]);
```

#### update a contact

```php
$ewshelper->updateContact('**id**', [
    'first_name' => 'Max',
    'last_name' => 'Mustermann',
    'company_name' => 'Musterfirma',
    'emails' => ['max@mustermann.de'],
    'phones' => ['private' => ['0123456789'], 'business' => ['9876543210']]
]);
```

#### remove a contact

```php
$ewshelper->removeContact('**id**');
```
