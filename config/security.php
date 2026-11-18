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

];
