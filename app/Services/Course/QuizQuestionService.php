<?php

namespace App\Services\Course;

use App\Models\Course\QuizQuestion;
use Illuminate\Support\Facades\DB;

class QuizQuestionService
{
   public function createQuestion(array $data)
   {
      return QuizQuestion::create([
         ...$data,
         'answer'  => json_encode($data['answer']),
         'options' => json_encode($data['options']),
      ]);
   }

   public function bulkCreateQuestions(string $quizId, array $questions): int
   {
      $baseSort = (int) QuizQuestion::where('section_quiz_id', $quizId)->max('sort') ?? 0;

      return DB::transaction(function () use ($quizId, $questions, $baseSort) {
         $created = 0;
         $sort = $baseSort;

         foreach ($questions as $question) {
            $sort++;

            QuizQuestion::create([
               'section_quiz_id' => $quizId,
               'title' => $question['title'],
               'type' => $question['type'],
               'options' => json_encode($question['options'] ?? []),
               'answer' => json_encode($question['answer'] ?? []),
               'sort' => $sort,
            ]);

            $created++;
         }

         return $created;
      });
   }

   public function updateQuestion(array $data, string $id)
   {
      return QuizQuestion::where('id', $id)->update([
         ...$data,
         'answer'  => json_encode($data['answer']),
         'options' => json_encode($data['options']),
      ]);
   }

   public function deleteQuestion(string $id): bool
   {
      return QuizQuestion::find($id)->delete();
   }

   public function sortQuestions(array $sortedData)
   {
      foreach ($sortedData as $value) {
         QuizQuestion::where('id', $value['id'])->update([
            'sort' => $value['sort']
         ]);
      }
   }
}
