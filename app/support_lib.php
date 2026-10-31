<?php
declare(strict_types=1);

function support_defaults(): array
{
    return [
        'help_email' => 'libertyfoundation4news@gmail.com', 'help_phone' => '+91 6289702130  ||  +91 8276015376', 'help_hours' => 'Mon-Sun · 10:30 am - 5:30 pm', 'hr_email' => 'Barrackpore , Kolkata,  700122, WB , IN',
        'dir_quote' => 'Great work is built one honest day at a time. Show up, own your craft, and lift the people around you.',
        'dir_name' => 'Director', 'dir_org' => 'Liberty Foundation', 'dir_photo' => '',
    ];
}
/** Support-page content: saved values over the defaults. */
function support_settings(): array
{
    $out = support_defaults();
    foreach (q_all('SELECT k, v FROM site_settings') as $r) if (array_key_exists($r['k'], $out)) $out[$r['k']] = (string)$r['v'];
    return $out;
}
function support_set(string $k, string $v): void
{
    q('INSERT INTO site_settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [$k, $v]);
}
function support_default_faqs(): array
{
    return [
        ['How is attendance calculated?', 'Hours are the time between your punch-in and punch-out minus recorded breaks.'],
        ['I forgot to punch out.', 'Open the Attendance Calendar, pick the day and choose “Request Regularization”.'],
        ['How do leave requests work?', 'Submit from Leave Application; your reporting manager or an administrator approves it. Leave other than your scheduled weekly off must be requested at least 3 days in advance; emergencies are considered individually. See the HR policy under Documents.'],
    ];
}
