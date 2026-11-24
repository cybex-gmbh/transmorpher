<?php

// Delete all Sanctum tokens which expired more than 24 hours ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
