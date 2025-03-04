<?php

return [

    'column_toggle' => [

        'heading' => 'Սյունակներ',

    ],

    'columns' => [

        'actions' => [
            'label' => 'Գործողություն|Գործողություններ',
        ],

        'text' => [

            'actions' => [
                'collapse_list' => 'Ցույց տալ :count քիչ',
                'expand_list' => 'Ցույց տալ :count շատ',
            ],

            'more_list_items' => 'Եվ ևս :count',

        ],

    ],

    'fields' => [

        'bulk_select_page' => [
            'label' => 'Ընտրել / չընտրել բոլոր տարրերը զանգվածային գործողությունների համար։',
        ],

        'bulk_select_record' => [
            'label' => 'Ընտրել / չընտրել :key նյութը զանգվածային գործողությունների համար։',
        ],

        'bulk_select_group' => [
            'label' => 'Ընտրել / չընտրել :title խումբը զանգվածային գործողությունների համար։',
        ],

        'search' => [
            'label' => 'Որոնել',
            'placeholder' => 'Որոնել',
            'indicator' => 'Որոնել',
        ],

    ],

    'summary' => [

        'heading' => 'Ամփոփում',

        'subheadings' => [
            'all' => 'Ամբողջը :label',
            'group' => ':group Ամփոփումը',
            'page' => 'Այս էջը',
        ],

        'summarizers' => [

            'average' => [
                'label' => 'Միջին',
            ],

            'count' => [
                'label' => 'Հաշվել',
            ],

            'sum' => [
                'label' => 'Գումար',
            ],

        ],

    ],

    'actions' => [

        'disable_reordering' => [
            'label' => 'Ավարտեք գրառումների փոփոխությունը',
        ],

        'enable_reordering' => [
            'label' => 'Պատվիրելգրառումներ',
        ],

        'filter' => [
            'label' => 'Ֆիլտր',
        ],

        'group' => [
            'label' => 'Խումբ',
        ],

        'open_bulk_actions' => [
            'label' => 'Մեծ գործողություններ',
        ],

        'toggle_columns' => [
            'label' => 'Փոխել սյունակները',
        ],

    ],

    'empty' => [

        'heading' => 'Ոչ :model',

        'description' => 'Ստեղծեք :model սկսելու համար։',

    ],

    'filters' => [

        'actions' => [

            'apply' => [
                'label' => 'Կիրառել ֆիլտրերը',
            ],

            'remove' => [
                'label' => 'Հեռացնել ֆիլտրերը',
            ],

            'remove_all' => [
                'label' => 'Հեռացնելբոլոր ֆիլտրերը',
                'tooltip' => 'Հեռացնելբոլոր ֆիլտրերը',
            ],

            'reset' => [
                'label' => 'Վերականգնել',
            ],

        ],

        'heading' => 'Ֆիլտրեր',

        'indicator' => 'Ակտիվ ֆիլտրեր',

        'multi_select' => [
            'placeholder' => 'Բոլորը',
        ],

        'select' => [
            'placeholder' => 'Բոլորը',
        ],

        'trashed' => [

            'label' => 'Ջնջված գրառումներ',

            'only_trashed' => 'Միայն ջնջված գրառումները',

            'with_trashed' => 'Ջնջված գրառումներով',

            'without_trashed' => 'Առանց ջնջված գրառումների',

        ],

    ],

    'grouping' => [

        'fields' => [

            'group' => [
                'label' => 'Խմբավորել ըստ',
                'placeholder' => 'Խմբավորել ըստ',
            ],

            'direction' => [

                'label' => 'Խմբի ուղղություն',

                'options' => [
                    'asc' => 'Աճող',
                    'desc' => 'Իջնող',
                ],

            ],

        ],

    ],

    'reorder_indicator' => 'Քաշեք գրառումները ցանկալի հերթականությամբ։',

    'selection_indicator' => [

        'selected_count' => '1 գրառում ընտրված է|:count ընտրված գրառումներ',

        'actions' => [

            'select_all' => [
                'label' => 'Ընտրել բոլորը :count',
            ],

            'deselect_all' => [
                'label' => 'Չընտրել բոլորը',
            ],

        ],

    ],

    'sorting' => [

        'fields' => [

            'column' => [
                'label' => 'Դասավորել ըստ',
            ],

            'direction' => [

                'label' => 'Տեսակավորման ուղղություն',

                'options' => [
                    'asc' => 'Աճող',
                    'desc' => 'Իջնող',
                ],

            ],

        ],

    ],

];
