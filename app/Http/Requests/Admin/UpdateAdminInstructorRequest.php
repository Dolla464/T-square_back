<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminInstructorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'field' => ['sometimes', 'required', 'string', 'max:255'],
            'bio' => ['sometimes', 'required', 'string'],
            'gender' => ['sometimes', 'required', 'in:male,female'],
            'phone' => ['prohibited'], // ممنوع تعديل رقم الهاتف من هنا
            'insta_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'facebook_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
            // جعلنا الـ avatar غير مطلوب (nullable) حتى لا نُجبر المستخدم على رفع صورة جديدة كل مرة يعدل فيها
            'avatar' => ['sometimes','nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'phone.prohibited' => 'Phone number cannot be updated from this endpoint.',
            
            'full_name.required' => 'The full name is required.',
            'field.required' => 'The instructor field/specialty is required.',
            
            'bio.required' => 'The biography is required.',
            
            'gender.required' => 'The gender is required.',
            'gender.in' => 'The gender must be either male or female.',
            
            'insta_url.required' => 'Instagram URL is required.',
            'insta_url.url' => 'Please provide a valid URL for Instagram.',
            
            'linkedin_url.required' => 'LinkedIn URL is required.',
            'linkedin_url.url' => 'Please provide a valid URL for LinkedIn.',
            
            'facebook_url.required' => 'Facebook URL is required.',
            'facebook_url.url' => 'Please provide a valid URL for Facebook.',
            
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be either active or inactive.',
            
            'avatar.image' => 'The uploaded file must be an image.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, png, jpg, webp.',
            'avatar.max' => 'The avatar may not be greater than 2048 kilobytes.',
        ];
    }
}
