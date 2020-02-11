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
use jamesiarmes\PhpEws\ArrayType\ArrayOfStringsType;

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
                        $phones__value->Key === PhoneNumberKeyType::HOME_PHONE_2 ||
                        $phones__value->Key === PhoneNumberKeyType::OTHER_PHONE ||
                        $phones__value->Key === PhoneNumberKeyType::MOBILE_PHONE
                    ) {
                        $phones['private'][] = $phones__value->_;
                    }
                    if (
                        $phones__value->Key === PhoneNumberKeyType::BUSINESS_PHONE ||
                        $phones__value->Key === PhoneNumberKeyType::BUSINESS_PHONE_2 ||
                        $phones__value->Key === PhoneNumberKeyType::COMPANY_MAIN_PHONE ||
                        $phones__value->Key === PhoneNumberKeyType::PAGER
                    ) {
                        $phones['business'][] = $phones__value->_;
                    }
                }
            }
            $contacts[$contacts__key] = [
                'id' => $contacts__value->ItemId->Id,
                'first_name' => @$contacts__value->GivenName != '' ? $contacts__value->GivenName : '',
                'last_name' => @$contacts__value->Surname != '' ? $contacts__value->Surname : '',
                'company_name' => $contacts__value->CompanyName,
                'emails' => $emails,
                'phones' => $phones,
                'url' => $contacts__value->BusinessHomePage,
                'categories' => @$contacts__value->Categories->String != '' ? $contacts__value->Categories->String : [],
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
            $fullname = [];
            if (@$contacts__value['obj']->Surname != '') {
                $fullname[] = trim($contacts__value['obj']->Surname);
            }
            if (@$contacts__value['obj']->GivenName != '') {
                $fullname[] = trim($contacts__value['obj']->GivenName);
            }
            if (empty($fullname) && @$contacts__value['obj']->CompanyName != '') {
                $fullname[] = trim($contacts__value['obj']->CompanyName);
            }
            $fullname = implode(' ', $fullname);
            $fullname = trim($fullname);

            $change = new ItemChangeType();
            $change->ItemId = new ItemIdType();
            $change->ItemId->Id = $contacts__value['obj']->ItemId->Id;
            $change->Updates = new NonEmptyArrayOfItemChangeDescriptionsType();

            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_FILE_AS;
            $field->Contact = new ContactItemType();
            $field->Contact->FileAs = $fullname;
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
            $field->Contact->Subject = $fullname;
            $change->Updates->SetItemField[] = $field;

            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_DISPLAY_NAME;
            $field->Contact = new ContactItemType();
            $field->Contact->DisplayName = $fullname;
            $change->Updates->SetItemField[] = $field;

            if (@$contacts__value['obj']->Surname != '') {
                $field = new SetItemFieldType();
                $field->FieldURI = new PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_SURNAME;
                $field->Contact = new ContactItemType();
                $field->Contact->Surname = trim($contacts__value['obj']->Surname);
                $change->Updates->SetItemField[] = $field;
            }

            if (@$contacts__value['obj']->GivenName != '') {
                $field = new SetItemFieldType();
                $field->FieldURI = new PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_GIVEN_NAME;
                $field->Contact = new ContactItemType();
                $field->Contact->GivenName = trim($contacts__value['obj']->GivenName);
                $change->Updates->SetItemField[] = $field;
            }

            if (@$contacts__value['obj']->CompanyName != '') {
                $field = new SetItemFieldType();
                $field->FieldURI = new PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_COMPANY_NAME;
                $field->Contact = new ContactItemType();
                $field->Contact->CompanyName = trim($contacts__value['obj']->CompanyName);
                $change->Updates->SetItemField[] = $field;
            }

            if (@$contacts__value['obj']->BusinessHomePage != '') {
                $field = new SetItemFieldType();
                $field->FieldURI = new PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_BUSINESS_HOME_PAGE;
                $field->Contact = new ContactItemType();
                $field->Contact->BusinessHomePage = __url_normalize($contacts__value['obj']->BusinessHomePage);
                $change->Updates->SetItemField[] = $field;
            }

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

        if (@$data['first_name'] != '') {
            $contact->GivenName = $data['first_name'];
        }
        if (@$data['last_name'] != '') {
            $contact->Surname = $data['last_name'];
        }
        if (@$data['company_name'] != '') {
            $contact->CompanyName = $data['company_name'];
        }
        if (@$data['url'] != '') {
            $contact->BusinessHomePage = $data['url'];
        }

        if (!empty(@$data['categories'])) {
            $contact->Categories = new ArrayOfStringsType();
            foreach ($data['categories'] as $categories__value) {
                $contact->Categories->String[] = $categories__value;
            }
        }

        if (!empty(@$data['emails'])) {
            $contact->EmailAddresses = new EmailAddressDictionaryType();
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
        }

        if (!empty(@$data['phones'])) {
            $contact->PhoneNumbers = new PhoneNumberDictionaryType();
            foreach ($data['phones'] as $phones__key => $phones__value) {
                foreach ($phones__value as $phones__value__key => $phones__value__value) {
                    $phone = new PhoneNumberDictionaryEntryType();

                    if ($phones__key === 'private') {
                        if ($phones__value__key === 0) {
                            $phone->Key = PhoneNumberKeyType::HOME_PHONE;
                        } elseif ($phones__value__key === 1) {
                            $phone->Key = PhoneNumberKeyType::HOME_PHONE_2;
                        } elseif ($phones__value__key === 2) {
                            $phone->Key = PhoneNumberKeyType::OTHER_PHONE;
                        } elseif ($phones__value__key === 3) {
                            $phone->Key = PhoneNumberKeyType::MOBILE_PHONE;
                        } else {
                            continue;
                        }
                    } elseif ($phones__key === 'business') {
                        if ($phones__value__key === 0) {
                            $phone->Key = PhoneNumberKeyType::BUSINESS_PHONE;
                        } elseif ($phones__value__key === 1) {
                            $phone->Key = PhoneNumberKeyType::BUSINESS_PHONE_2;
                        } elseif ($phones__value__key === 2) {
                            $phone->Key = PhoneNumberKeyType::COMPANY_MAIN_PHONE;
                        } elseif ($phones__value__key === 3) {
                            $phone->Key = PhoneNumberKeyType::PAGER;
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

        if (@$data['url'] != '') {
            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::CONTACTS_BUSINESS_HOME_PAGE;
            $field->Contact = new ContactItemType();
            $field->Contact->BusinessHomePage = $data['url'];
            $change->Updates->SetItemField[] = $field;
        }

        if (!empty(@$data['categories'])) {
            $field = new SetItemFieldType();
            $field->FieldURI = new PathToUnindexedFieldType();
            $field->FieldURI->FieldURI = UnindexedFieldURIType::ITEM_CATEGORIES;
            $field->Contact = new ContactItemType();
            $field->Contact->Categories = new ArrayOfStringsType();
            foreach ($data['categories'] as $categories__value) {
                $field->Contact->Categories->String[] = $categories__value;
            }
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
                [
                    'private' => ['HOME_PHONE', 'HOME_PHONE_2', 'OTHER_PHONE', 'MOBILE_PHONE'],
                    'business' => ['BUSINESS_PHONE', 'BUSINESS_PHONE_2', 'COMPANY_MAIN_PHONE', 'PAGER']
                ]
                as $phones__key => $phones__value
            ) {
                foreach ($phones__value as $phones__value__key => $phones__value__value) {
                    $constant = constant('jamesiarmes\PhpEws\Enumeration\PhoneNumberKeyType::' . $phones__value__value);

                    if (@$data['phones'][$phones__key][$phones__value__key] != '') {
                        $field = new SetItemFieldType();
                        $field->IndexedFieldURI = new PathToIndexedFieldType();
                        $field->IndexedFieldURI->FieldURI = DictionaryURIType::CONTACTS_PHONE_NUMBER;
                        $field->IndexedFieldURI->FieldIndex = $constant;
                        $field->Contact = new ContactItemType();
                        $field->Contact->PhoneNumbers = new PhoneNumberDictionaryType();
                        $entry = new PhoneNumberDictionaryEntryType();
                        $entry->_ = $data['phones'][$phones__key][$phones__value__key];
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

    public function syncContacts($category, $contacts_new)
    {
        $this->normalizeData();

        // get all outlook contacts (in special category)
        $contacts_outlook = $this->getContacts();
        foreach ($contacts_outlook as $contacts_outlook__key => $contacts_outlook__value) {
            if (!in_array($category, $contacts_outlook__value['categories'])) {
                unset($contacts_outlook[$contacts_outlook__key]);
            }
        }

        // get array of contacts that do not exist in new data but in exchange
        $contacts_to_remove = [];
        foreach ($contacts_outlook as $contacts_outlook__value) {
            $to_remove = true;
            foreach ($contacts_new as $contacts_new__value) {
                if ($this->syncContactsIsEqual($contacts_outlook__value, $contacts_new__value)) {
                    $to_remove = false;
                    break;
                }
            }
            if ($to_remove === true) {
                $contacts_to_remove[] = $contacts_outlook__value['id'];
            }
        }

        // get array of contacts that do not exist in exchange but in new data
        $contacts_to_create = [];
        foreach ($contacts_new as $contacts_new__value) {
            $to_create = true;
            foreach ($contacts_outlook as $contacts_outlook__value) {
                if ($this->syncContactsIsEqual($contacts_outlook__value, $contacts_new__value)) {
                    $to_create = false;
                    break;
                }
            }
            if ($to_create === true) {
                $contacts_to_create[] = $contacts_new__value;
            }
        }

        // finally remove
        foreach ($contacts_to_remove as $contacts_to_remove__value) {
            $this->removeContact($contacts_to_remove__value);
        }

        // finally create
        foreach ($contacts_to_create as $contacts_to_create__value) {
            $this->addContact($contacts_to_create__value);
        }

        return [
            'success' => true,
            'message' => null,
            'data' => [
                'deleted' => count($contacts_to_remove),
                'created' => count($contacts_to_create)
            ]
        ];
    }

    private function syncContactsIsEqual($a, $b)
    {
        foreach (['a', 'b'] as $contact) {
            if (@${$contact}['id'] != '') {
                unset(${$contact}['id']);
            }
            if (@${$contact}['obj'] != '') {
                unset(${$contact}['obj']);
            }

            foreach (${$contact}['phones'] as $phones__key => $phones__value) {
                foreach ($phones__value as $phones__value__key => $phones__value__value) {
                    ${$contact}['phones'][$phones__key][$phones__value__key] = __phone_normalize($phones__value__value);
                }
                ${$contact}['phones'][$phones__key] = array_slice(${$contact}['phones'][$phones__key], 0, 4);
            }

            ${$contact}['emails'] = array_slice(${$contact}['emails'], 0, 3);

            sort(${$contact}['emails']);
            sort(${$contact}['phones']['private']);
            sort(${$contact}['phones']['business']);
        }

        return $a == $b;
    }
}
