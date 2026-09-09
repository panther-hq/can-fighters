<?php

namespace App\Http\Controllers;

use App\Http\Resources\FighterResource;
use App\Models\Fighter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FighterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return FighterResource::collection(
            $request->user()->fighters()->latest()->get(),
        );
    }

    public function show(Request $request, Fighter $fighter): FighterResource
    {
        abort_unless($fighter->user_id === $request->user()->id, 404);

        return FighterResource::make($fighter);
    }
}
