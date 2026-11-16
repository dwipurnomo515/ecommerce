<?php

namespace App\Http\Requests\Seller;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class ProductImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->product()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.ProductImage::MAX_PER_PRODUCT],
            'images.*' => [
                'required',
                // Checks the real file CONTENT (magic bytes), not just the
                // ".jpg" in the name. SVG and GIF are deliberately not
                // allowed: an SVG can carry JavaScript.
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024) // KB → 5 MB
                    ->dimensions(Rule::dimensions()->minWidth(400)->minHeight(400)->maxWidth(6000)->maxHeight(6000)),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $files = $this->file('images');
                $incoming = is_array($files) ? count($files) : 0;
                $existing = $this->product()->images()->count();

                if ($existing + $incoming > ProductImage::MAX_PER_PRODUCT) {
                    $left = max(0, ProductImage::MAX_PER_PRODUCT - $existing);
                    $validator->errors()->add(
                        'images',
                        'Maksimal '.ProductImage::MAX_PER_PRODUCT." foto per produk. Anda masih bisa menambah {$left} foto.",
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.*.image' => 'File harus berupa gambar.',
            'images.*.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
            'images.*.max' => 'Ukuran foto maksimal 5 MB.',
            'images.*.dimensions' => 'Foto minimal 400×400 piksel (maksimal 6000×6000).',
        ];
    }

    private function product(): Product
    {
        /** @var Product */
        return $this->route('product');
    }
}
