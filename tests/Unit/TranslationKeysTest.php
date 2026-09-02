<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * As chaves de navegação são cadastradas de uma vez pela tarefa de fundação do
 * plano de reorganização da navegação, para que as tarefas seguintes apenas as
 * consumam. Este teste fixa esse contrato.
 */
class TranslationKeysTest extends TestCase
{
    public function test_navigation_keys_exist(): void
    {
        $keys = [
            'nav_group_operation',
            'nav_group_setup',
            'nav_control',
            'control_all_places',
            'control_index_title',
            'breadcrumb_home',
            'place_select_all',
            'place_select_label',
            'devices_status_label',
            'devices_status_online',
            'devices_status_offline',
            'devices_only_unassigned',
            'place_booking_sources_heading',
            'place_add_booking_source',
            'place_no_booking_sources',
            'dashboard_active_codes_heading',
            'user_menu_label',
        ];

        foreach ($keys as $key) {
            $this->assertNotSame(
                "app.{$key}",
                trans("app.{$key}"),
                "A chave de tradução [app.{$key}] não existe."
            );
        }
    }

    public function test_dashboard_nav_label_is_translated_to_portuguese(): void
    {
        $this->assertSame('Início', trans('app.nav_dashboard'));
    }

    public function test_removed_key_is_gone(): void
    {
        $this->assertSame(
            'app.nav_bookings_integrations',
            trans('app.nav_bookings_integrations'),
            'A chave [app.nav_bookings_integrations] deveria ter sido removida: '
            .'os dois cabeçalhos de integrações passam a usar [app.integrations].'
        );
    }
}
