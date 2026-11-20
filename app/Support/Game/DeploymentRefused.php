<?php

namespace App\Support\Game;

use RuntimeException;

/** A deployment action that the rules of the sequence do not allow right now (wrong phase, wrong models). The message is for the player. */
final class DeploymentRefused extends RuntimeException {}
