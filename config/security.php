<?php

return [

    /*
    | Content-Security-Policy. "report_only" makes the browser only REPORT
    | violations in the console instead of blocking them: the safe way to
    | try a stricter policy on a live site first.
    */
    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),
    ],

    /*
    | Admin pages need a second factor (confirmed 2FA app or a passkey).
    | An admin password alone is one phishing email away from a ban spree
    | or redirected payouts. Can be switched off for local experiments.
    */
    'admin_requires_second_factor' => (bool) env('ADMIN_REQUIRE_2FA', true),

    /*
    | Write requests (POST/PUT/PATCH/DELETE) per minute per user (or IP for
    | guests), on top of the stricter per-route limits in ThrottleWrites.
    */
    'writes_per_minute' => (int) env('WRITES_PER_MINUTE', 60),

];
