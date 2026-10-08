<?php

namespace Amarenkov\MutableContent\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Amarenkov\MutableContent\Domain\Field\Field;

use Amarenkov\MutableContent\Models\Field\Field as FieldModel;
use Amarenkov\MutableContent\Models\Field\Usage as UsageModel;

use Amarenkov\MutableContent\Domain\MutableClassRegistry;

class FieldsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(MutableClassRegistry $registry): void
    {
        foreach ($registry->getSystemFields() as $field) {
            $fieldModel = FieldModel::where(Field::COMMON_CODE_CODE, $field->code)->withTrashed()->first();

            if (!$fieldModel) {
                $fieldModel = new FieldModel();
                $fieldModel->mergeWithFields($field->attributesToArray());
                $fieldModel->setSystemFields($field);
                $fieldModel->setUpdatedByIfDirty('FieldsSeeder');
                $fieldModel->save();
            } else {
                $fieldModel->setSystemFields($field);
                $fieldModel->setUpdatedByIfDirty('FieldsSeeder');
                $fieldModel->save();
            }

            foreach ($field->usages as $usage) {
                $newUsageModel = new UsageModel();
                $newUsageModel->{UsageModel::FIELD_FIELD_ID} = $fieldModel->id;
                $newUsageModel->mergeWithFields($usage->toArray());
                $newUsageModel->setSystemFields($usage);

                $usageModel = $fieldModel->usages()->where(UsageModel::CODE_SCOPE, $newUsageModel->{UsageModel::CODE_SCOPE})->first();

                if (!$usageModel) {
                    $usageModel = $newUsageModel;
                } else {
                    $usageModel->setSystemFields($usage);
                }

                $usageModel->setUpdatedByIfDirty('FieldsSeeder');
                $usageModel->save();
            }
        }
    }
}
