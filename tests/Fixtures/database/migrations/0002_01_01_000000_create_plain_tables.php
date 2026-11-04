<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        DB::table('members')->insert(['name' => 'Existing']);

        Schema::table('members', function (Blueprint $table) {
            $table->mutableFields();

            $table->fieldExtract('nickname')->type('varchar(50)');
        });

        Schema::addLog('members', columns: ['name', 'status']);

        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->softDeletes();
        });

        Schema::addLog('assets', columns: ['title']);

        Schema::create('composite_keys', function (Blueprint $table) {
            $table->integer('first');
            $table->integer('second');
            $table->primary(['first', 'second']);
        });
    }
};
