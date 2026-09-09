<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MixIngredientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.slug' => ['required', 'string'],
            'ingredients.*.quantity' => ['required', 'integer', 'min:1', 'max:6'],
        ];
    }

    /**
     * @return list<array{slug: string, quantity: int}>
     */
    public function ingredients(): array
    {
        return array_map(
            fn (array $row) => ['slug' => $row['slug'], 'quantity' => (int) $row['quantity']],
            $this->validated('ingredients'),
        );
    }
}
