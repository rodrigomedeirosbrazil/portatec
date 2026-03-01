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
    'gpio' => 'GPIO',
    'places' => 'Locais',
    'place_device' => 'Dispositivo do Local',
    'place_devices' => 'Dispositivos do Local',
    'command_log' => 'Registro de Comando',
    'command_logs' => 'Registros de Comandos',
    'access_pin' => 'PIN de Acesso',
    'access_pins' => 'PINs de Acesso',
    'user' => 'Usuário',
    'users' => 'Usuários',
    'role' => 'Função',
    'roles' => 'Funções',
    'view' => 'Visualizar',
    'device_type' => 'Tipo de dispositivo',

    // Ações e notificações
    'command_sent' => 'Comando enviado.',
    'command_ack' => 'Comando executado.',
    'sending' => 'Enviando...',
    'device_not_found' => 'Dispositivo não encontrado.',
    'error_sending_command' => 'Erro ao enviar comando: :message',
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
    'push' => 'Acionar',

    // Campos
    'name' => 'Nome',
    'type' => 'Tipo',
    'description' => 'Descrição',
    'value' => 'Valor',
    'pin' => 'PIN',
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
    'start' => 'Início',
    'end' => 'Fim',
    'slug' => 'Slug',
    'timestamp' => 'Timestamp',
    'result' => 'Resultado',
    'external_id' => 'ID Externo',
    'external_device_id' => 'ID Externo do Dispositivo',
    'brand' => 'Marca',
    'default_pin' => 'PIN Padrão',
    'guest_name' => 'Nome do Hóspede',
    'check_in' => 'Check-in',
    'check_out' => 'Check-out',
    'access_code' => 'Código de Acesso',
    'booking' => 'Reserva',
    'integration' => 'Integração',
    'platform' => 'Plataforma',
    'places_count' => 'Quantidade de Locais',
    'integrations_count' => 'Quantidade de Integrações',
    'device_functions_count' => 'Quantidade de Funções',
    'control_devices' => 'Controlar Dispositivos',
    'sync_bookings' => 'Sincronizar Reservas',
    'sync_success' => 'Sincronização concluída com sucesso',
    'sync_error' => 'Erro ao sincronizar',
    'valid' => 'Válido',
    'expired' => 'Expirado',
    'success' => 'Sucesso',
    'failed' => 'Falhou',
    'invalid' => 'Inválido',

    // Seções e descrições do formulário
    'device_information' => 'Informações do Dispositivo',
    'device_information_description' => 'Configure as informações básicas do dispositivo.',
    'device_functions' => 'Funções do Dispositivo',
    'device_functions_description' => 'Gerencie as funções e capacidades específicas deste dispositivo.',
    'add_device_function' => 'Adicionar Função do Dispositivo',
    'device_function' => 'Função do Dispositivo',
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
        'device_function' => 'Função do dispositivo',
        'command_type' => 'Tipo de comando',
        'command_payload' => 'Payload do comando',
        'device_type' => 'Tipo de dispositivo',
        'device_function_type' => 'Tipo de função do dispositivo',
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

    // Status do dispositivo
    'device_statuses' => [
        'open' => 'Aberto',
        'closed' => 'Fechado',
        'on' => 'Ligado',
        'off' => 'Desligado',
    ],
];
