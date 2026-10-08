<?php

return [
    'in_lov' => 'The :attribute must be an item of the ":lov" LOV.',
    'in_lovs' => 'The :attribute must be a LOV code.',
    'mutable_class' => 'The :attribute must be a mutable class.',
    'object' => 'The :attribute must be a ":class" object.',
    'object_code' => 'The :attribute must be a code of a ":class" object.',
    'object_code_ambiguous' => 'The :attribute code belongs to several ":class" objects.',

    'field_type_system' => 'The "System" field type is available only for fields declared in code.',
    'field_type_setting' => 'Invalid value ":value" of field type setting ":setting".',
    'usage_target' => 'A field is bound either to a class or to a LOV — specify exactly one.',

    'field_code' => [
        'format' => 'Field code ":code" must start with a latin letter and contain only lowercase latin letters, digits and underscores.',
        'max' => 'Field code ":code" must not be longer than :max characters.',
        'reserved' => 'Field code ":code" is reserved.',
        'reserved_in_class' => 'Field code ":code" is already taken in ":class".',
    ],
];
