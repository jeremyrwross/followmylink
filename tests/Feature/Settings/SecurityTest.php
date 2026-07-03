<?php

test('security settings page is not exposed', function () {
    $this->get('/settings/security')->assertNotFound();
});
