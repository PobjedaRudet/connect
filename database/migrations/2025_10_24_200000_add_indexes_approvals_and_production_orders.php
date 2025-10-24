<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                if (Schema::hasColumn('production_orders', 'Status')) {
                    $table->index('Status', 'po_status_idx');
                }
                if (Schema::hasColumn('production_orders', 'is_void')) {
                    $table->index('is_void', 'po_is_void_idx');
                }
                if (Schema::hasColumn('production_orders', 'partner_id')) {
                    $table->index('partner_id', 'po_partner_idx');
                }
                if (Schema::hasColumn('production_orders', 'user_id')) {
                    $table->index('user_id', 'po_user_idx');
                }
                if (Schema::hasColumn('production_orders', 'created_at')) {
                    $table->index('created_at', 'po_created_idx');
                }
            });
        }

        if (Schema::hasTable('approvals')) {
            Schema::table('approvals', function (Blueprint $table) {
                if (Schema::hasColumn('approvals', 'order_id')) {
                    $table->index('order_id', 'ap_order_idx');
                }
                if (Schema::hasColumn('approvals', 'Funkcija')) {
                    $table->index('Funkcija', 'ap_funkcija_idx');
                }
                if (Schema::hasColumn('approvals', 'Odobreno')) {
                    $table->index('Odobreno', 'ap_odobreno_idx');
                }
                if (Schema::hasColumn('approvals', 'created_at')) {
                    $table->index('created_at', 'ap_created_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'po_status_idx');
                $this->dropIndexIfExists($table, 'po_is_void_idx');
                $this->dropIndexIfExists($table, 'po_partner_idx');
                $this->dropIndexIfExists($table, 'po_user_idx');
                $this->dropIndexIfExists($table, 'po_created_idx');
            });
        }

        if (Schema::hasTable('approvals')) {
            Schema::table('approvals', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'ap_order_idx');
                $this->dropIndexIfExists($table, 'ap_funkcija_idx');
                $this->dropIndexIfExists($table, 'ap_odobreno_idx');
                $this->dropIndexIfExists($table, 'ap_created_idx');
            });
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $index): void
    {
        try {
            $table->dropIndex($index);
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
