<?php

return [

    'label' => 'Հարցման կոնստրուկտոր',

    'form' => [

        'operator' => [
            'label' => 'Օպերատոր',
        ],

        'or_groups' => [

            'label' => 'Խմբեր',

            'block' => [
                'label' => 'Անձատում (կամ)',
                'or' => 'կամ',
            ],

        ],

        'rules' => [

            'label' => 'Կանոններ',

            'item' => [
                'and' => 'եվ',
            ],

        ],

    ],

    'no_rules' => '(Անկանոն)',

    'item_separators' => [
        'and' => 'եվ',
        'or' => 'կամ',
    ],

    'operators' => [

        'is_filled' => [

            'label' => [
                'direct' => 'Լրացված է',
                'inverse' => 'Դատարկ է',
            ],

            'summary' => [
                'direct' => ':attribute լրացված է',
                'inverse' => ':attribute դատարկ է',
            ],

        ],

        'boolean' => [

            'is_true' => [

                'label' => [
                    'direct' => 'Ճիշտ է',
                    'inverse' => 'Սխալ է',
                ],

                'summary' => [
                    'direct' => ':attribute ճիշտ է',
                    'inverse' => ':attribute սխալ է',
                ],

            ],

        ],

        'date' => [

            'is_after' => [

                'label' => [
                    'direct' => 'Հետո է',
                    'inverse' => 'Հետո չէ',
                ],

                'summary' => [
                    'direct' => ':attribute հետո է :date',
                    'inverse' => ':attribute հետո չէ :date',
                ],

            ],

            'is_before' => [

                'label' => [
                    'direct' => 'Նախկինում է',
                    'inverse' => 'Նախկինում չէ',
                ],

                'summary' => [
                    'direct' => ':attribute նախկինում է :date',
                    'inverse' => ':attribute նախկինում չէ :date',
                ],

            ],

            'is_date' => [

                'label' => [
                    'direct' => 'Ամսաթիվ է',
                    'inverse' => 'Ամսաթիվ չէ',
                ],

                'summary' => [
                    'direct' => ':attribute է :date',
                    'inverse' => ':attribute չէ :date',
                ],

            ],

            'is_month' => [

                'label' => [
                    'direct' => 'Ամիս է',
                    'inverse' => 'Ամիս չէ',
                ],

                'summary' => [
                    'direct' => ':attribute է :month',
                    'inverse' => ':attribute չէt :month',
                ],

            ],

            'is_year' => [

                'label' => [
                    'direct' => 'Տարի է',
                    'inverse' => 'Տարի չէ',
                ],

                'summary' => [
                    'direct' => ':attribute է :year',
                    'inverse' => ':attribute չէ :year',
                ],

            ],

            'form' => [

                'date' => [
                    'label' => 'Օր',
                ],

                'month' => [
                    'label' => 'Ամիս',
                ],

                'year' => [
                    'label' => 'Տարի',
                ],

            ],

        ],

        'number' => [

            'equals' => [

                'label' => [
                    'direct' => 'Հավասար է',
                    'inverse' => 'Հավասար չէ',
                ],

                'summary' => [
                    'direct' => ':attribute հավասար է :number',
                    'inverse' => ':attribute հավասար չէ :number',
                ],

            ],

            'is_max' => [

                'label' => [
                    'direct' => 'Առավելագույնն է',
                    'inverse' => 'Ավելին քան',
                ],

                'summary' => [
                    'direct' => ':attribute առավելագույնն է :number',
                    'inverse' => ':attribute ավելին քան :number',
                ],

            ],

            'is_min' => [

                'label' => [
                    'direct' => 'Նվազագույն է',
                    'inverse' => 'Ավելի քիչ, քան',
                ],

                'summary' => [
                    'direct' => ':attribute նվազագույն է :number',
                    'inverse' => ':attribute ավելի քիչ, քան :number',
                ],

            ],

            'aggregates' => [

                'average' => [
                    'label' => 'Միջին',
                    'summary' => 'Միջին :attribute',
                ],

                'max' => [
                    'label' => 'Առավելագույն',
                    'summary' => 'Առավելագույն :attribute',
                ],

                'min' => [
                    'label' => 'Նվազագույն',
                    'summary' => 'Նվազագույն :attribute',
                ],

                'sum' => [
                    'label' => 'Գումար',
                    'summary' => 'Գումարը :attribute',
                ],

            ],

            'form' => [

                'aggregate' => [
                    'label' => 'Կուտակային',
                ],

                'number' => [
                    'label' => 'Թիվ',
                ],

            ],

        ],

        'relationship' => [

            'equals' => [

                'label' => [
                    'direct' => 'Has',
                    'inverse' => 'Does not have',
                ],

                'summary' => [
                    'direct' => 'Has :count :relationship',
                    'inverse' => 'Does not have :count :relationship',
                ],

            ],

            'has_max' => [

                'label' => [
                    'direct' => 'Has maximum',
                    'inverse' => 'Has more than',
                ],

                'summary' => [
                    'direct' => 'Has maximum :count :relationship',
                    'inverse' => 'Has more than :count :relationship',
                ],

            ],

            'has_min' => [

                'label' => [
                    'direct' => 'Has minimum',
                    'inverse' => 'Has less than',
                ],

                'summary' => [
                    'direct' => 'Has minimum :count :relationship',
                    'inverse' => 'Has less than :count :relationship',
                ],

            ],

            'is_empty' => [

                'label' => [
                    'direct' => 'Is empty',
                    'inverse' => 'Is not empty',
                ],

                'summary' => [
                    'direct' => ':relationship is empty',
                    'inverse' => ':relationship is not empty',
                ],

            ],

            'is_related_to' => [

                'label' => [

                    'single' => [
                        'direct' => 'Is',
                        'inverse' => 'Is not',
                    ],

                    'multiple' => [
                        'direct' => 'Contains',
                        'inverse' => 'Does not contain',
                    ],

                ],

                'summary' => [

                    'single' => [
                        'direct' => ':relationship is :values',
                        'inverse' => ':relationship is not :values',
                    ],

                    'multiple' => [
                        'direct' => ':relationship contains :values',
                        'inverse' => ':relationship does not contain :values',
                    ],

                    'values_glue' => [
                        0 => ', ',
                        'final' => ' or ',
                    ],

                ],

                'form' => [

                    'value' => [
                        'label' => 'Value',
                    ],

                    'values' => [
                        'label' => 'Values',
                    ],

                ],

            ],

            'form' => [

                'count' => [
                    'label' => 'Count',
                ],

            ],

        ],

        'select' => [

            'is' => [

                'label' => [
                    'direct' => 'Is',
                    'inverse' => 'Is not',
                ],

                'summary' => [
                    'direct' => ':attribute is :values',
                    'inverse' => ':attribute is not :values',
                    'values_glue' => [
                        ', ',
                        'final' => ' or ',
                    ],
                ],

                'form' => [

                    'value' => [
                        'label' => 'Value',
                    ],

                    'values' => [
                        'label' => 'Values',
                    ],

                ],

            ],

        ],

        'text' => [

            'contains' => [

                'label' => [
                    'direct' => 'Contains',
                    'inverse' => 'Does not contain',
                ],

                'summary' => [
                    'direct' => ':attribute contains :text',
                    'inverse' => ':attribute does not contain :text',
                ],

            ],

            'ends_with' => [

                'label' => [
                    'direct' => 'Ends with',
                    'inverse' => 'Does not end with',
                ],

                'summary' => [
                    'direct' => ':attribute ends with :text',
                    'inverse' => ':attribute does not end with :text',
                ],

            ],

            'equals' => [

                'label' => [
                    'direct' => 'Equals',
                    'inverse' => 'Does not equal',
                ],

                'summary' => [
                    'direct' => ':attribute equals :text',
                    'inverse' => ':attribute does not equal :text',
                ],

            ],

            'starts_with' => [

                'label' => [
                    'direct' => 'Starts with',
                    'inverse' => 'Does not start with',
                ],

                'summary' => [
                    'direct' => ':attribute starts with :text',
                    'inverse' => ':attribute does not start with :text',
                ],

            ],

            'form' => [

                'text' => [
                    'label' => 'Text',
                ],

            ],

        ],

    ],

    'actions' => [

        'add_rule' => [
            'label' => 'Add rule',
        ],

        'add_rule_group' => [
            'label' => 'Add rule group',
        ],

    ],

];
