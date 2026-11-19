<?php

return [
    /*
     * A lesson counts as finished once this much of its video has been watched.
     * Owner decision pending; 90 is a working default.
     */
    'complete_at_percent' => (int) env('LMS_COMPLETE_AT_PERCENT', 90),

    /*
     * How long a passwordless login link stays valid, in minutes.
     * Owner decision pending; 30 is a working default.
     */
    'login_link_minutes' => (int) env('LMS_LOGIN_LINK_MINUTES', 30),

    /*
     * The link in the "your class is open" mail lives longer, because buyers
     * often open that mail a day later. Working default; owner decision pending.
     */
    'purchase_link_minutes' => (int) env('LMS_PURCHASE_LINK_MINUTES', 72 * 60),

    // How long a browser stays signed in to the learning area.
    'session_days' => (int) env('LMS_SESSION_DAYS', 30),

    // How long after payment the buying browser may open the class without the mail.
    'claim_hours' => (int) env('LMS_CLAIM_HOURS', 24),

    // The landing site that hosts /belajar/; links in mails point here.
    'frontend_url' => rtrim((string) env('LMS_FRONTEND_URL', 'https://academy.pitcar.co.id'), '/'),
];
