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
            'start' => $this->getStartInTimezone($user->timezone)->toISOString(),
            'end' => $this->getEndInTimezone($user->timezone)->toISOString(),
            'start_at_utc' => $this->start_at_utc->toISOString(),
            'end_at_utc' => $this->end_at_utc->toISOString(),
            'status' => $this->status,
            'category' => new EventCategoryResource($this->whenLoaded('category')),
            'category_id' => $this->category_id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
