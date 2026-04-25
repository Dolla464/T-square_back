<?php

use Illuminate\Support\Facades\Auth;
use App\Models\User;

$user = User::first();
echo "User: " . ($user ? $user->email : 'None') . "\n";
