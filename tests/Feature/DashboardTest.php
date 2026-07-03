<?php

test('dashboard is not exposed', function () {
    $this->get('/dashboard')->assertNotFound();
});
