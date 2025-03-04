<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Traduções Específicas da Aplicação
    |--------------------------------------------------------------------------
    |
    | As seguintes linhas de idioma são usadas em várias partes da aplicação.
    |
    */

    // Recursos e modelos
    'device' => 'Dispositivo',
    'devices' => 'Dispositivos',
    'place' => 'Local',
    'places' => 'Locais',
    'place_device' => 'Dispositivo do Local',
    'place_devices' => 'Dispositivos do Local',
    'command_log' => 'Registro de Comando',
    'command_logs' => 'Registros de Comandos',
    'user' => 'Usuário',
    'users' => 'Usuários',
    'role' => 'Função',
    'roles' => 'Funções',

    // Ações e notificações
    'command_sent' => 'Comando enviado.',
    'toggle_device' => 'Alternar dispositivo',
    'push_button' => 'Pressionar botão',
    'ask_for_status' => 'Verificar status',
    'ask_for_availability' => 'Verificar disponibilidade',
    'device_status' => 'Status do dispositivo',
    'device_availability' => 'Disponibilidade do dispositivo',
    'device_online' => 'Dispositivo online',
    'device_offline' => 'Dispositivo offline',
    'device_on' => 'Ligado',
    'device_off' => 'Desligado',

    // Campos
    'name' => 'Nome',
    'type' => 'Tipo',
    'topic' => 'Tópico',
    'command_topic' => 'Tópico de comando',
    'availability_topic' => 'Tópico de disponibilidade',
    'payload_on' => 'Payload para ligar',
    'payload_off' => 'Payload para desligar',
    'created_at' => 'Criado em',
    'updated_at' => 'Atualizado em',
    'deleted_at' => 'Excluído em',

    // Tipos de dispositivos
    'device_types' => [
        'switch' => 'Interruptor',
        'sensor' => 'Sensor',
        'button' => 'Botão',
    ],

    // Funções de local
    'place_roles' => [
        'admin' => 'Administrador',
        'host' => 'Anfitrião',
    ],
];
