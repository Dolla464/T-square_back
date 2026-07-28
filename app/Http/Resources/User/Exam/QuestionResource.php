<?php

namespace App\Http\Resources\User\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'question_image' => $this->question_image_url,
            'question_code' => $this->question_code,
            'question_code_language' => $this->question_code_language,
            'marks' => $this->marks,
            // بنبعت الاختيارات بس بنشيل منها عمود is_correct للأمان
            'choices' => $this->choices->map(function ($choice) {
                return [
                    'id' => $choice->id,
                    'choice_text' => $choice->choice_text,
                ];
            }),
        ];
    }
}
