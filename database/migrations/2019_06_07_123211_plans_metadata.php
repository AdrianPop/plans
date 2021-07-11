<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PlansMetadata extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->mediumText('metadata')->after('duration')->nullable();
        });

        Schema::table('plans_features', function (Blueprint $table): void {
            $table->mediumText('metadata')->after('limit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });

        Schema::table('plans_features', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
    }
}
