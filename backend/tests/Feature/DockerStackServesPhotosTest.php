<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 2026-11-11 (WALK-2): en la pila docker toda foto subida daba 404. El backend guarda las
 * subidas en el volumen `backend_storage`, y nginx — quien sirve /storage/... en el 8000 —
 * solo montaba ./backend, donde esa carpeta esta vacia. frontend/e2e-space-photo.mjs lo
 * comprueba de punta a punta; esto sujeta la linea que lo arregla.
 */
class DockerStackServesPhotosTest extends TestCase
{
    public function test_nginx_mounts_the_volume_the_backend_writes_photos_to(): void
    {
        $compose = file_get_contents(base_path('../docker-compose.yml'));

        $this->assertMatchesRegularExpression('/^  backend:[\s\S]*?- backend_storage:\/var\/www\/storage\n/m', $compose);
        $this->assertMatchesRegularExpression('/^  nginx:[\s\S]*?- backend_storage:\/var\/www\/storage(:ro)?\n[\s\S]*?^  frontend:/m', $compose,
            'nginx serves /storage/... and must see the same volume the backend writes to.');
    }
}
