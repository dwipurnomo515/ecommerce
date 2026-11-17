<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A buyer's shipping addresses. Orders will COPY the address they ship
     * to, so editing or deleting one here never changes a past order.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('label', 30)->nullable();       // "Rumah", "Kantor"
            $table->string('recipient_name', 100);
            $table->string('phone', 20);                   // stored as 08xxxxxxxxxx
            $table->text('street');                        // jalan, nomor, RT/RW
            $table->string('notes', 200)->nullable();      // patokan for the courier

            // The RajaOngkir destination (sub-district level), used to price
            // shipping. The names are a copy, so showing an address never
            // needs the API.
            $table->unsignedInteger('destination_id');
            $table->string('province', 100);
            $table->string('city', 100);
            $table->string('district', 100);
            $table->string('subdistrict', 100);
            $table->string('postal_code', 10);

            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        // At most ONE primary address per user, enforced by the database:
        // a unique index that only covers rows WHERE is_primary (a Postgres
        // "partial index"). Non-primary rows are not limited.
        DB::statement('CREATE UNIQUE INDEX addresses_one_primary_per_user ON addresses (user_id) WHERE is_primary');
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
