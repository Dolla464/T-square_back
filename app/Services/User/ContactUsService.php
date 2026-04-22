<?php

namespace App\Services\User;

use App\Http\Resources\User\ContactUs\ContactUsResource;
use App\Models\ContactUs;

class ContactUsService
{
    public function __construct(
        private readonly ContactUs $contactUs
    ) {}

    public function store(array $data): ContactUsResource
    {
        $contact = $this->contactUs->create($data);

        return new ContactUsResource($contact);
    }
}
