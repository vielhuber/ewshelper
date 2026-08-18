<?php
declare(strict_types=1);

namespace vielhuber\ewshelper\tests;

use jamesiarmes\PhpEws\Client;
use jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use jamesiarmes\PhpEws\Type\ContactItemType;
use jamesiarmes\PhpEws\Type\ItemIdType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use vielhuber\ewshelper\ewshelper;

final class Test extends TestCase
{
    public function testContactIsMapped(): void
    {
        $contact = new ContactItemType();
        $contact->ItemId = new ItemIdType();
        $contact->ItemId->Id = 'contact-1';
        $contact->GivenName = 'Test';
        $contact->Surname = 'User';

        $response = (object) [
            'ResponseMessages' => (object) [
                'FindItemResponseMessage' => [
                    (object) [
                        'ResponseClass' => ResponseClassType::SUCCESS,
                        'RootFolder' => (object) [
                            'Items' => (object) ['Contact' => [$contact]],
                            'IncludesLastItemInRange' => true
                        ]
                    ]
                ]
            ]
        ];
        $client = $this->createStub(Client::class);
        $client->method('FindItem')->willReturn($response);
        $reflection = new ReflectionClass(ewshelper::class);
        $helper = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('client')->setValue($helper, $client);

        $contacts = $helper->getContacts();

        $this->assertSame('contact-1', $contacts[0]['id']);
        $this->assertSame('Test', $contacts[0]['first_name']);
        $this->assertSame('User', $contacts[0]['last_name']);
    }
}
