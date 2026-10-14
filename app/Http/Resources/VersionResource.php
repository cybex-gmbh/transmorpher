<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'processed' => $this->processed,
            'hash' => $this->hash,
            'filename' => $this->filename,
            'createdAt' => $this->created_at,
        ];
    }
}
