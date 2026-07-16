<?php

use Well35\EnumObjects\EnumObjects;

it('has generated TS enum objects in sync with the PHP enums', function () {
    EnumObjects::assertInSync();
});
