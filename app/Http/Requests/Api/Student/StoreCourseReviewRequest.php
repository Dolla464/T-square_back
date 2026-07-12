<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        $hasPerInstructorRatings = is_array($this->input('instructor_ratings'))
            && count($this->input('instructor_ratings')) > 0;

        $rules = [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'overall_comment' => ['required', 'string', 'min:10', 'max:5000'],
            'ratings' => ['required', 'array'],
            'instructor_ratings' => ['sometimes', 'array'],
            'instructor_ratings.*.course_instructor_id' => ['required_with:instructor_ratings', 'integer', 'exists:course_instructor,id'],
            'instructor_ratings.*.ratings' => ['required_with:instructor_ratings', 'array'],
        ];

        foreach (self::COURSE_QUESTION_IDS as $questionId) {
            $rules["ratings.{$questionId}"] = $ratingRule;
        }

        foreach (self::CENTER_QUESTION_IDS as $questionId) {
            $rules["ratings.{$questionId}"] = $ratingRule;
        }

        if (! $hasPerInstructorRatings) {
            foreach (self::INSTRUCTOR_QUESTION_IDS as $questionId) {
                $rules["ratings.{$questionId}"] = $ratingRule;
            }
        } else {
            foreach (self::INSTRUCTOR_QUESTION_IDS as $questionId) {
                $rules["instructor_ratings.*.ratings.{$questionId}"] = $ratingRule;
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $entries = $this->input('instructor_ratings', []);
            if (! is_array($entries) || $entries === []) {
                return;
            }

            $ids = collect($entries)->pluck('course_instructor_id')->filter();
            if ($ids->duplicates()->isNotEmpty()) {
                $v->errors()->add('instructor_ratings', 'Duplicate instructor ratings are not allowed.');
            }
        });
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
