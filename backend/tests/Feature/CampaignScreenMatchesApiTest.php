<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * WALK-2 (2026-11-11), antes de recorrerlo: la pantalla de campaña contradecía a la API.
 *
 *  - Al borrar un adset preguntaba "Delete adset and all its ads?", y la API NUNCA borra
 *    los anuncios: vuelven al backlog (§21; OwnershipChainTest lo sujeta del lado API).
 *    Un aviso que asusta con algo que no pasa es un control que miente (EH-10).
 *  - Tras borrar no recargaba el backlog: los anuncios desaparecían hasta refrescar (BR-8).
 *  - Mover UN anuncio a otro adset existía en la API (POST adsets/move) y no en pantalla:
 *    el paso W2-1 no tenía puerta (EH-19).
 */
class CampaignScreenMatchesApiTest extends TestCase
{
    private function screen(): string
    {
        return file_get_contents(base_path('../frontend/src/app/features/client/campaigns/campaign-detail.component.ts'));
    }

    public function test_deleting_an_adset_does_not_threaten_to_delete_its_ads(): void
    {
        $this->assertStringNotContainsString('and all its ads', $this->screen());
        $this->assertStringContainsString('Unassigned spaces', $this->screen());
    }

    public function test_deleting_an_adset_reloads_the_backlog_its_ads_fall_into(): void
    {
        $this->assertMatchesRegularExpression('/deleteAdset\(adset: Adset\)[\s\S]*?this\.loadOrphans\(\);[\s\S]*?\n  }\n/', $this->screen());
    }

    public function test_one_ad_can_be_moved_to_another_adset_from_the_screen(): void
    {
        $this->assertMatchesRegularExpression('/moveAd\(ad: Ad[\s\S]*?adsets\/move[\s\S]*?ad_ids: \[ad\.id\]/', $this->screen());
        $this->assertStringContainsString('(ngModelChange)="moveAd(ad, $event)"', $this->screen());
    }
}
