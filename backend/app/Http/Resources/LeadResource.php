<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Lead $resource
 */
class LeadResource extends JsonResource
{
    /** The contract documents an unwrapped body. */
    public static $wrap = null;

    public function __construct($resource, private readonly ?string $whatsappUrl, private readonly string $statusMessage)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'lead_code' => $this->resource->lead_code,
            'score' => $this->resource->score,
            'qualification' => $this->resource->qualification,
            'whatsapp_url' => $this->whatsappUrl,
            'message' => $this->statusMessage,
        ];
    }
}
