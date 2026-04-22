<?php

namespace Database\Seeders;

use App\Models\ContactUs;
use Illuminate\Database\Seeder;

class ContactUsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ContactUs::factory()->count(5)->create();

        $message = 'تم انشاء 5 رسايل بنجاح';
        $this->command?->info($message);
    }
}
