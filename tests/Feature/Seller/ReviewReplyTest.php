<?php

namespace Tests\Feature\Seller;

use App\Enums\OrderStatus;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewReplyTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->active()->create(['slug' => 'toko-contoh']);
        $this->product = Product::factory()->active()->for($this->store)->create(['slug' => 'kaos']);
        ProductVariant::factory()->for($this->product)->create();
    }

    /**
     * A review as WriteReview would leave it (the purchase path itself is
     * covered in Shop\ReviewTest).
     */
    private function review(int $rating = 4, ?string $reply = null): Review
    {
        $buyer = User::factory()->create(['name' => 'Ani Lestari']);
        $checkout = new Checkout;
        $checkout->forceFill([
            'user_id' => $buyer->id, 'code' => 'ZTG-'.strtoupper(fake()->unique()->bothify('????####')),
            'idempotency_key' => fake()->uuid(), 'payment_status' => 'paid',
            'items_total' => 1000, 'shipping_total' => 0, 'grand_total' => 1000, 'expires_at' => now(),
        ])->save();
        $order = new Order;
        $order->forceFill([
            'checkout_id' => $checkout->id, 'user_id' => $buyer->id, 'store_id' => $this->store->id,
            'number' => $checkout->code.'-1', 'status' => OrderStatus::Completed, 'items_total' => 1000,
            'shipping_cost' => 0, 'courier' => 'jne', 'courier_service' => 'REG', 'weight_grams' => 100,
            'recipient_name' => 'Ani', 'phone' => '081200000000', 'street' => 'Jl. Contoh', 'province' => 'P',
            'city' => 'K', 'district' => 'D', 'subdistrict' => 'S', 'postal_code' => '10000',
            'destination_id' => 1, 'origin_destination_id' => 1,
        ])->save();
        $item = $order->items()->forceCreate([
            'product_variant_id' => $this->product->variants()->value('id'), 'product_id' => $this->product->id,
            'product_name' => $this->product->name, 'price' => 1000, 'quantity' => 1, 'weight_grams' => 100,
        ]);

        $review = new Review;
        $review->forceFill([
            'order_item_id' => $item->id, 'product_id' => $this->product->id, 'store_id' => $this->store->id,
            'user_id' => $buyer->id, 'rating' => $rating, 'body' => 'Lumayan.', 'seller_reply' => $reply,
        ])->save();

        return $review;
    }

    public function test_the_seller_replies_once_and_it_shows_on_the_product_page(): void
    {
        $review = $this->review();
        $seller = $this->store->user;

        $this->actingAs($seller)
            ->post(route('seller.reviews.reply', $review), ['reply' => '  Terima kasih, Kak!  '])
            ->assertSessionHasNoErrors();
        $this->assertSame('Terima kasih, Kak!', $review->fresh()?->seller_reply);

        $this->actingAs($seller)
            ->post(route('seller.reviews.reply', $review), ['reply' => 'Diganti ya'])
            ->assertSessionHasErrors(['reply' => 'Ulasan ini sudah Anda balas.']);
        $this->assertSame('Terima kasih, Kak!', $review->fresh()?->seller_reply, 'A reply is never rewritten.');

        $this->get(route('shop.products.show', ['toko-contoh', 'kaos']))
            ->assertInertia(fn (Assert $p) => $p->where('reviews.0.seller_reply', 'Terima kasih, Kak!'));
    }

    public function test_only_the_reviewed_store_can_reply(): void
    {
        $review = $this->review();

        $this->actingAs(Store::factory()->active()->create()->user)
            ->post(route('seller.reviews.reply', $review), ['reply' => 'Halo'])
            ->assertForbidden();
        $this->actingAs($review->user)
            ->post(route('seller.reviews.reply', $review), ['reply' => 'Saya balas sendiri'])
            ->assertForbidden();

        $this->assertNull($review->fresh()?->seller_reply);
    }

    public function test_the_list_shows_unanswered_first_and_counts_them(): void
    {
        $this->review(5);
        $this->review(3, reply: 'Sudah dibalas');
        $page = fn (array $q = []) => $this->actingAs($this->store->user)->get(route('seller.reviews.index', $q));

        $page()->assertInertia(fn (Assert $p) => $p
            ->component('seller/reviews/index')
            ->where('filter', 'belum-dibalas')
            ->where('unansweredCount', 1)
            ->has('reviews.data', 1)
            ->where('reviews.data.0.author', 'Ani L.'));

        $page(['filter' => 'semua'])->assertInertia(fn (Assert $p) => $p->has('reviews.data', 2));
    }

    public function test_store_rating_shows_on_its_public_pages(): void
    {
        $this->store->forceFill(['rating_count' => 4, 'rating_sum' => 18])->save();

        $this->get(route('shop.stores.show', 'toko-contoh'))
            ->assertInertia(fn (Assert $p) => $p->where('store.rating', ['average' => 4.5, 'count' => 4]));
        $this->get(route('shop.products.show', ['toko-contoh', 'kaos']))
            ->assertInertia(fn (Assert $p) => $p->where('store.rating.average', 4.5));
    }
}
