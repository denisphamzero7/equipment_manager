<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create table only if it doesn't exist to make the migration idempotent
        if (!Schema::hasTable('rooms')) {
            Schema::create('rooms', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('room_number');
                $table->unsignedBigInteger('room_type_id')->nullable();
                $table->string('status')->default('ready');
                $table->decimal('price', 12, 2)->nullable();
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // Add foreign keys only if referenced tables exist. Wrap in try/catch to avoid duplicate-key errors.
        if (Schema::hasTable('rooms')) {
            if (Schema::hasTable('room_types')) {
                try {
                    Schema::table('rooms', function (Blueprint $table) {
                        $table->foreign('room_type_id')->references('id')->on('room_types')->nullOnDelete();
                    });
                } catch (\Exception $e) {
                    // ignore - foreign key may already exist or DB may not support introspection uniformly
                }
            }

            if (Schema::hasTable('organizations')) {
                try {
                    Schema::table('rooms', function (Blueprint $table) {
                        $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
                    });
                } catch (\Exception $e) {
                    // ignore
                }
            }

            if (Schema::hasTable('users')) {
                try {
                    Schema::table('rooms', function (Blueprint $table) {
                        $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                        $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
                    });
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys if they exist, then drop table
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                // Use try/catch to suppress errors if indexes don't exist in some DB drivers
                try {
                    $table->dropForeign(['room_type_id']);
                } catch (\Exception $e) {
                    // ignore
                }
                try {
                    $table->dropForeign(['organization_id']);
                } catch (\Exception $e) {
                    // ignore
                }
                try {
                    $table->dropForeign(['created_by']);
                } catch (\Exception $e) {
                    // ignore
                }
                try {
                    $table->dropForeign(['updated_by']);
                } catch (\Exception $e) {
                    // ignore
                }
            });

            Schema::dropIfExists('rooms');
        }
    }
};
