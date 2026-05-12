<?php

use App\Models\User;

$user = User::first();
echo 'User: '.($user ? $user->email : 'None')."\n";
