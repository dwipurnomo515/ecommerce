<?php

namespace App\Http\Requests\Seller;

use App\Enums\StoreStatus;
use App\Http\Requests\Concerns\NormalizesSlug;
use App\Http\Requests\Concerns\ReadsDestinationToken;
use App\Models\Store;
use App\Shipping\Destination;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The seller-editable part of a store: used to open a store and to edit it.
 * Status, owner and approval date are never part of this request.
 */
class StoreProfileRequest extends FormRequest
{
    use NormalizesSlug, ReadsDestinationToken;

    public function authorize(): bool
    {
        // Decide by the kind of request (POST = open, PUT = edit), never by
        // "is there a store?": a PUT from a user without a store must fail.
        if ($this->isCreating()) {
            return $this->user()?->can('create', Store::class) ?? false;
        }

        $store = $this->existingStore();

        return $store !== null && ($this->user()?->can('update', $store) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->slugIsEditable()) {
            $this->merge(['slug' => $this->normalizedSlug()]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Where parcels are sent from. Optional on the form (a new
            // seller may not know yet), but checkout needs it.
            'origin_token' => ['nullable', 'string'],
        ];

        // The slug is the store's public address (/toko/{slug}). Buyers may
        // already have shared or bookmarked it, so it is frozen once the store
        // has been approved.
        if ($this->slugIsEditable()) {
            $rules['slug'] = [
                'required',
                'string',
                'min:3',
                'max:120',
                Rule::unique(Store::class)->ignore($this->existingStore()),
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->decodeDestinationToken(
                $validator, 'origin_token', 'Pilih kecamatan asal pengiriman dari daftar saran.',
            ),
        ];
    }

    /** The decrypted shipping origin, or null when it was left unchanged. */
    public function origin(): ?Destination
    {
        return $this->decodedDestination();
    }

    /**
     * The store's own columns. origin_token is not one of them: it becomes
     * three columns through Store::setShippingOrigin().
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return $this->safe()->except('origin_token');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama toko',
            'slug' => 'alamat toko',
            'description' => 'deskripsi',
            'origin_token' => 'asal pengiriman',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Alamat toko ini sudah dipakai toko lain. Coba variasi lain.',
        ];
    }

    private function isCreating(): bool
    {
        return $this->isMethod('POST');
    }

    private function slugIsEditable(): bool
    {
        return $this->isCreating() || $this->existingStore()?->status === StoreStatus::Pending;
    }

    /**
     * The current user's store when editing; null when opening a new one.
     */
    private function existingStore(): ?Store
    {
        return $this->isCreating() ? null : $this->user()?->store;
    }
}
