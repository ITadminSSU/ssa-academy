<?php

namespace App\Services\Course;

use App\Models\Course\QuizQuestion;
use App\Models\Course\SectionQuiz;
use Illuminate\Support\Facades\DB;

class QuizQuestionService
{
   public function __construct(
      private QuizTakeoffService $takeoff,
   ) {}

   public function createQuestion(array $data)
   {
      $quiz = SectionQuiz::findOrFail($data['section_quiz_id']);
      $this->takeoff->assertCanSave($quiz, [$data['type']]);

      if (($data['type'] ?? '') === QuizQuestion::TYPE_TAKEOFF) {
         $data = $this->takeoff->prepareQuestionPayload($data);
      }

      return QuizQuestion::create([
         'section_quiz_id' => $data['section_quiz_id'],
         'title' => $data['title'],
         'type' => $data['type'],
         'options' => json_encode($data['options'] ?? []),
         'answer' => json_encode($data['answer'] ?? []),
         'sort' => $data['sort'],
      ]);
   }

   public function bulkCreateQuestions(string $quizId, array $questions): int
   {
      $quiz = SectionQuiz::findOrFail($quizId);
      $this->takeoff->assertCanSave($quiz, array_column($questions, 'type'));

      $baseSort = (int) QuizQuestion::where('section_quiz_id', $quizId)->max('sort');

      return DB::transaction(function () use ($quizId, $questions, $baseSort) {
         $created = 0;
         $sort = $baseSort;

         foreach ($questions as $question) {
            $sort++;

            if (($question['type'] ?? '') === QuizQuestion::TYPE_TAKEOFF) {
               $question = $this->takeoff->prepareQuestionPayload($question);
            }

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
      $question = QuizQuestion::findOrFail($id);
      $quiz = $question->lesson_quiz ?: SectionQuiz::findOrFail($question->section_quiz_id);
      $this->takeoff->assertCanSave($quiz, [$data['type']], (int) $question->id);

      if (($data['type'] ?? '') === QuizQuestion::TYPE_TAKEOFF) {
         $data = $this->takeoff->prepareQuestionPayload($data, $question);
      }

      return $question->update([
         'title' => $data['title'],
         'type' => $data['type'],
         'options' => json_encode($data['options'] ?? []),
         'answer' => json_encode($data['answer'] ?? []),
         'sort' => $data['sort'] ?? $question->sort,
         'section_quiz_id' => $data['section_quiz_id'] ?? $question->section_quiz_id,
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
