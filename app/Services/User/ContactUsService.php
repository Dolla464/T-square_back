<?php

namespace App\Services\User;

use App\Http\Resources\User\ContactUs\ContactUsResource;
use App\Models\Message;

class ContactUsService
{
    public function __construct(
        private readonly Message $message
    ) {}

    public function store(array $data): ContactUsResource
    {
        $message = $this->message->create($data);

        return new ContactUsResource($message);
    }
}
