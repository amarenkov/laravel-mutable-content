<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\Usage;

use Amarenkov\MutableContent\Models\Field\Usage as UsageModel;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('fields', function (Blueprint $table) {
            $table->fieldsBase();
            
            $table->fieldsUpdatedAt();
            
            $table->softDeletes();

            $table->fieldExtract(Field::COMMON_CODE_CODE)->type('varchar(255)');
            $table->fieldExtract(Field::COMMON_CODE_LABEL)->type('varchar(255)');

            $table->unique(Field::COMMON_CODE_CODE);
            $table->index(Field::COMMON_CODE_LABEL);
        });

        Schema::createWithLog('fields_usage', function (Blueprint $table) {
            $table->fieldsBase();
            
            $table->fieldsUpdatedAt();
            
            $table->softDeletes();

            $table->fieldExtract(UsageModel::FIELD_FIELD_ID)->type('int');
            $table->fieldExtract(Usage::CODE_SCOPE)->type('varchar(255)');

            $table->index(UsageModel::FIELD_FIELD_ID);
            $table->index(Usage::CODE_SCOPE);

            $table->unique([UsageModel::FIELD_FIELD_ID, Usage::CODE_SCOPE]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExistsWithLog('fields_usage');
        
        Schema::dropIfExistsWithLog('fields');
    }
};
