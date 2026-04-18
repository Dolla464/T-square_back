<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // لكل امتحان، هنضيف 5 أسئلة عشوائية
        Exam::all()->each(function ($exam) {
            Question::factory(5)->create([
                'exam_id' => $exam->id
            ]);
        });
    }
}
