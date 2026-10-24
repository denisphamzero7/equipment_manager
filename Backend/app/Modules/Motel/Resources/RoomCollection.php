<?php

namespace App\Modules\Motel\Resources;

use Illuminate\Http\Request;
use Illuminate\Resources\Json\ResourceCollection as resourceCollection;

class RoomCollection extends resourceCollection
{
    public $collects = RoomResource::class;

    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
