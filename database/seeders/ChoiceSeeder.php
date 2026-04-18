<?php

namespace Database\Seeders;

use App\Models\Choice;
use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Question::all()->each(function ($question) {
            // إنشاء 4 اختيارات لكل سؤال
            $choices = Choice::factory(4)->create([
                'question_id' => $question->id
            ]);

            // اختيار واحد عشوائي ليكون هو الإجابة الصحيحة
            $choices->random()->update(['is_correct' => true]);
        });
    }
}
