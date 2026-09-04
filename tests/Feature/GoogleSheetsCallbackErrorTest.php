<?php

namespace Tests\Feature;

use Tests\TestCase;

class GoogleSheetsCallbackErrorTest extends TestCase
{
    public function test_callback_exception_redirects_with_generic_message(): void
    {
        $response = $this->get('/api/integrations/google/callback?code=any-code&state=not-a-valid-state');

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('google=error', $location);
        $this->assertStringContainsString(
            'message='.urlencode('Gagal menghubungkan Google. Silakan coba lagi.'),
            $location
        );
        $this->assertStringNotContainsString('Invalid+OAuth+state', $location);
        $this->assertStringNotContainsString('OAuth', $location);
    }
}
