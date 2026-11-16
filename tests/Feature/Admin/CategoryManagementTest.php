<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    // --- Access -----------------------------------------------------------

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
    }

    public function test_regular_users_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.categories.store'), ['name' => 'Hack'])->assertForbidden();
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_admins_see_all_categories_with_product_counts(): void
    {
        $fashion = Category::factory()->create(['name' => 'Fashion']);
        Category::factory()->childOf($fashion)->create();
        Product::factory()->for($fashion)->count(2)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/categories/index')
                ->has('categories', 2)
                ->where('categories.0.products_count', 2)
                ->where('maxDepth', 3));
    }

    // --- Create -----------------------------------------------------------

    public function test_the_slug_is_generated_from_the_name_when_left_empty(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Baju Pria & Wanita', 'slug' => ''])
            ->assertRedirect(route('admin.categories.index'))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertDatabaseHas('categories', ['name' => 'Baju Pria & Wanita', 'slug' => 'baju-pria-wanita', 'parent_id' => null]);
    }

    public function test_slugs_must_be_unique(): void
    {
        Category::factory()->create(['slug' => 'fashion']);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Fashion'])
            ->assertSessionHasErrors('slug');
    }

    public function test_categories_can_be_nested_three_levels_but_not_four(): void
    {
        $level1 = Category::factory()->create();
        $level2 = Category::factory()->childOf($level1)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Level 3', 'parent_id' => $level2->id])
            ->assertSessionHasNoErrors();

        $level3 = Category::where('name', 'Level 3')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Level 4', 'parent_id' => $level3->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Level 4']);
    }

    // --- Update -----------------------------------------------------------

    public function test_a_category_can_be_renamed_and_keep_its_own_slug(): void
    {
        $category = Category::factory()->create(['name' => 'Elektronik', 'slug' => 'elektronik']);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Elektronik & Gadget',
                'slug' => 'elektronik',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertSame('Elektronik & Gadget', $category->name);
        $this->assertFalse($category->is_active);
    }

    public function test_a_category_cannot_become_its_own_parent_or_child(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        // Check the MESSAGE, not just "some error": with MAX_DEPTH = 3 a loop
        // also breaks the depth rule, which would hide a missing loop check.
        $loopError = ['parent_id' => 'Kategori tidak bisa dipindahkan ke dalam dirinya sendiri atau subkategorinya.'];

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $parent), ['name' => $parent->name, 'parent_id' => $parent->id])
            ->assertSessionHasErrors($loopError);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $parent), ['name' => $parent->name, 'parent_id' => $child->id])
            ->assertSessionHasErrors($loopError);

        $this->assertNull($parent->fresh()?->parent_id);
    }

    public function test_moving_a_category_counts_the_levels_below_it(): void
    {
        // A > B (2 levels) moved under X (level 1) would make A level 2, B level 3: OK.
        // Moved under Y (level 2) it would push B to level 4: rejected.
        $a = Category::factory()->create();
        Category::factory()->childOf($a)->create();
        $x = Category::factory()->create();
        $y = Category::factory()->childOf($x)->create();

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $a), ['name' => $a->name, 'parent_id' => $y->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $a), ['name' => $a->name, 'parent_id' => $x->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($x->id, $a->fresh()?->parent_id);
    }

    // --- Delete -----------------------------------------------------------

    public function test_an_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertModelMissing($category);
    }

    public function test_a_category_with_subcategories_cannot_be_deleted(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $parent))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertModelExists($parent);
    }

    public function test_a_category_used_by_a_deleted_product_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create()->delete(); // soft-deleted

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertModelExists($category);
    }
}
