<?php

return [
    'groups' => [
        // Sent to guests and users without any permission. The ~330 admin.*
        // routes are only ever used by admin/author UI, so leaving them out
        // trims a large chunk of the payload that ships on every page load.
        'public' => ['!admin.*'],
    ],
];
