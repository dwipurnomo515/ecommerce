<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The option list as the seller typed it, IN ORDER:
     *   [{"name": "Warna", "values": ["Hitam", "Putih"]}, {"name": "Ukuran", ...}]
     *
     * It can't be rebuilt from the variants: jsonb stores object keys sorted
     * (by length, then bytes), so {"Warna", "Ukuran"} comes back as
     * {"Ukuran", "Warna"} and the edit form would shuffle the seller's order.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('options')->default('[]');
        });

        // Backfill products created before this column existed, from their
        // variants. Best effort: the original order is already lost for them.
        // Query builder, not models: a migration must keep working even after
        // the model classes change.
        DB::table('products')->orderBy('id')->each(function (object $product): void {
            $options = [];

            $variants = DB::table('product_variants')
                ->where('product_id', $product->id)
                ->orderBy('id')
                ->pluck('options');

            foreach ($variants as $json) {
                foreach (json_decode($json, true) as $name => $value) {
                    $options[$name] ??= [];
                    if (! in_array($value, $options[$name], true)) {
                        $options[$name][] = $value;
                    }
                }
            }

            $list = [];
            foreach ($options as $name => $values) {
                $list[] = ['name' => $name, 'values' => $values];
            }

            DB::table('products')->where('id', $product->id)->update(['options' => json_encode($list)]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
