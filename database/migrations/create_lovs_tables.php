<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Domain\Field\Field;

use Amarenkov\MutableContent\Models\Lov\Item as LovItemModel;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('lovs', function (Blueprint $table) {
            $table->fieldsBase();
            
            $table->fieldsUpdatedAt();
            
            $table->softDeletes();

            $table->fieldsUpdatedBy();

            $table->fieldExtract(Field::COMMON_CODE_CODE)->type('varchar(255)');
            $table->fieldExtract(Field::COMMON_CODE_LABEL)->type('varchar(255)');

            $table->unique(Field::COMMON_CODE_CODE);
            $table->index(Field::COMMON_CODE_LABEL);
        });

        Schema::createWithLog('lov_items', function (Blueprint $table) {
            $table->fieldsBase();
            
            $table->fieldsUpdatedAt();
            
            $table->softDeletes();

            $table->fieldsUpdatedBy();

            $table->fieldExtract(LovItemModel::FIELD_LOV_ID)->type('int');
            $table->fieldExtract(Field::COMMON_CODE_CODE)->type('varchar(255)');
            $table->fieldExtract(Field::COMMON_CODE_LABEL)->type('varchar(255)');

            $table->index(LovItemModel::FIELD_LOV_ID);
            $table->index(Field::COMMON_CODE_CODE);
            $table->index(Field::COMMON_CODE_LABEL);
            $table->unique([LovItemModel::FIELD_LOV_ID, Field::COMMON_CODE_CODE]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExistsWithLog('lov_items');

        Schema::dropIfExistsWithLog('lovs');
    }
};
