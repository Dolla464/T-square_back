<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id'            => $this->id,
            'exam_id'       => (int) $this->exam_id,
            'question_text' => $this->question_text,
            'marks'         => (float) $this->marks,

            // Get the choices associated with the question automatically
            'choices'       => $this->choices->map(function ($choice) {
                return [
                    'id'          => $choice->id,
                    'choice_text' => $choice->choice_text,
                    'is_correct'  => (bool) $choice->is_correct,
                ];
            }),
        ];

        if ($request->routeIs('*.trash')) {
            $data['deleted_at'] = $this->deleted_at ? $this->deleted_at->format('Y-m-d H:i') : null;
        }

        return $data;
    }
}
