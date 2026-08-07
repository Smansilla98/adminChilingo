<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Smoke test: la aplicacion arranca y la ruta raiz responde sin error de servidor.
     * Puede devolver 200 (contenido publico) o 302 (redireccion a login).
     */
    public function test_la_ruta_raiz_responde_sin_error_de_servidor(): void
    {
        $response = $this->get('/');

        $this->assertContains(
            $response->getStatusCode(),
            [200, 302],
            'La ruta raiz debe responder 200 o redirigir, no fallar.'
        );
    }
}
