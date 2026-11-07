<?php

test('the API health endpoint returns an up status', function () {
    $this->getJson('/api/up')
        ->assertOk()
        ->assertJson(['status' => 'up']);
});
