<?php

test('password confirmation screen is not exposed', function () {
    $this->get('/confirm-password')->assertNotFound();
});
