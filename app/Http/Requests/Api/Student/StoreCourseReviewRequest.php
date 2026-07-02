<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseReviewRequest extends FormRequest
{
    private const COURSE_QUESTION_IDS = [
        'course_organization',
        'course_materials',
        'course_difficulty',
        'course_assessments',
        'course_practical_skills',
    ];

    private const CENTER_QUESTION_IDS = [
        'center_facilities',
        'center_location',
        'center_staff',
        'center_environment',
        'center_platform',
    ];

    private const INSTRUCTOR_QUESTION_IDS = [
        'instructor_knowledge',
        'instructor_clarity',
        'instructor_responsive',
        'instructor_engaging',
        'instructor_fair',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->student()->exists();
    }

    public function rules(): array
    {
        $ratingRule = ['required', 'integer', 'min:1', 'max:5'];

        $rules = [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'overall_comment' => ['required', 'string', 'min:10', 'max:5000'],
            'ratings' => ['required', 'array'],
        ];

        foreach (array_merge(
            self::COURSE_QUESTION_IDS,
            self::CENTER_QUESTION_IDS,
            self::INSTRUCTOR_QUESTION_IDS
        ) as $questionId) {
            $rules["ratings.{$questionId}"] = $ratingRule;
        }

        return $rules;
    }

    public static function courseQuestionIds(): array
    {
        return self::COURSE_QUESTION_IDS;
    }

    public static function centerQuestionIds(): array
    {
        return self::CENTER_QUESTION_IDS;
    }

    public static function instructorQuestionIds(): array
    {
        return self::INSTRUCTOR_QUESTION_IDS;
    }
}
