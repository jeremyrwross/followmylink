<?php

test('auth get screens are not exposed', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/register',
    '/forgot-password',
    '/reset-password/token',
    '/confirm-password',
    '/two-factor-challenge',
]);

test('login has no public get screen', function () {
    $this->get('/login')->assertMethodNotAllowed();
});
