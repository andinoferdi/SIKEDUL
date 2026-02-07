<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TodoItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $titleRule = $this->isMethod('post') ? 'required' : 'sometimes';

        if ($this->isMethod('post')) {
            return [
                'title' => [$titleRule, 'string', 'max:200'],
                'due_date' => [
                    'nullable',
                    'date',
                    'required_without_all:start_date,end_date',
                ],
                'start_date' => [
                    'nullable',
                    'date',
                    'required_with:end_date',
                ],
                'end_date' => [
                    'nullable',
                    'date',
                    'required_with:start_date',
                    'after_or_equal:start_date',
                ],
                'is_done' => ['nullable', 'boolean'],
            ];
        }

        return [
            'title' => [$titleRule, 'string', 'max:200'],
            'due_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_done' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasAnyDateInput = $this->has('due_date') || $this->has('start_date') || $this->has('end_date');

            if (! $this->isMethod('post') && ! $hasAnyDateInput) {
                return;
            }

            $dueDate = $this->input('due_date');
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            $hasDue = ! empty($dueDate);
            $hasRange = ! empty($startDate) || ! empty($endDate);

            if (! $hasDue && ! $hasRange) {
                $validator->errors()->add('due_date', 'Please provide a due date or a date range.');
                return;
            }

            if ($hasDue && $hasRange) {
                $validator->errors()->add('due_date', 'Due date cannot be combined with a date range.');
                return;
            }

            if ($hasRange) {
                if (empty($startDate) || empty($endDate)) {
                    $validator->errors()->add('start_date', 'Start date and end date are required for a range.');
                    return;
                }

                if (strtotime($startDate) > strtotime($endDate)) {
                    $validator->errors()->add('end_date', 'End date must be after or equal to start date.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Item title is required.',
            'title.max' => 'Item title cannot exceed 200 characters.',
        ];
    }
}
