<?php

it('serves the EVT landing page as a display-only screen', function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    $this->withoutMiddleware();
    $this->withoutVite();

    $page = $this->get('/evt');

    $page->assertOk();
    $page->assertInertia(fn ($inertia) => $inertia->component('evt/index'));

    $this->post('/evt')->assertStatus(405);
});
