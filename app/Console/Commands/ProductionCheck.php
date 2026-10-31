<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Property;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Read-only pre-launch checklist. Run on the server after deploying:
 *
 *   php artisan meezan:production-check
 *
 * FAIL items block a safe launch (exit code 1); WARN items work but need a
 * decision or a production-grade provider. Nothing is changed and no secret
 * value is printed.
 */
class ProductionCheck extends Command
{
    protected $signature = 'meezan:production-check';

    protected $description = 'Check configuration, server and data readiness for production (read-only)';

    /** @var list<array{0: string, 1: string, 2: string}> */
    protected array $rows = [];

    public function handle(): int
    {
        $this->application();
        $this->server();
        $this->data();
        $this->services();

        $this->table(['Status', 'Check', 'Detail'], array_map(fn ($r) => [
            match ($r[0]) {
                'PASS' => '<fg=green>PASS</>', 'WARN' => '<fg=yellow>WARN</>', default => '<fg=red>FAIL</>'
            }, $r[1], $r[2],
        ], $this->rows));

        $counts = array_count_values(array_column($this->rows, 0));
        $this->line(sprintf('%d passed, %d warnings, %d failed.', $counts['PASS'] ?? 0, $counts['WARN'] ?? 0, $counts['FAIL'] ?? 0));

        return ($counts['FAIL'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function application(): void
    {
        $url = (string) config('app.url');

        $this->check(app()->isProduction(), 'APP_ENV is production', 'currently "'.app()->environment().'"');
        $this->check(! config('app.debug'), 'APP_DEBUG is off', 'debug pages would show code and settings to visitors');
        $this->check(filled(config('app.key')), 'APP_KEY is set', 'run php artisan key:generate once, on first install only');
        $this->check(str_starts_with($url, 'https://') && ! preg_match('/localhost|127\.0\.0\.1|\.test\b/', $url), 'APP_URL is the live https address', 'currently '.$url);
        $this->check((bool) config('session.secure'), 'Session cookie is HTTPS-only', 'set SESSION_SECURE_COOKIE=true', warnOnly: true);
        $this->check(app()->configurationIsCached(), 'Configuration is cached', 'php artisan config:cache (after every .env change)', warnOnly: true);
        $this->check(app()->routesAreCached(), 'Routes are cached', 'php artisan route:cache', warnOnly: true);
        $this->check((bool) config('meezan.security.csp'), 'Content-Security-Policy is on', 'MEEZAN_CSP_ENABLED', warnOnly: true);
    }

    protected function server(): void
    {
        $missing = array_values(array_filter(['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'exif', 'gd', 'curl', 'tokenizer', 'xml', 'ctype'], fn ($ext) => ! extension_loaded($ext)));
        $this->check($missing === [], 'Required PHP extensions', $missing ? 'missing: '.implode(', ', $missing) : 'PHP '.PHP_VERSION);
        $this->check(function_exists('imagewebp'), 'GD can write WebP (listing photos)', 'install GD with WebP support');

        foreach ([storage_path(), base_path('bootstrap/cache')] as $dir) {
            $this->check(is_writable($dir), 'Writable: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $dir), 'web server user needs write access');
        }

        // file_exists() also sees Windows junctions, which is_link()/is_dir() report as false.
        $this->check(file_exists(public_path('storage')), 'Public storage link exists', 'php artisan storage:link');
        $this->check(File::exists(public_path('build/manifest.json')), 'Front-end build present', 'run npm run build and upload public/build');
    }

    protected function data(): void
    {
        try {
            DB::select('select 1');
            $connected = true;
        } catch (\Throwable $e) {
            $connected = false;
        }
        $this->check($connected, 'Database connection', 'check DB_* settings');
        if (! $connected) {
            return;
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $pending = collect(File::glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'))->diff($ran);
        $this->check($pending->isEmpty(), 'Migrations are up to date', $pending->count().' pending: php artisan migrate --force');

        $this->check(User::where('role', Role::Admin)->where('status', 'active')->exists(), 'An active admin account exists', 'create one before launch');
        $demo = Property::withTrashed()->where('is_demo', true)->count();
        $this->check($demo === 0, 'No demo listings', $demo.' demo listing(s) present', warnOnly: true);
        $testAccounts = User::where('email', 'like', '%.test')->count();
        $this->check($testAccounts === 0, 'No .test demo accounts', $testAccounts.' account(s) use .test addresses', warnOnly: true);

        $failed = DB::table('failed_jobs')->count();
        $this->check($failed === 0, 'No failed queued jobs', $failed.' failed: php artisan queue:failed', warnOnly: true);
    }

    protected function services(): void
    {
        $mailer = (string) config('mail.default');
        $this->check(! in_array($mailer, ['log', 'array'], true), 'Outgoing mail is configured', 'MAIL_MAILER is "'.$mailer.'"');
        $this->check(filled(config('mail.from.address')) && ! str_ends_with((string) config('mail.from.address'), '@example.com'), 'Mail "from" address is real', 'set MAIL_FROM_ADDRESS', warnOnly: true);

        $queue = (string) config('queue.default');
        $this->check($queue !== 'sync', 'Emails are queued', 'QUEUE_CONNECTION=database recommended', warnOnly: true);
        $this->check($queue === 'sync' || config('meezan.queue_via_scheduler'), 'Queue is drained by the scheduler', 'MEEZAN_QUEUE_VIA_SCHEDULER=true on shared hosting, or keep php artisan queue:work running', warnOnly: true);

        $this->check((bool) config('meezan.require_verified_email'), 'Email confirmation is enforced', 'MEEZAN_REQUIRE_EMAIL_VERIFICATION=true once mail works', warnOnly: true);
        $this->check(filled(config('meezan.contact.email')), 'Public contact email is set', 'MEEZAN_CONTACT_EMAIL', warnOnly: true);
        $this->check(! str_contains((string) config('meezan.map.tile_url'), 'tile.openstreetmap.org'), 'Map tiles use a production provider', 'public OpenStreetMap tiles are for light use (MEEZAN_MAP_TILE_URL)', warnOnly: true);
        $this->check(! str_contains((string) config('meezan.nearby.endpoint'), 'overpass-api.de'), 'Nearby places use a production Overpass server', 'MEEZAN_OVERPASS_URL', warnOnly: true);
    }

    protected function check(bool $ok, string $label, string $detail, bool $warnOnly = false): void
    {
        $this->rows[] = [$ok ? 'PASS' : ($warnOnly ? 'WARN' : 'FAIL'), $label, $ok ? '' : $detail];
    }
}
