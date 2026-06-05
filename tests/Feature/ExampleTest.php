<?php

test('guests are redirected to login from the Filament panel', function () {
    $this->get('/')->assertRedirect('/login');
});
