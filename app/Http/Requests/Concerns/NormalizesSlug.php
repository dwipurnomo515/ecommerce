<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * For FormRequests with `name` + `slug` fields: an empty slug is generated
 * from the name, and any slug is cleaned ("Baju Pria!" → "baju-pria").
 * Call it from prepareForValidation() so the rules see the final slug.
 */
trait NormalizesSlug
{
    protected function normalizedSlug(): string
    {
        $slug = $this->string('slug')->trim()->value();

        return Str::slug($slug !== '' ? $slug : $this->string('name')->value());
    }
}
