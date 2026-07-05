<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;

/**
 * @tags Admin: Tags
 */
class AdminTagController extends Controller
{
    public function index()
    {
        $tags = Tag::select('id', 'name')->get();

        return $this->successResponse(
            $tags,
            'Tags retrieved successfully'
        );
    }
}
