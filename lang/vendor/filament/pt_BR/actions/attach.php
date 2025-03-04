<?php

return [

    'single' => [

        'label' => 'Anexar',

        'modal' => [

            'heading' => 'Anexar :label',

            'fields' => [

                'record_id' => [
                    'label' => 'Registro',
                ],

            ],

            'actions' => [

                'attach' => [
                    'label' => 'Anexar',
                ],

                'attach_another' => [
                    'label' => 'Anexar e anexar outro',
                ],

            ],

        ],

        'notifications' => [

            'attached' => [
                'title' => 'Anexado',
            ],

        ],

    ],

    'multiple' => [

        'label' => 'Anexar selecionados',

        'modal' => [

            'heading' => 'Anexar :label selecionados',

            'fields' => [

                'record_ids' => [
                    'label' => 'Registros',
                ],

            ],

            'actions' => [

                'attach' => [
                    'label' => 'Anexar',
                ],

                'attach_another' => [
                    'label' => 'Anexar e anexar outros',
                ],

            ],

        ],

        'notifications' => [

            'attached' => [
                'title' => 'Anexados',
            ],

        ],

    ],

];
