<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * WALK-8 (Marco, 2026-11-10): el alta de la landing respondia
 * `Unexpected token '<', "<!DOCTYPE "... is not valid JSON` con cualquier dato.
 *
 * Dos causas, y cada una tiene su cerrojo aqui:
 *  1. La landing mandaba el alta a `/api` RELATIVO: al servidor de la propia landing (4300),
 *     que no tiene API y contesta HTML. La API vive en el 8000.
 *  2. CORS_ALLOWED_ORIGINS SUSTITUIA la lista de origenes, y el .env.example solo nombraba el
 *     4200: aun apuntando al 8000, el navegador habria bloqueado el alta en local.
 */
class LandingSignupReachesApiTest extends TestCase
{
    public function test_the_landing_posts_to_the_api_port_not_to_itself(): void
    {
        $service = file_get_contents(base_path('../landing/src/app/shared/signup.service.ts'));

        $this->assertDoesNotMatchRegularExpression("#api\s*=\s*'/api'#", $service,
            "landing/ has no API behind its own port: a relative '/api' reaches the dev server and gets HTML back.");
        $this->assertStringContainsString('-8000.', $service, 'Codespaces: the landing must swap -4300 for -8000.');
        $this->assertStringContainsString(':8000/api', $service, 'Local: the landing must aim at port 8000.');
    }

    public function test_the_api_accepts_the_landing_origin_even_when_env_lists_only_the_spa(): void
    {
        putenv('CORS_ALLOWED_ORIGINS=http://localhost:4200');
        $this->refreshApplication();
        putenv('CORS_ALLOWED_ORIGINS');

        $this->call('OPTIONS', '/api/register', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:4300',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4300');
    }

    public function test_the_landing_never_paints_a_raw_parser_message(): void
    {
        $form = file_get_contents(base_path('../landing/src/app/shared/signup-form.component.ts'));

        $this->assertStringNotContainsString('e.error?.message', $form,
            'When the response is not JSON, error.message is the browser parser talking ("Unexpected token <").');
    }
}
