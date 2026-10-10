<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('owners', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract('code')->type('varchar(20)');
            $table->unique('code');
        });

        Schema::createSchema('fixtures');

        Schema::createWithLog('fixtures.records', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract('code')->type('varchar(50)');
            $table->fieldExtract('owner_id')->type('int');
            $table->unique('code');
        });

        Schema::createWithLog('fixtures.tickets', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract('from')->type('varchar(20)');
        });
    }
};
