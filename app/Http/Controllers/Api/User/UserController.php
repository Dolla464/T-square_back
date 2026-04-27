<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of all users.
     */
    public function index()
    {
        $users = User::paginate(15);
        return $this->successResponse(
            UserResource::collection($users),
            'تم جلب المستخدمين بنجاح'
        );
    }

    /**
     * Display a specific user by ID.
     */
    public function show(User $user)
    {
        return $this->successResponse(
            new UserResource($user),
            'تم جلب المستخدم بنجاح'
        );
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $data = $request->validated();
            $data['password'] = Hash::make($data['password']);

            $user = User::create($data);

            return $this->successResponse(
                new UserResource($user),
                'تم إنشاء المستخدم بنجاح',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified user in storage.
     * Note: Email and ID cannot be modified
     */
    public function update(UpdateUserRequest $request, User $user)
    {
         try {
            $data = $request->validated();

            // إذا كانت البيانات المرسلة فارغة
            if (empty($data)) {
                return $this->successResponse(
                    new UserResource($user),
                    'لم يتم إرسال بيانات للتحديث'
                );
            }

            // تجاهل القيم الفارغة أو القيم التي لم تتغير
            $data = array_filter($data, function ($value, $key) use ($user) {
                if ($value === null || $value === '') {
                    return false;
                }

                if ($key === 'password') {
                    return !Hash::check($value, $user->password);
                }

                return $user->{$key} !== $value;
            }, ARRAY_FILTER_USE_BOTH);

            if (empty($data)) {
                return $this->successResponse(
                    new UserResource($user),
                    'لا يوجد تغييرات للتحديث'
                );
            }

            // تشفير كلمة المرور فقط لو كانت متغيرة فعلا
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // تطبيق التحديثات
            $user->update($data);
            
            // إعادة تحميل البيانات من DB
            $user->refresh();

            return $this->successResponse(
                new UserResource($user),
                'تم تحديث المستخدم بنجاح'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        try {
            $user->delete();

            return $this->successResponse(
                null,
                'تم حذف المستخدم بنجاح'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(int $id)
    {
        try {
            $user = User::onlyTrashed()->findOrFail($id);
            $user->restore();

            return $this->successResponse(
                new UserResource($user),
                'تم استعادة المستخدم بنجاح'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Permanently delete a user.
     */
    public function forceDelete(int $id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);

            $user->forceDelete();

            return $this->successResponse(
                null,
                'تم حذف المستخدم بشكل نهائي'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Search users by name or email.
     */
    public function search(Request $request)
    {
        try {
            $query = $request->input('q');

            if (!$query || strlen($query) < 2) {
                return $this->errorResponse('يجب إدخال على الأقل حرفين للبحث', 400);
            }

            $users = User::where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->paginate(15);

            return $this->successResponse(
                UserResource::collection($users),
                'نتائج البحث'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
