<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesSlug;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared by "create" and "update". On update the route has a {category}.
 */
class CategoryRequest extends FormRequest
{
    use NormalizesSlug;

    public function authorize(): bool
    {
        // The route group already requires `can:admin`; checking again here
        // keeps the request safe even if it is reused on another route.
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * Normalise input BEFORE validation: an empty slug is generated from the
     * name, and any slug is lower-cased and cleaned ("Baju Pria" → "baju-pria").
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->normalizedSlug(),
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->integer('sort_order'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:120',
                Rule::unique(Category::class)->ignore($this->category()),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * Rules that need the database and the other fields: run only after the
     * basic rules above have passed.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $parentId = $this->integer('parent_id') ?: null;

                if ($parentId === null) {
                    return; // A root category is always allowed.
                }

                /** @var Category $parent */
                $parent = Category::query()->findOrFail($parentId);
                $category = $this->category();

                if ($category !== null) {
                    // Moving a category under itself or under one of its own
                    // children would create a loop in the tree.
                    if ($parent->is($category) || in_array($category->id, $parent->ancestorIds(), true)) {
                        $validator->errors()->add(
                            'parent_id',
                            'Kategori tidak bisa dipindahkan ke dalam dirinya sendiri atau subkategorinya.',
                        );

                        return;
                    }
                }

                // The deepest level after saving = parent's level + 1 (this
                // category) + the levels already hanging below it.
                $deepest = $parent->level() + 1 + ($category?->descendantDepth() ?? 0);

                if ($deepest > Category::MAX_DEPTH) {
                    $validator->errors()->add(
                        'parent_id',
                        'Kategori maksimal '.Category::MAX_DEPTH.' level. Pilih induk yang lebih tinggi.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'parent_id' => 'induk kategori',
            'description' => 'deskripsi',
            'sort_order' => 'urutan',
        ];
    }

    /**
     * The category being edited, or null when creating.
     */
    private function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }
}
