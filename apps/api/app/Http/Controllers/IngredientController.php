<?php

namespace App\Http\Controllers;

use App\Http\Resources\IngredientDefinitionResource;
use App\Models\IngredientDefinition;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IngredientController extends Controller
{
    /**
     * The full ingredient catalogue (static game content).
     */
    public function index(): AnonymousResourceCollection
    {
        return IngredientDefinitionResource::collection(
            IngredientDefinition::query()->orderBy('id')->get(),
        );
    }
}
