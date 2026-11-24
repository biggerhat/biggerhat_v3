<?php

namespace App\Http\Requests\Games\Logged;

/** Editing a logged game takes the same details as entering it; who may edit it is checked by the controller's policy. */
class UpdateLoggedGameRequest extends StoreLoggedGameRequest {}
