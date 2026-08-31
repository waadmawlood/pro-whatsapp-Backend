<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_docs_ui_is_available(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('swagger-ui', false);
    }

    public function test_openapi_spec_is_available(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertHeader('content-type', 'application/yaml; charset=UTF-8')
            ->assertSee('openapi: 3.0.3', false)
            ->assertSee('/auth/login', false);
    }

    public function test_root_points_to_docs(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJsonPath('docs', url('/docs'))
            ->assertJsonPath('openapi', url('/docs/openapi.yaml'));
    }
}
