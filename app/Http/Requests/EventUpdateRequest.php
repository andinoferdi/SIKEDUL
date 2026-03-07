<?php

namespace App\Http\Requests;

use App\Rules\NoEventOverlap;
use Illuminate\Foundation\Http\FormRequest;

class EventUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $event = $this->route('event');

        $rules = [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category_id' => ['sometimes', 'nullable', 'exists:event_categories,id'],
            'status' => ['sometimes', 'nullable', 'in:planned,done,canceled'],
            'reminder_minutes' => ['sometimes', 'nullable', 'integer', 'in:0,5,10,15,30,60,1440'],
            'ignore_conflict' => ['sometimes', 'boolean'],
        ];

        // Add overlap validation if start_at or end_at is being updated
        if ($this->has('start_at') || $this->has('end_at')) {
            $startAt = $this->input('start_at', $event->getStartInTimezone($user->timezone)->format('Y-m-d H:i:s'));
            $endAt = $this->input('end_at', $event->getEndInTimezone($user->timezone)->format('Y-m-d H:i:s'));

            $rules['start_at'] = [
                'sometimes',
                'required',
                'date',
                'before:end_at',
                new NoEventOverlap(
                    $user->id,
                    $user->timezone,
                    $startAt,
                    $endAt,
                    $event->id,
                    (bool) $this->boolean('ignore_conflict')
                ),
            ];
            $rules['end_at'] = ['sometimes', 'required', 'date', 'after:start_at'];
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Verify that the category_id belongs to the authenticated user
        if ($this->has('category_id') && $this->category_id) {
            $categoryExists = auth()->user()->eventCategories()
                ->where('id', $this->category_id)
                ->exists();

            if (! $categoryExists) {
                $this->merge(['category_id' => null]);
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required.',
            'title.max' => 'Event title cannot exceed 120 characters.',
            'start_at.required' => 'Start time is required.',
            'start_at.date' => 'Start time must be a valid date.',
            'start_at.before' => 'Start time must be before end time.',
            'end_at.required' => 'End time is required.',
            'end_at.date' => 'End time must be a valid date.',
            'end_at.after' => 'End time must be after start time.',
            'category_id.exists' => 'Selected category does not exist.',
            'status.in' => 'Status must be one of: planned, done, or canceled.',
            'reminder_minutes.in' => 'Reminder must be 0, 5, 10, 15, 30, 60 minutes, or 1 day before the event.',
        ];
    }
}
