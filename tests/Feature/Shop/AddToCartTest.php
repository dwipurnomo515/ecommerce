<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductStatus;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AddToCartTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create();
        $this->product = Product::factory()->active()
            ->for(Store::factory()->active()->create(['slug' => 'toko-contoh']))
            ->create(['slug' => 'kaos-polos']);
        $this->variant = ProductVariant::factory()->for($this->product)->create(['stock' => 5]);
    }

    private function add(int $quantity, ?int $variantId = null, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->buyer)
            ->from('/toko/toko-contoh/kaos-polos')
            ->post(route('cart.store'), ['variant_id' => $variantId ?? $this->variant->id, 'quantity' => $quantity]);
    }

    private function inCart(): int
    {
        return (int) $this->buyer->cartItems()->where('product_variant_id', $this->variant->id)->value('quantity');
    }

    public function test_a_buyer_adds_a_variant_and_returns_to_the_product(): void
    {
        $this->add(2)
            ->assertSessionHasNoErrors()
            ->assertRedirect('/toko/toko-contoh/kaos-polos');

        $this->assertSame(2, $this->inCart());
    }

    public function test_adding_again_raises_the_quantity_instead_of_a_second_row(): void
    {
        $this->add(2);
        $this->add(1);

        $this->assertSame(3, $this->inCart());
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_the_cart_total_may_not_exceed_stock(): void
    {
        $this->add(3);

        $this->add(3)->assertSessionHasErrors([
            'quantity' => 'Stok tersisa 5, dan 3 sudah ada di keranjang Anda.',
        ]);
        $this->assertSame(3, $this->inCart());
    }

    public function test_a_sold_out_variant_cannot_be_added(): void
    {
        $this->variant->update(['stock' => 0]);

        $this->add(1)->assertSessionHasErrors(['quantity' => 'Stok varian ini habis.']);
    }

    public function test_quantity_must_be_between_1_and_99(): void
    {
        $this->variant->update(['stock' => 500]);

        $this->add(0)->assertSessionHasErrors('quantity');
        $this->add(100)->assertSessionHasErrors('quantity');
        $this->add(99)->assertSessionHasNoErrors();
        $this->add(1)->assertSessionHasErrors('quantity'); // 99 already in the cart

        $this->assertSame(99, $this->inCart());
    }

    public function test_hidden_or_removed_variants_cannot_be_added(): void
    {
        $removed = ProductVariant::factory()->for($this->product)->create(['is_active' => false]);
        $this->add(1, $removed->id)->assertSessionHasErrors(['variant_id' => 'Produk ini sudah tidak dijual.']);
        $this->add(1, 999_999)->assertSessionHasErrors(['variant_id' => 'Produk ini sudah tidak dijual.']);

        $this->product->forceFill(['status' => ProductStatus::Draft])->save();
        $this->add(1)->assertSessionHasErrors(['variant_id' => 'Produk ini sudah tidak dijual.']);

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_sellers_cannot_buy_from_their_own_store(): void
    {
        $seller = $this->product->store->user;

        $this->add(1, as: $seller)->assertSessionHasErrors(['variant_id' => 'Ini produk toko Anda sendiri.']);

        $this->actingAs($seller)
            ->get('/toko/toko-contoh/kaos-polos')
            ->assertInertia(fn (Assert $page) => $page->where('isOwner', true));
    }

    public function test_guests_are_sent_to_log_in_and_brought_back_to_the_product(): void
    {
        $this->post(route('cart.store'), ['variant_id' => $this->variant->id, 'quantity' => 1])
            ->assertRedirect(route('login'));

        // The "Masuk untuk membeli" link: auth remembers it, login returns
        // here, and this route sends the buyer back to the product.
        $this->get(route('shop.products.login-to-buy', ['toko-contoh', 'kaos-polos']))
            ->assertRedirect(route('login'));
        $this->assertSame(
            route('shop.products.login-to-buy', ['toko-contoh', 'kaos-polos']),
            session('url.intended'),
        );

        $this->actingAs($this->buyer)
            ->get(route('shop.products.login-to-buy', ['toko-contoh', 'kaos-polos']))
            ->assertRedirect(route('shop.products.show', ['toko-contoh', 'kaos-polos']));
    }
}
