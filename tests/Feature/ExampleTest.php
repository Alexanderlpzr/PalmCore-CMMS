<?php

test('the root url redirects to the admin panel', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/admin');
});
