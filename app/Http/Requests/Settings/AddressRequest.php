<?php

namespace App\Http\Requests\Settings;

use App\Models\Address;
use App\Shipping\Destination;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddressRequest extends FormRequest
{
    private ?Destination $destination = null;

    public function authorize(): bool
    {
        // Ownership is enforced by looking the address up through the
        // user's own relation in the controller (someone else's id → 404).
        return $this->user() !== null;
    }

    /**
     * People type phone numbers in many ways: "+62 812-3456-7890",
     * "0812 3456 7890", "62812...". Store one format: 0812...
     */
    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s\-().]/', '', $this->string('phone')->value()) ?? '';
        $phone = preg_replace('/^(\+62|62)/', '0', $phone) ?? $phone;

        $this->merge([
            'phone' => $phone,
            'label' => $this->filled('label') ? trim($this->string('label')->value()) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:30'],
            'recipient_name' => ['required', 'string', 'max:100'],
            // Indonesian mobile: 08, then 8-12 more digits.
            'phone' => ['required', 'regex:/^08[1-9][0-9]{7,11}$/'],
            'street' => ['required', 'string', 'min:10', 'max:500'],
            'notes' => ['nullable', 'string', 'max:200'],
            // Required for a new address; when editing, leaving the region
            // untouched sends no token and keeps the current one.
            'destination_token' => [$this->editing() ? 'nullable' : 'required', 'string'],
            'is_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $token = $this->input('destination_token');

                if (! is_string($token) || $token === '') {
                    return;
                }

                $this->destination = Destination::fromToken($token);

                if ($this->destination === null) {
                    $validator->errors()->add('destination_token', 'Pilih kecamatan/kelurahan dari daftar saran.');
                }
            },
        ];
    }

    /** The chosen region, decrypted from its token; null = keep the current one. */
    public function destination(): ?Destination
    {
        return $this->destination;
    }

    private function editing(): bool
    {
        return $this->route('address') !== null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'label',
            'recipient_name' => 'nama penerima',
            'phone' => 'nomor HP',
            'street' => 'alamat lengkap',
            'notes' => 'catatan',
            'destination_token' => 'wilayah',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor HP harus nomor seluler Indonesia, misalnya 0812 3456 7890.',
            'destination_token.required' => 'Pilih kecamatan/kelurahan dari daftar saran.',
        ];
    }

    /** Max per user, checked by the controller for new addresses. */
    public static function limitMessage(): string
    {
        return 'Maksimal '.Address::MAX_PER_USER.' alamat. Hapus alamat lama dulu.';
    }
}
