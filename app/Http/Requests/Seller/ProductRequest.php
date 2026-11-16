<?php

namespace App\Http\Requests\Seller;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A product with its variants, submitted as one form:
 *
 *   options:  [{name: "Warna", values: ["Hitam", "Putih"]}, ...]   (0-2)
 *   variants: [{options: {"Warna": "Hitam"}, price, stock, ...}, ...] (1-100)
 *
 * No options = a simple product with exactly one variant (options: {}).
 */
class ProductRequest extends FormRequest
{
    public const MAX_OPTIONS = 2;

    public const MAX_VALUES_PER_OPTION = 20;

    public const MAX_VARIANTS = 100;

    public function authorize(): bool
    {
        $product = $this->editedProduct();

        return $product === null
            ? $this->user()?->can('create', Product::class) ?? false
            : $this->user()?->can('update', $product) ?? false;
    }

    /**
     * The product being edited (PUT /seller/products/{product}), or null
     * when creating one.
     */
    public function editedProduct(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'description' => ['nullable', 'string', 'max:10000'],
            // Archiving only makes sense for an existing product. Banned is
            // never accepted here: it is set by an admin only.
            'status' => ['required', Rule::enum(ProductStatus::class)->only(
                $this->editedProduct() === null
                    ? [ProductStatus::Draft, ProductStatus::Active]
                    : ProductStatus::sellerSelectable(),
            )],

            'options' => ['present', 'array', 'max:'.self::MAX_OPTIONS],
            'options.*.name' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:'.self::MAX_VALUES_PER_OPTION],
            'options.*.values.*' => ['required', 'string', 'max:30', 'distinct:ignore_case'],

            'variants' => ['required', 'array', 'min:1', 'max:'.self::MAX_VARIANTS],
            'variants.*.options' => ['present', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            // Whole Rupiah. Rp 100 minimum, Rp 1 miliar maximum.
            'variants.*.price' => ['required', 'integer', 'min:100', 'max:1000000000'],
            'variants.*.compare_at_price' => ['nullable', 'integer', 'gt:variants.*.price', 'max:1000000000'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            // Used for shipping costs later. 1 g to 100 kg.
            'variants.*.weight_grams' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * Cross-field rules that the array rules above cannot express.
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

                $this->validateCategory($validator);
                $this->validateVariantsMatchOptions($validator);
                $this->validateUniqueSkus($validator);
            },
        ];
    }

    private function validateCategory(Validator $validator): void
    {
        // Keeping the category the product already has is always fine, even
        // if an admin has since deactivated it: fixing a typo in the price
        // must not force the seller to re-categorise.
        if ($this->integer('category_id') === $this->editedProduct()?->category_id) {
            return;
        }

        $category = Category::query()->find($this->integer('category_id'));

        if ($category === null || ! $category->isSelectable()) {
            $validator->errors()->add('category_id', 'Kategori ini sedang tidak tersedia. Pilih kategori lain.');
        }
    }

    /**
     * Every variant must name exactly the declared options, use only the
     * declared values, and no two variants may be the same combination.
     */
    private function validateVariantsMatchOptions(Validator $validator): void
    {
        /** @var list<array{name: string, values: list<string>}> $options */
        $options = $this->input('options');
        // Values are still untrusted user input here (could be arrays/numbers).
        /** @var list<array{options: array<string, mixed>}> $variants */
        $variants = $this->input('variants');

        if ($options === [] && count($variants) !== 1) {
            $validator->errors()->add('variants', 'Produk tanpa pilihan varian hanya boleh punya satu harga.');

            return;
        }

        $allowed = [];
        foreach ($options as $option) {
            $allowed[$option['name']] = $option['values'];
        }

        $declaredNames = array_keys($allowed);
        sort($declaredNames);

        $seen = [];
        foreach ($variants as $i => $variant) {
            $chosen = $variant['options'];

            $chosenNames = array_keys($chosen);
            sort($chosenNames);

            // Same option names as declared, and every value is one of the
            // declared values for that option.
            $valid = $chosenNames === $declaredNames && collect($chosen)->every(
                fn ($value, $name) => is_string($value) && in_array($value, $allowed[$name], true),
            );

            if (! $valid) {
                $validator->errors()->add("variants.{$i}.options", 'Pilihan varian tidak sesuai dengan daftar pilihan produk.');

                continue;
            }

            $key = ProductVariant::optionsKey($chosen);

            if (isset($seen[$key])) {
                $validator->errors()->add("variants.{$i}.options", 'Kombinasi varian ini muncul dua kali.');
            }

            $seen[$key] = true;
        }
    }

    private function validateUniqueSkus(Validator $validator): void
    {
        $seen = [];

        foreach ($this->input('variants') as $i => $variant) {
            $sku = $variant['sku'] ?? null;

            if ($sku === null || $sku === '') {
                continue; // SKU is optional; several variants may leave it empty
            }

            if (isset($seen[mb_strtolower($sku)])) {
                $validator->errors()->add("variants.{$i}.sku", 'SKU ini sudah dipakai varian lain.');
            }

            $seen[mb_strtolower($sku)] = true;
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama produk',
            'category_id' => 'kategori',
            'description' => 'deskripsi',
            'options.*.name' => 'nama pilihan',
            'options.*.values' => 'isi pilihan',
            'options.*.values.*' => 'isi pilihan',
            'variants.*.price' => 'harga',
            'variants.*.compare_at_price' => 'harga coret',
            'variants.*.stock' => 'stok',
            'variants.*.weight_grams' => 'berat',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.*.compare_at_price.gt' => 'Harga coret harus lebih tinggi dari harga jual.',
        ];
    }
}
