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
    'view' => 'Visualizar',

    // Ações e notificações
    'command_sent' => 'Comando enviado.',
    'command_ack' => 'Comando executado.',
    'sending' => 'Enviando...',
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
    'chip_id' => 'Chip ID',
    'topic' => 'Tópico',
    'status' => 'Status',
    'online' => 'Online',
    'offline' => 'Offline',
    'command_topic' => 'Tópico de comando',
    'availability_topic' => 'Tópico de disponibilidade',
    'payload_on' => 'Payload para ligar',
    'payload_off' => 'Payload para desligar',
    'created_at' => 'Criado em',
    'updated_at' => 'Atualizado em',
    'deleted_at' => 'Excluído em',

    // Seções e descrições do formulário
    'device_information' => 'Informações do Dispositivo',
    'device_information_description' => 'Configure as informações básicas do dispositivo.',
    'device_status_description' => 'Visualize o status atual e última sincronização do dispositivo.',
    'device_places_description' => 'Associe o dispositivo aos locais onde ele está instalado.',

    // Status e estados
    'current_status' => 'Status Atual',
    'new_device' => 'Novo Dispositivo',
    'last_sync' => 'Última Sincronização',
    'never_synced' => 'Nunca Sincronizado',

    // Tipos de dispositivos
    'device_types' => [
        'switch' => 'Interruptor',
        'sensor' => 'Sensor',
        'button' => 'Botão',
    ],

    'command_log_fields' => [
        'id' => 'ID',
        'user' => 'Usuário',
        'place' => 'Local',
        'device' => 'Dispositivo',
        'command_type' => 'Tipo de comando',
        'command_payload' => 'Payload do comando',
        'device_type' => 'Tipo de dispositivo',
        'ip_address' => 'Endereço IP',
        'user_agent' => 'User Agent',
        'created_at' => 'Criado em',
        'updated_at' => 'Atualizado em',
    ],

    // Funções de local
    'place_roles' => [
        'admin' => 'Administrador',
        'host' => 'Anfitrião',
    ],
];
