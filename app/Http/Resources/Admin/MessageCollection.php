<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MessageCollection extends ResourceCollection
{
    public static $wrap = 'messages';

    /**
     * Each item in the collection is transformed via MessageIndexResource.
     */
    public $collects = MessageIndexResource::class;

    public function toArray(Request $request): array
    {
        return [
            'messages' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
