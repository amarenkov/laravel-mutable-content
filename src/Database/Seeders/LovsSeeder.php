<?php

namespace Amarenkov\MutableContent\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Amarenkov\MutableContent\Models\Lov\Lov as LovModel;
use Amarenkov\MutableContent\Models\Lov\Item as LovItemModel;

use Amarenkov\MutableContent\Domain\Field\Field;

use Amarenkov\MutableContent\Domain\LovRegistry;

class LovsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(LovRegistry $registry): void
    {
        foreach ($registry->getSystemLovs() as $lov) {
            $lovModel = LovModel::where(Field::COMMON_CODE_CODE, $lov->code)->withTrashed()->first();

            if (!$lovModel) {
                $lovModel = new LovModel();
                $lovModel->setSystemFields($lov);
                $lovModel->mergeWithFields($lov->attributesToArray());
                $lovModel->setUpdatedByIfDirty('LovsSeeder');
                $lovModel->save();
            } else {
                $lovModel->setSystemFields($lov);
                $lovModel->setUpdatedByIfDirty('LovsSeeder');
                $lovModel->save();
            }

            foreach ($lov->items as $item) {
                $lovItemModel = $lovModel->items()->where(Field::COMMON_CODE_CODE, $item->code)->first();
                
                if (!$lovItemModel) {
                    $lovItemModel = new LovItemModel();
                    $lovItemModel->{LovItemModel::FIELD_LOV_ID} = $lovModel->id;
                    $lovItemModel->setSystemFields($item);
                    $lovItemModel->mergeWithFields($item->toArray());
                    $lovItemModel->setUpdatedByIfDirty('LovsSeeder');
                    $lovItemModel->save();
                } else {
                    $lovItemModel->setSystemFields($item);
                    $lovItemModel->setUpdatedByIfDirty('LovsSeeder');
                    $lovItemModel->save();
                }
            }
        }
    }
}
