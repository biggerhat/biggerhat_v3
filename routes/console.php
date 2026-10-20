<?php

Schedule::command('telescope:prune')->daily();
// Keep the activity_log table bounded (package default retention: 365 days).
Schedule::command('activitylog:clean')->daily();
