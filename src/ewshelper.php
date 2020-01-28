<?php
namespace vielhuber\ewshelper;

use jamesiarmes\PhpEws\Client;
use jamesiarmes\PhpEws\Request\FindItemType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;
use jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use jamesiarmes\PhpEws\Type\ContactsViewType;
use jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use jamesiarmes\PhpEws\Enumeration\ItemQueryTraversalType;
use jamesiarmes\PhpEws\Enumeration\IndexBasePointType;
use jamesiarmes\PhpEws\Type\IndexedPageViewType;
use jamesiarmes\PhpEws\Request\UpdateItemType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfItemChangeDescriptionsType;
use jamesiarmes\PhpEws\Enumeration\ConflictResolutionType;
use jamesiarmes\PhpEws\Type\ItemChangeType;
use jamesiarmes\PhpEws\Type\ItemIdType;
use jamesiarmes\PhpEws\Type\SetItemFieldType;
use jamesiarmes\PhpEws\Type\ContactItemType;
use jamesiarmes\PhpEws\Type\PathToIndexedFieldType;
use jamesiarmes\PhpEws\Type\PathToUnindexedFieldType;
use jamesiarmes\PhpEws\Enumeration\UnindexedFieldURIType;
use jamesiarmes\PhpEws\Request\CreateItemType;
use jamesiarmes\PhpEws\Enumeration\BodyTypeType;
use jamesiarmes\PhpEws\Enumeration\EmailAddressKeyType;
use jamesiarmes\PhpEws\Enumeration\FileAsMappingType;
use jamesiarmes\PhpEws\Enumeration\MapiPropertyTypeType;
use jamesiarmes\PhpEws\Enumeration\PhoneNumberKeyType;
use jamesiarmes\PhpEws\Enumeration\PhysicalAddressKeyType;
use jamesiarmes\PhpEws\Type\BodyType;
use jamesiarmes\PhpEws\Type\EmailAddressDictionaryEntryType;
use jamesiarmes\PhpEws\Type\EmailAddressDictionaryType;
use jamesiarmes\PhpEws\Type\CompleteNameType;
use jamesiarmes\PhpEws\Type\ExtendedPropertyType;
use jamesiarmes\PhpEws\Type\PathToExtendedFieldType;
use jamesiarmes\PhpEws\Type\PhoneNumberDictionaryEntryType;
use jamesiarmes\PhpEws\Type\PhysicalAddressDictionaryEntryType;
use jamesiarmes\PhpEws\Type\PhoneNumberDictionaryType;
use jamesiarmes\PhpEws\Request\DeleteItemType;
use jamesiarmes\PhpEws\Enumeration\DisposalType;
use jamesiarmes\PhpEws\Enumeration\DictionaryURIType;
use jamesiarmes\PhpEws\Type\DeleteItemFieldType;

class ewshelper
{
    private $client = null;

    public function __construct($host, $username, $password)
    {
        $this->client = new Client($host, $username, $password);
        $this->client->setCurlOptions([CURLOPT_SSL_VERIFYPEER => false]);
    }

    public function debugRequest()
    {
        return @$this->client->getClient()->__last_request;
    }

    public function getContact($id)
    {
        $contacts = $this->getContacts($id);
        if (!empty($contacts)) {
            return $contacts[0];
        }
        return [];
    }

    public function getContacts($id = null)
    {
        $limit = 1000;
        $request = new FindItemType();
        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $request->ContactsView = new ContactsViewType();
        $request->IndexedPageItemView = new IndexedPageViewType();
        $request->IndexedPageItemView->BasePoint = IndexBasePointType::BEGINNING;
        $request->IndexedPageItemView->Offset = 0;
        $request->IndexedPageItemView->MaxEntriesReturned = $limit;
        $folder_id = new DistinguishedFolderIdType();
        $folder_id->Id = DistinguishedFolderIdNameType::CONTACTS;
        $request->ParentFolderIds->DistinguishedFolderId[] = $folder_id;
        $request->Traversal = ItemQueryTraversalType::SHALLOW;
        $response = $this->client->FindItem($request);

        $contacts = [];
        foreach ($response->ResponseMessages->FindItemResponseMessage as $response_message) {
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
                continue;
            }
            $contacts = array_merge($contacts, $response_message->RootFolder->Items->Contact);
            $last_page = $response_message->RootFolder->IncludesLastItemInRange;
            $page_number = 1;
            while (!$last_page) {
                $request->IndexedPageItemView->Offset = $limit * $page_number;
                $response = $this->client->FindItem($request);
                foreach ($response->ResponseMessages->FindItemResponseMessage as $response_message_this) {
                    $contacts = array_merge($contacts, $response_message_this->RootFolder->Items->Contact);
                }
                $last_page = $response_message_this->RootFolder->IncludesLastItemInRange;
                $page_number++;
            }
        }

        if ($id !== null) {
            foreach ($contacts as $contacts__key => $contacts__value) {
                if ($contacts__value->ItemId->Id !== $id) {
                    unset($contacts[$contacts__key]);
                }
            }
            $contacts = array_values($contacts);
        }

        foreach ($contacts as $contacts__key => $contacts__value) {
            $emails = [];
            if (!empty(@$contacts__value->EmailAddresses->Entry)) {
                foreach ($contacts__value->EmailAddresses->Entry as $emails__value) {
                    $emails[] = $emails__value->_;
                }
            }
            $phones = ['private' => [], 'business' => []];
            if (!empty(@$contacts__value->PhoneNumbers->Entry)) {
                foreach ($contacts__value->PhoneNumbers->Entry as $phones__value) {
                    if (
                        $phones__value->Key === PhoneNumberKeyType::HOME_PHONE ||
                        $phones__value->Key === PhoneNumberKeyType::HOME_PHONE_2
                    ) {
                        $phones['private'][] = $phones__value->_;
                    }
                    if (
                        $phones__value->Key === PhoneNumberKeyType::BUSINESS_PHONE ||
                        $phones__value->Key === PhoneNumberKeyType::BUSINESS_PHONE_2
                    ) {
                        $phones['business'][] = $phones__value->_;
                    }
                }
            }
            $contacts[$contacts__key] = [
                'id' => $contacts__value->ItemId->Id,
                'first_name' => $contacts__value->CompleteName->FirstName,
                'last_name' => $contacts__value->CompleteName->LastName,
                'company_name' => $contacts__value->CompanyName,
                'emails' => $emails,
                'phones' => $phones,
                'obj' => $contacts__value
            ];
        }

        return $contacts;
    }

    public function normalizeData($id = null)
    {
        $request = new UpdateItemType();
        $request->ConflictResolution = ConflictResolutionType::ALWAYS_OVERWRITE;

        if ($id === null) {
            $contacts = $this->getContacts();
        } else {
            $contacts = [$this->getContact($id)];
        }
        foreach ($contacts as $contacts__value) {
            $name = trim(
                $contacts__value['obj']->CompleteName->LastName . ' ' . $contacts__value['obj']->CompleteName->FirstName
            );

            $change = new ItemChangeType();
            $change->ItemId = new ItemIdType();
            $change->ItemId->Id = $contacts__value['obj']->ItemId->Id;
            $change->Updates = new NonEmptyArrayOfItemChangeDescriptionsType();

            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_FILE_AS;
            $field->Contact = new ContactItemType();
            $field->Contact->FileAs = $name;
            $change->Updates->SetItemField[] = $field;

            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_FILE_AS_MAPPING;
            $field->Contact = new ContactItemType();
            $field->Contact->FileAsMapping = FileAsMappingType::NONE;
            $change->Updates->SetItemField[] = $field;

            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::ITEM_SUBJECT;
            $field->Contact = new ContactItemType();
            $field->Contact->Subject = $name;
            $change->Updates->SetItemField[] = $field;

            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_DISPLAY_NAME;
            $field->Contact = new ContactItemType();
            $field->Contact->DisplayName = $name;
            $change->Updates->SetItemField[] = $field;

            if (!empty(@$contacts__value['obj']->PhoneNumbers->Entry)) {
                foreach ($contacts__value['obj']->PhoneNumbers->Entry as $phones__value) {
                    if (trim(@$phones__value->_) == '') {
                        continue;
                    }
                    $field = new SetItemFieldType();
                    $field->IndexedFieldURI = new PathToIndexedFieldType();
                    $field->IndexedFieldURI->FieldURI = DictionaryURIType::CONTACTS_PHONE_NUMBER;
                    $field->IndexedFieldURI->FieldIndex = $phones__value->Key;
                    $field->Contact = new ContactItemType();
                    $field->Contact->PhoneNumbers = new PhoneNumberDictionaryType();

                    $entry = new PhoneNumberDictionaryEntryType();
                    $entry->_ = __phone_normalize($phones__value->_);
                    $entry->Key = $phones__value->Key;
                    $field->Contact->PhoneNumbers->Entry[] = $entry;
                    $change->Updates->SetItemField[] = $field;
                }
            }

            $request->ItemChanges[] = $change;
        }

        $response = $this->client->UpdateItem($request);

        $response_messages = $response->ResponseMessages->UpdateItemResponseMessage;
        foreach ($response_messages as $response_messages__value) {
            if ($response_messages__value->ResponseClass !== ResponseClassType::SUCCESS) {
                return [
                    'success' => false,
                    'message' => $response_messages__value->MessageText
                ];
            }
        }
        return [
            'success' => true,
            'message' => null
        ];
    }

    public function addContact($data)
    {
        $request = new CreateItemType();
        $contact = new ContactItemType();
        $contact->GivenName = $data['first_name'];
        $contact->Surname = $data['last_name'];
        $contact->CompanyName = $data['company_name'];
        $contact->EmailAddresses = new EmailAddressDictionaryType();
        $contact->PhoneNumbers = new PhoneNumberDictionaryType();

        foreach ($data['emails'] as $emails__key => $emails__value) {
            $email = new EmailAddressDictionaryEntryType();
            if ($emails__key === 0) {
                $email->Key = EmailAddressKeyType::EMAIL_ADDRESS_1;
            } elseif ($emails__key === 1) {
                $email->Key = EmailAddressKeyType::EMAIL_ADDRESS_2;
            } elseif ($emails__key === 2) {
                $email->Key = EmailAddressKeyType::EMAIL_ADDRESS_3;
            } else {
                continue;
            }
            $email->_ = $emails__value;
            $contact->EmailAddresses->Entry[] = $email;
        }

        foreach ($data['phones'] as $phones__key => $phones__value) {
            foreach ($phones__value as $phones__value__key => $phones__value__value) {
                $phone = new PhoneNumberDictionaryEntryType();

                if ($phones__key === 'private') {
                    if ($phones__value__key === 0) {
                        $phone->Key = PhoneNumberKeyType::HOME_PHONE;
                    } elseif ($phones__value__key === 1) {
                        $phone->Key = PhoneNumberKeyType::HOME_PHONE_2;
                    } else {
                        continue;
                    }
                } elseif ($phones__key === 'business') {
                    if ($phones__value__key === 0) {
                        $phone->Key = PhoneNumberKeyType::BUSINESS_PHONE;
                    } elseif ($phones__value__key === 1) {
                        $phone->Key = PhoneNumberKeyType::BUSINESS_PHONE_2;
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }

                $phone->_ = $phones__value__value;
                $contact->PhoneNumbers->Entry[] = $phone;
            }
        }

        $contact->FileAsMapping = FileAsMappingType::FIRST_SPACE_LAST;

        $request->Items->Contact[] = $contact;
        $response = $this->client->CreateItem($request);

        $id = $response->ResponseMessages->CreateItemResponseMessage[0]->Items->Contact[0]->ItemId->Id;
        $this->normalizeData($id);

        return [
            'success' =>
                $response->ResponseMessages->CreateItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS,
            'message' => @$response->ResponseMessages->CreateItemResponseMessage[0]->MessageText,
            'data' => ['id' => $id]
        ];
    }

    public function removeContact($id)
    {
        $request = new DeleteItemType();
        $request->DeleteType = DisposalType::HARD_DELETE;
        $request->ItemIds = (object) [];
        $request->ItemIds->ItemId = new ItemIdType();
        $request->ItemIds->ItemId->Id = $id;
        $response = $this->client->DeleteItem($request);

        return [
            'success' =>
                $response->ResponseMessages->DeleteItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS,
            'message' => @$response->ResponseMessages->DeleteItemResponseMessage[0]->MessageText
        ];
    }

    public function updateContact($id, $data)
    {
        $request = new UpdateItemType();
        $request->ConflictResolution = ConflictResolutionType::ALWAYS_OVERWRITE;

        $change = new ItemChangeType();
        $change->ItemId = new ItemIdType();
        $change->ItemId->Id = $id;
        $change->Updates = new NonEmptyArrayOfItemChangeDescriptionsType();

        if (@$data['first_name'] != '') {
            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_GIVEN_NAME;
            $field->Contact = new ContactItemType();
            $field->Contact->GivenName = $data['first_name'];
            $change->Updates->SetItemField[] = $field;
        }

        if (@$data['last_name'] != '') {
            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_SURNAME;
            $field->Contact = new ContactItemType();
            $field->Contact->Surname = $data['last_name'];
            $change->Updates->SetItemField[] = $field;
        }

        if (@$data['company_name'] != '') {
            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_COMPANY_NAME;
            $field->Contact = new ContactItemType();
            $field->Contact->CompanyName = $data['company_name'];
            $change->Updates->SetItemField[] = $field;
        }

        if (!empty(@$data['emails'])) {
            foreach (['EMAIL_ADDRESS_1', 'EMAIL_ADDRESS_2', 'EMAIL_ADDRESS_3'] as $emails__key => $email__value) {
                $constant = constant('jamesiarmes\PhpEws\Enumeration\EmailAddressKeyType::' . $email__value);

                if (@$data['emails'][$emails__key] != '') {
                    $field = new SetItemFieldType();
                    $field->IndexedFieldURI = new PathToIndexedFieldType();
                    $field->IndexedFieldURI->FieldURI = DictionaryURIType::CONTACTS_EMAIL_ADDRESS;
                    $field->IndexedFieldURI->FieldIndex = $constant;
                    $field->Contact = new ContactItemType();
                    $field->Contact->EmailAddresses = new EmailAddressDictionaryType();
                    $entry = new EmailAddressDictionaryEntryType();
                    $entry->_ = $data['emails'][$emails__key];
                    $entry->Key = $constant;
                    $field->Contact->EmailAddresses->Entry[] = $entry;
                    $change->Updates->SetItemField[] = $field;
                } else {
                    $field = new DeleteItemFieldType();
                    $field->IndexedFieldURI = new PathToIndexedFieldType();
                    $field->IndexedFieldURI->FieldURI = DictionaryURIType::CONTACTS_EMAIL_ADDRESS;
                    $field->IndexedFieldURI->FieldIndex = $constant;
                    $change->Updates->DeleteItemField[] = $field;
                }
            }
        }

        if (!empty(@$data['phones'])) {
            foreach (
                ['HOME_PHONE', 'HOME_PHONE_2', 'BUSINESS_PHONE', 'BUSINESS_PHONE_2']
                as $phones__key => $phones__value
            ) {
                $constant = constant('jamesiarmes\PhpEws\Enumeration\PhoneNumberKeyType::' . $phones__value);

                if (
                    @$data['phones'][strpos($phones__value, 'HOME') !== false ? 'private' : 'business'][
                        $phones__key % 2
                    ] != ''
                ) {
                    $field = new SetItemFieldType();
                    $field->IndexedFieldURI = new PathToIndexedFieldType();
                    $field->IndexedFieldURI->FieldURI = DictionaryURIType::CONTACTS_PHONE_NUMBER;
                    $field->IndexedFieldURI->FieldIndex = $constant;
                    $field->Contact = new ContactItemType();
                    $field->Contact->PhoneNumbers = new PhoneNumberDictionaryType();
                    $entry = new PhoneNumberDictionaryEntryType();
                    $entry->_ =
                        $data['phones'][strpos($phones__value, 'HOME') !== false ? 'private' : 'business'][
                            $phones__key % 2
                        ];
                    $entry->Key = $constant;
                    $field->Contact->PhoneNumbers->Entry[] = $entry;
                    $change->Updates->SetItemField[] = $field;
                } else {
                    $field = new DeleteItemFieldType();
                    $field->IndexedFieldURI = new PathToIndexedFieldType();
                    $field->IndexedFieldURI->FieldURI = DictionaryURIType::CONTACTS_PHONE_NUMBER;
                    $field->IndexedFieldURI->FieldIndex = $constant;
                    $change->Updates->DeleteItemField[] = $field;
                }
            }
        }

        $request->ItemChanges[] = $change;

        $response = $this->client->UpdateItem($request);

        $this->normalizeData($id);

        $response_messages = $response->ResponseMessages->UpdateItemResponseMessage;
        foreach ($response_messages as $response_messages__value) {
            if ($response_messages__value->ResponseClass !== ResponseClassType::SUCCESS) {
                return [
                    'success' => false,
                    'message' => $response_messages__value->MessageText
                ];
            }
        }
        return [
            'success' => true,
            'message' => null
        ];
    }
}
