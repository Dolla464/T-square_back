<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id'                 => $this->id,
            'title'              => $this->title,
            'duration_minutes'   => (int) $this->duration,
            'is_active'          => (bool) $this->is_active,
            'questions_count'    => (int) $this->questions_count,

            // Get the course name safely if the relation is loaded
            'course' => $this->whenLoaded('course', function () {
                return [
                    'id'   => $this->course->id,
                    'title' => $this->course->title,
                ];
            }),
        ];
         // Add the additional columns that we need for the show only (the full details)
        // Check if the current route is the show route or if we are not returning a collection
        if ($request->routeIs('*.show') || $request->get('detailed') == true) {
            $data['description']       = $this->description;
            $data['total_marks']       = (float) $this->total_marks;
            $data['passing_mark']      = (float) $this->passing_mark;
            $data['max_attempts']      = (int) $this->max_attempts;
            $data['is_final']          = (bool) $this->is_final;
            $data['shuffle_questions'] = (bool) $this->shuffle_questions;
            $data['created_at']        = $this->created_at->format('Y-m-d H:i');
        }

        if ($request->routeIs('*.trash')) {
            $data['deleted_at'] = $this->deleted_at ? $this->deleted_at->format('Y-m-d H:i') : null;
        }

        return $data;
    }
}
