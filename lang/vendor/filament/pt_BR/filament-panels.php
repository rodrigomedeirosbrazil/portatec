<?php

return [

    'pages' => [

        'dashboard' => [
            'title' => 'Dashboard',
        ],

        'auth' => [

            'login' => [
                'actions' => [
                    'login' => [
                        'label' => 'Entrar',
                    ],
                ],

                'form' => [
                    'email' => [
                        'label' => 'E-mail',
                    ],
                    'password' => [
                        'label' => 'Senha',
                    ],
                    'remember' => [
                        'label' => 'Lembrar-me',
                    ],
                ],

                'heading' => 'Entrar na sua conta',

                'messages' => [
                    'failed' => 'As credenciais informadas não correspondem com nossos registros.',
                    'throttled' => 'Muitas tentativas de login. Por favor, tente novamente em :seconds segundos.',
                ],

                'title' => 'Login',
            ],

            'register' => [
                'actions' => [
                    'register' => [
                        'label' => 'Registrar',
                    ],
                ],

                'form' => [
                    'email' => [
                        'label' => 'E-mail',
                    ],
                    'name' => [
                        'label' => 'Nome',
                    ],
                    'password' => [
                        'label' => 'Senha',
                    ],
                    'password_confirmation' => [
                        'label' => 'Confirmar senha',
                    ],
                ],

                'heading' => 'Registrar-se',

                'title' => 'Registro',
            ],

            'password-reset' => [

                'actions' => [
                    'reset' => [
                        'label' => 'Redefinir senha',
                    ],
                ],

                'form' => [
                    'email' => [
                        'label' => 'E-mail',
                    ],
                    'password' => [
                        'label' => 'Senha',
                    ],
                    'password_confirmation' => [
                        'label' => 'Confirmar senha',
                    ],
                ],

                'heading' => 'Redefinir senha',

                'messages' => [
                    'throttled' => 'Muitas tentativas. Por favor, tente novamente em :seconds segundos.',
                ],

                'title' => 'Redefinir senha',

            ],

            'password-confirmation' => [
                'actions' => [
                    'confirm' => [
                        'label' => 'Confirmar',
                    ],
                ],

                'form' => [
                    'password' => [
                        'label' => 'Senha',
                    ],
                ],

                'heading' => 'Confirmar senha',

                'title' => 'Confirmar senha',
            ],

            'email-verification' => [
                'actions' => [
                    'resend' => [
                        'label' => 'Reenviar e-mail',
                    ],
                ],

                'heading' => 'Verifique seu e-mail',

                'messages' => [
                    'notification_not_received' => 'Não recebeu o e-mail que enviamos?',
                    'notification_sent' => 'Enviamos um e-mail para :email contendo um link de verificação.',
                ],

                'title' => 'Verificar e-mail',
            ],

        ],

        'tenancy' => [

            'register' => [
                'actions' => [
                    'register' => [
                        'label' => 'Registrar',
                    ],
                ],

                'heading' => 'Registrar :tenant',

                'title' => 'Registrar :tenant',
            ],

        ],

    ],

    'layout' => [

        'direction' => 'ltr',

        'sidebar' => [

            'collapse' => [
                'label' => 'Recolher menu',
            ],

            'expand' => [
                'label' => 'Expandir menu',
            ],

        ],

        'tenant-menu' => [
            'label' => 'Seletor de inquilino',
        ],

    ],

];
