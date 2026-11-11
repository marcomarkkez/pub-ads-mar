<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 2026-11-11: el login del SPA decía NETWORK_ERROR con la API sana (`curl localhost:8000`
 * daba 422). En un Codespace el navegador llega a la API por `<nombre>-8000.app.github.dev`,
 * y si ese puerto queda Private GitHub contesta con su redirección de inicio de sesión.
 * Se previene en mvp-init.py (lo pone Public) y, si aun así pasa, el mensaje lo nombra.
 */
class CodespaceApiPortTest extends TestCase
{
    public function test_mvp_init_sets_the_api_port_public_in_a_codespace(): void
    {
        $init = file_get_contents(base_path('../mvp-init.py'));

        $this->assertStringContainsString('"ports", "visibility", "8000:public"', $init);
        $this->assertMatchesRegularExpression('/\n    ensure_api_port_public\(\)\n/', $init,
            'main() must call it, or the prevention exists only on paper.');
    }

    public function test_the_network_error_names_the_private_port_in_a_codespace(): void
    {
        $src = file_get_contents(base_path('../frontend/src/app/core/models/api-error.ts'));

        $this->assertStringContainsString('networkErrorMessage(', $src);
        $this->assertStringContainsString('Port Visibility → Public', $src);
    }
}
