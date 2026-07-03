<?php

test('profile settings pages are not exposed', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/settings',
    '/settings/profile',
    '/settings/appearance',
]);
