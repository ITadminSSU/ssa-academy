<?php

namespace Modules\Exam\Http\Requests;

use Illuminate\Validation\Rule;

class StoreExamWithQuestionsRequest extends ExamRequest
{
   public function rules(): array
   {
      $rules = parent::rules();

      $types = ['multiple_choice', 'multiple_select', 'matching', 'fill_blank', 'ordering', 'short_answer', 'listening', 'file_submission'];

      $rules['questions'] = 'nullable|array';
      $rules['questions.*.question_type'] = ['required', 'string', Rule::in($types)];
      $rules['questions.*.title'] = 'required|string';
      $rules['questions.*.description'] = 'nullable|string';
      $rules['questions.*.marks'] = 'required|numeric|min:0.5';
      $rules['questions.*.options'] = 'nullable|array';
      $rules['questions.*.question_options'] = 'nullable|array';
      $rules['questions.*.question_options.*.option_text'] = 'required|string';
      $rules['questions.*.question_options.*.is_correct'] = 'required|boolean';

      return $rules;
   }

   /**
    * Require at least 2 options for multiple choice / multiple select drafts.
    */
   public function withValidator($validator): void
   {
      $validator->after(function ($validator) {
         if ($this->input('exam_mode') === 'quantity_takeoff') {
            return;
         }

         foreach ($this->input('questions', []) as $i => $question) {
            if (in_array($question['question_type'] ?? null, ['multiple_choice', 'multiple_select'])) {
               if (count($question['question_options'] ?? []) < 2) {
                  $validator->errors()->add("questions.{$i}.question_options", 'At least 2 options are required.');
               }
            }
         }
      });
   }
}
