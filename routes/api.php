<?php

use Illuminate\Support\Facades\Route;

// Phase 5 owns the public read API under /api/v1/*.
// Unversioned /api/* will return 410 Gone with an RFC 7807 pointer at the
// versioned URL once the V1\* controllers land.
