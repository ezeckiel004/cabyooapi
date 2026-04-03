<?php
// database/migrations/2026_04_03_151854_add_admin_reply_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Ajout des champs pour contact_messages
        if (Schema::hasTable('contact_messages')) {
            if (!Schema::hasColumn('contact_messages', 'admin_reply')) {
                Schema::table('contact_messages', function (Blueprint $table) {
                    $table->text('admin_reply')->nullable()->after('message');
                });
            }
            if (!Schema::hasColumn('contact_messages', 'replied_at')) {
                Schema::table('contact_messages', function (Blueprint $table) {
                    $table->timestamp('replied_at')->nullable()->after('admin_reply');
                });
            }
        }

        // Ajout des champs pour support_tickets
        if (Schema::hasTable('support_tickets')) {
            if (!Schema::hasColumn('support_tickets', 'admin_reply')) {
                Schema::table('support_tickets', function (Blueprint $table) {
                    $table->text('admin_reply')->nullable()->after('message');
                });
            }
            if (!Schema::hasColumn('support_tickets', 'replied_at')) {
                Schema::table('support_tickets', function (Blueprint $table) {
                    $table->timestamp('replied_at')->nullable()->after('admin_reply');
                });
            }
        }
    }

    public function down()
    {
        // Suppression des champs pour contact_messages
        if (Schema::hasTable('contact_messages')) {
            if (Schema::hasColumn('contact_messages', 'admin_reply')) {
                Schema::table('contact_messages', function (Blueprint $table) {
                    $table->dropColumn('admin_reply');
                });
            }
            if (Schema::hasColumn('contact_messages', 'replied_at')) {
                Schema::table('contact_messages', function (Blueprint $table) {
                    $table->dropColumn('replied_at');
                });
            }
        }

        // Suppression des champs pour support_tickets
        if (Schema::hasTable('support_tickets')) {
            if (Schema::hasColumn('support_tickets', 'admin_reply')) {
                Schema::table('support_tickets', function (Blueprint $table) {
                    $table->dropColumn('admin_reply');
                });
            }
            if (Schema::hasColumn('support_tickets', 'replied_at')) {
                Schema::table('support_tickets', function (Blueprint $table) {
                    $table->dropColumn('replied_at');
                });
            }
        }
    }
};
