<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            
            // هنجيب أول رول لليوزر من Spatie (عشان نسهلها على الفرونت إند)
            'role' => $this->roles->first()->name ?? null,
            
            // هنا هنجيب رقم التليفون من جدول الـ Student المرتبط باليوزر ده
            'phone' => $this->student->phone ?? null, 

            'is_verified' => !is_null($this->email_verified_at),
            
            // لو عايز ترجع الرولز كلها كمصفوفة بدل رول واحد:
            // 'roles' => $this->roles->pluck('name'),
        ];
    }
}