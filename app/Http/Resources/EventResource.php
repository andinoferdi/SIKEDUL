<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'start' => $this->getStartInTimezone($user->timezone)->format('Y-m-d\TH:i:s'),
            'end' => $this->getEndInTimezone($user->timezone)->format('Y-m-d\TH:i:s'),
            'start_at_utc' => $this->start_at_utc->toISOString(),
            'end_at_utc' => $this->end_at_utc->toISOString(),
            'status' => $this->status,
            'reminder_minutes' => $this->reminder_minutes,
            'category' => new EventCategoryResource($this->whenLoaded('category')),
            'category_id' => $this->category_id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
