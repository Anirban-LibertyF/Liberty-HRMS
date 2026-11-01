<?php
declare(strict_types=1);

/**
 * Job responsibilities for the Appointment Letter (clause 5).
 * An employee's own list (users.job_duties, one point per line, set by the admin) wins; otherwise a default list is picked
 * from the designation (then the department) by keyword; anything unrecognised gets a generic list.
 */

/** [role key, regex on designation / department, role-specific duties]. First match wins, so more specific roles come first. */
function job_duty_templates(): array
{
    return [
        ['digital', '/digital\s*market|seo|social\s*media|performance\s*market/i', [
            'planning, running and optimising digital campaigns (search, social, email and content) to meet the lead, traffic and conversion targets set by the Company;',
            'creating, scheduling and publishing content on the Company\'s website and social media channels in line with brand guidelines;',
            'tracking campaign spend, analytics and results, and preparing regular performance reports with recommendations;']],
        ['marketing', '/market/i', [
            'planning and executing marketing activities, promotions and events that build the Company\'s brand and generate enquiries;',
            'preparing brochures, creatives, presentations and other marketing material that follow Company brand guidelines;',
            'researching the market, competitors and target audience, and reporting the results of every activity to management;']],
        ['telecalling', '/tele\s*-?\s*call|call\s*cent|telesales|tele\s*marketing/i', [
            'making outbound calls and handling inbound calls to prospects and students/customers using the approved script and call process;',
            'recording every call, lead status and follow-up accurately in the Company\'s CRM or call sheet on the same day;',
            'meeting the daily call, connect and conversion targets while remaining polite, truthful and professional on every call;']],
        ['sales', '/sales|business\s*development|counsell?or|relationship\s*manager/i', [
            'generating and following up leads, presenting the Company\'s courses and services, and closing admissions / sales to meet the targets set by the Company;',
            'maintaining accurate lead, follow-up and conversion records in the CRM, and sharing a regular sales pipeline report with management;',
            'quoting only approved prices, offers and terms, and never making promises on behalf of the Company that it has not authorised;']],
        ['finance', '/financ|account|book\s*keep|payroll|audit/i', [
            'maintaining accurate books of account, vouchers, bills and records, and posting entries in the accounting system correctly and on time;',
            'preparing invoices, receipts, payment runs, reconciliations, payroll inputs and the periodic financial and statutory reports required by the Company;',
            'ensuring that expenses and payments are made only against proper approval and supporting documents, and supporting audits and tax / GST / TDS compliance;']],
        ['hr', '/\bhr\b|human\s*resource|talent|recruit/i', [
            'managing the employee life-cycle - recruitment, onboarding, documentation, attendance, leave, payroll coordination, confirmation and exit formalities;',
            'drafting and issuing offer letters, appointment letters and HR notices, and keeping all personnel records accurate, confidential and up to date;',
            'communicating and applying HR policies fairly, handling employee queries and grievances, and supporting performance reviews and engagement activities;']],
        ['frontoffice', '/front\s*office|reception|help\s*desk/i', [
            'receiving visitors, students and callers courteously, handling enquiries and directing them to the right person or department;',
            'attending the front-desk phone, email and walk-in enquiries, and maintaining the visitor register, appointment diary and enquiry records;',
            'coordinating with all departments for messages, couriers, stationery and office needs, and keeping the reception area presentable;']],
        ['dataentry', '/data\s*entry|\bdeo\b|back\s*office|mis\b/i', [
            'entering, updating and verifying data in the Company\'s software, sheets and records quickly and with a high degree of accuracy;',
            'checking source documents against entries, correcting errors, and keeping all data complete, organised and backed up as instructed;',
            'preparing routine reports and sheets from the data, and treating all data as confidential Company information;']],
        ['admin', '/admin|office\s*(manager|assistant|executive|coordinator)|operations?|executive\s*assistant|coordinator/i', [
            'managing the day-to-day administration of the office - records, files, supplies, vendors, assets, travel and facilities;',
            'coordinating with staff and departments on schedules, meetings, documentation and follow-ups so that work runs smoothly;',
            'maintaining registers, inventories and administrative reports accurately, and supporting management with any office or compliance requirement;']],
        ['dev', '/develop|software|programmer|engineer|\bqa\b|tester|devops|full\s*stack|front\s*end|back\s*end|\bai\b|machine\s*learning|\bit\b/i', [
            'designing, developing, testing, debugging and deploying mobile / app, web and software applications to the quality, timelines and standards set by the Company;',
            'taking part in project planning - understanding requirements, estimating effort, breaking work into tasks and reporting progress against the plan;',
            'testing your own work and supporting QA (unit, functional and regression testing), and fixing defects found in testing or in production;',
            'implementing AI / automation features and integrations (such as APIs, models and tools) where the project requires them, with due care for data security;',
            'following the Company\'s coding standards, version-control, code-review, security and documentation practices;']],
        ['uiux', '/ui\s*\/?\s*ux|\bux\b|\bui\b|designer|graphic|product\s*design|web\s*design/i', [
            'researching user needs and designing user flows, wireframes, prototypes and high-fidelity UI / UX for the Company\'s websites, apps and software;',
            'building and maintaining design systems, style guides and reusable components, and keeping every product consistent with the Company\'s brand;',
            'working with developers and product owners to hand over design specifications and assets, reviewing the built result, and improving usability from feedback and testing;']],
        ['teacher', '/teach|faculty|trainer|instructor|tutor|lecturer|professor/i', [
            'preparing and delivering classes / training sessions according to the approved curriculum, schedule and teaching standards of the Company;',
            'assessing students\' progress, conducting tests and assignments, giving feedback, and keeping attendance and academic records accurate;',
            'supporting students\' doubts and placement / career readiness, and taking part in meetings, events and curriculum improvement as required;']],
    ];
}

/** Job roles an admin can tick (several allowed) on the employee form: key => label. */
function job_role_catalog(): array
{
    return ['dev' => 'Software Developer / Software Engineer', 'uiux' => 'UI/UX Designer', 'digital' => 'Digital Marketer', 'marketing' => 'Marketing',
            'telecalling' => 'Telecalling', 'sales' => 'Sales', 'finance' => 'Finance / Accounts', 'hr' => 'HR Manager', 'frontoffice' => 'Front Office',
            'dataentry' => 'Data Entry', 'admin' => 'Office Admin', 'teacher' => 'Teacher / Faculty'];
}

/** Ticked role keys of an employee row (users.job_roles is a comma-separated list), only valid ones, in catalog order. */
function job_roles_of(array $emp): array
{
    $have = array_filter(array_map('trim', explode(',', (string)($emp['job_roles'] ?? ''))));
    return array_values(array_filter(array_keys(job_role_catalog()), fn($k) => in_array($k, $have, true)));
}

/** Role-specific duty lines only (no common tail, no punctuation tidy-up): the admin's own list, else the ticked roles, else a keyword match. */
function job_role_duties(array $emp): array
{
    $custom = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($emp['job_duties'] ?? ''))), fn($l) => $l !== ''));
    if ($custom) return $custom;
    $tpl = [];
    foreach (job_duty_templates() as [$key, $re, $lines]) $tpl[$key] = [$re, $lines];
    $picked = job_roles_of($emp);
    if ($picked) return array_merge(...array_map(fn($k) => $tpl[$k][1], $picked));
    foreach ([(string)($emp['designation'] ?? ''), (string)($emp['department'] ?? '')] as $hay) {
        foreach ($tpl as [$re, $lines]) if ($hay !== '' && preg_match($re, $hay)) return $lines;
    }
    return ['carrying out the duties of your role in the ' . ($emp['department'] ?: 'assigned') . ' function to the quality, timelines and standards set by the Company;',
            'following the Company\'s processes, quality standards, security practices and documentation requirements;'];
}

/** Lines every role shares at the end of the list. */
function job_duty_common(): array
{
    return [
        'keeping your reporting manager informed of progress and risks, and maintaining a complete and accurate Daily Work Log;',
        'collaborating with colleagues and clients professionally, and sharing knowledge within the team;',
        'safeguarding the Company\'s assets, systems, data and reputation, and using them only for authorised business purposes; and',
        'complying with all Company policies, applicable laws and the lawful instructions of the Company.',
    ];
}

/** Duties for the Appointment Letter clause 5. The admin's own list is used as written; otherwise role duties + the common tail. */
function job_duties_for(array $emp): array
{
    if (trim((string)($emp['job_duties'] ?? '')) !== '') {
        $custom = job_role_duties($emp); $last = count($custom) - 1;
        foreach ($custom as $i => $l) $custom[$i] = preg_replace('/(?:[;,]\s*and|[;.,:])\s*$/i', '', rtrim($l)) . ($i === $last ? '.' : ($i === $last - 1 ? '; and' : ';'));
        return $custom;
    }
    return [...job_role_duties($emp), ...job_duty_common()];
}
/** Designations offered as suggestions in the employee form (free text is still allowed). Each one maps to a duties list above. */
function designation_suggestions(): array
{
    return ['App Developer', 'Web Developer', 'Full Stack Developer', 'Software Engineer', 'Senior Software Engineer', 'UI Engineer', 'QA Engineer',
            'AI Engineer', 'UI/UX Designer', 'Graphic Designer', 'Digital Marketer', 'Marketing Executive', 'Sales Executive', 'Telecaller',
            'Accounts Executive', 'Finance Manager', 'HR Manager', 'HR Executive', 'Front Office Executive', 'Office Admin', 'Data Entry Operator',
            'Teacher', 'Faculty'];
}

/** A duty line without its trailing ; . , or "; and" - for editable boxes and letter points. */
function duty_clean(string $l): string
{
    return trim(preg_replace('/(?:[;,]\s*and|[;.,:])\s*$/i', '', rtrim($l)));
}
