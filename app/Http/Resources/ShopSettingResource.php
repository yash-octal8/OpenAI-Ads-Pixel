<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'pixel_id' => $this->pixel_id ?? '',
            'capi_key' => $this->capi_key ?? '',
            'advertiser_key' => $this->advertiser_key ?? '',
            'tracking_enabled' => (bool)$this->tracking_enabled,
            'pixel_helper_enabled' => (bool)$this->pixel_helper_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
