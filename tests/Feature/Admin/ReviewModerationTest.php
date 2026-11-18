<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Store $store;

    private Product $product;

    private Review $spam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->store = Store::factory()->active()->create(['slug' => 'toko-contoh']);
        $this->product = Product::factory()->active()->for($this->store)->create(['slug' => 'kaos']);
        ProductVariant::factory()->for($this->product)->create();

        // Two reviews: 5 stars, and a 1-star spam one. Totals as WriteReview leaves them.
        $this->review(5, 'Bagus sekali.');
        $this->spam = $this->review(1, 'Hubungi 0812xxxx untuk harga murah!!!');
        foreach ([$this->product, $this->store] as $model) {
            $model->forceFill(['rating_count' => 2, 'rating_sum' => 6])->save();
        }
    }

    private function review(int $rating, string $body): Review
    {
        $buyer = User::factory()->create();
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
            'recipient_name' => 'X', 'phone' => '081200000000', 'street' => 'Jl. Contoh', 'province' => 'P',
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
            'user_id' => $buyer->id, 'rating' => $rating, 'body' => $body,
        ])->save();

        return $review;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function totals(Product|Store $model): array
    {
        $fresh = $model->fresh();

        return [$fresh?->rating_count, $fresh?->rating_sum];
    }

    public function test_hiding_a_review_takes_it_out_of_the_averages_and_the_page(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.reviews.hide', $this->spam), ['reason' => 'Spam nomor telepon'])
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 5], $this->totals($this->product));
        $this->assertSame([1, 5], $this->totals($this->store));

        $this->get(route('shop.products.show', ['toko-contoh', 'kaos']))
            ->assertInertia(fn (Assert $p) => $p
                ->has('reviews', 1)
                ->where('rating.count', 1)
                ->where('rating.average', 5)); // JSON has no 5.0: it arrives as 5

        $log = AuditLog::query()->sole();
        $this->assertSame(['review.hidden', $this->admin->id], [$log->action, $log->actor_id]);
    }

    public function test_showing_it_again_restores_the_totals_exactly(): void
    {
        $this->actingAs($this->admin)->post(route('admin.reviews.hide', $this->spam), ['reason' => 'Spam nomor telepon']);
        $this->actingAs($this->admin)->post(route('admin.reviews.show', $this->spam));
        $this->actingAs($this->admin)->post(route('admin.reviews.show', $this->spam)); // twice: no double count

        $this->assertSame([2, 6], $this->totals($this->product));
        $this->assertSame([2, 6], $this->totals($this->store));
        $this->assertNull($this->spam->fresh()?->hidden_at);
    }

    public function test_hiding_twice_does_not_subtract_twice(): void
    {
        $this->actingAs($this->admin)->post(route('admin.reviews.hide', $this->spam), ['reason' => 'Spam nomor telepon']);
        $this->actingAs($this->admin)
            ->post(route('admin.reviews.hide', $this->spam), ['reason' => 'Disembunyikan lagi'])
            ->assertSessionHasErrors(['reason' => 'Ulasan ini sudah disembunyikan.']);

        $this->assertSame([1, 5], $this->totals($this->product));
    }

    public function test_the_seller_sees_why_and_is_not_asked_to_reply(): void
    {
        $this->actingAs($this->admin)->post(route('admin.reviews.hide', $this->spam), ['reason' => 'Spam nomor telepon']);

        $this->actingAs($this->store->user)
            ->get(route('seller.reviews.index', ['filter' => 'semua']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('unansweredCount', 1) // only the visible 5-star one
                ->where('reviews.data.0.hidden_reason', 'Spam nomor telepon'));
    }

    public function test_the_admin_list_starts_with_low_ratings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reviews.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('filter', 'rendah')
                ->has('reviews.data', 1)
                ->where('reviews.data.0.rating', 1));
    }

    public function test_only_admins_moderate(): void
    {
        $this->actingAs($this->store->user)
            ->post(route('admin.reviews.hide', $this->spam), ['reason' => 'Saya tidak suka'])
            ->assertForbidden();

        $this->assertNull($this->spam->fresh()?->hidden_at);
    }

    public function test_totals_can_never_go_below_zero(): void
    {
        $this->product->forceFill(['rating_count' => 0, 'rating_sum' => 0])->save();

        $this->expectException(QueryException::class); // CHECK constraint
        $this->actingAs($this->admin)->withoutExceptionHandling()
            ->post(route('admin.reviews.hide', $this->spam), ['reason' => 'Spam nomor telepon']);
    }
}
