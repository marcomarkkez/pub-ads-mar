<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * BR-10 (`ui-endpoints-planeacion-juntos`) — the rule that had no teeth.
 *
 * "Asegúrate que el UI siempre vaya de la mano con los endpoints y que vayan ambos con
 * la planeación" (owner). Until this file existed, that was a working convention and
 * nothing else: `enforcedBy` said "convención de trabajo, no código", which is the
 * honest way of writing "nobody checks". The drift it was supposed to prevent kept
 * happening anyway — 16 routes served with no entry in design.json, 24 entries
 * describing routes nobody serves, and a delete-users button that had been answering
 * 404 on every click since the ruling that removed its route.
 *
 * Three of the error-hunt patterns die here rather than being chased one at a time:
 *  · EH-2  campos fantasma en el frontend      — the UI calling something that is not there.
 *  · EH-7  vocabulario que el codigo no usa    — the plan describing a route that is not there.
 *  · EH-10 control muerto                      — a button pointing at a retired route.
 *
 * Nothing here touches the database, so it is fast and it fails for exactly one reason:
 * the three artefacts stopped agreeing.
 */
class PlanningCodeCongruenceTest extends TestCase
{
    /**
     * Routes that exist for the framework's sake and describe no product capability.
     * Deliberately tiny and deliberately explicit: this list is the one place where a
     * route can hide from the plan, so anything added here has to be defensible out loud.
     */
    private const INFRASTRUCTURE = [
        'GET /user',            // Sanctum's stock example route
        'GET /sanctum/csrf-cookie',
    ];

    private function design(): array
    {
        $path = base_path('../design/design.json');
        $this->assertFileExists($path, 'design.json is the canonical plan; the test is meaningless without it.');

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** `/client/campaigns/{campaign}` and `/client/campaigns/{c}` are the same route. */
    private function shape(string $path): string
    {
        return '/' . ltrim(preg_replace('/\{[^}]*\}/', '{}', $path), '/');
    }

    /** @return array<string,string> "GET /shape" => the real path, for readable failures */
    private function servedRoutes(): array
    {
        $served = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            $path = '/' . substr($uri, strlen('api/'));

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $key = $method . ' ' . $this->shape($path);
                if (! in_array($key, self::INFRASTRUCTURE, true)) {
                    $served[$key] = $method . ' ' . $path;
                }
            }
        }

        return $served;
    }

    /** @return array{0:array<string,array>,1:array<string,array>} [built, planned] keyed by "METHOD /shape" */
    private function documented(): array
    {
        $built = $planned = [];

        foreach ($this->design()['endpoints'] as $entry) {
            $key = strtoupper(trim($entry['method'])) . ' ' . $this->shape($entry['path']);
            if (str_starts_with($entry['group'], 'Planned')) {
                $planned[$key] = $entry;
            } else {
                $built[$key] = $entry;
            }
        }

        return [$built, $planned];
    }

    /**
     * EH-7's direction: the API grows a capability and the plan never hears about it.
     * An undocumented route is worse than an undocumented function — it is a promise the
     * platform is already keeping to somebody, that nobody wrote down.
     */
    public function test_every_served_route_is_documented(): void
    {
        [$built, $planned] = $this->documented();
        $undocumented = [];

        foreach ($this->servedRoutes() as $key => $readable) {
            if (isset($built[$key])) {
                continue;
            }
            $undocumented[] = isset($planned[$key])
                ? $readable . '  (documented as "' . $planned[$key]['group'] . '" — it is already served, promote it out of Planned)'
                : $readable . '  (no entry at all in design.json .endpoints[])';
        }

        $this->assertSame([], $undocumented, "Routes served with no matching entry in design.json .endpoints[]:\n  "
            . implode("\n  ", $undocumented) . "\n");
    }

    /**
     * The opposite direction, and the one that produced the dead delete-users button:
     * the plan keeps describing a route long after a ruling retired it, so the UI is
     * built against a promise the backend stopped keeping.
     */
    public function test_every_documented_route_is_served(): void
    {
        [$built] = $this->documented();
        $served = $this->servedRoutes();

        $missing = array_values(array_map(
            fn ($k) => $k . '  (' . $built[$k]['desc'] . ')',
            array_filter(array_keys($built), fn ($k) => ! isset($served[$k]))
        ));

        $this->assertSame([], $missing, "design.json documents routes nothing serves. Either build them, "
            . "move them to a 'Planned - …' group, or delete them if a ruling retired them:\n  "
            . implode("\n  ", $missing) . "\n");
    }

    /**
     * `Planned - …` is the only way an entry may describe something that does not exist,
     * so it needs two locks or it becomes the drawer where drift hides: it must name the
     * todo that owes it, and it must stop being planned the moment it starts being served.
     */
    public function test_planned_endpoints_name_a_real_todo_and_are_not_served_yet(): void
    {
        [, $planned] = $this->documented();
        $served = $this->servedRoutes();
        $todoIds = array_column($this->design()['todos'], 'id');

        $this->assertNotEmpty($planned, 'The Planned groups vanished entirely — that is suspicious, not clean.');

        foreach ($planned as $key => $entry) {
            $this->assertArrayHasKey('todo', $entry, "Planned endpoint {$key} names no todo that owes it.");
            $this->assertContains($entry['todo'], $todoIds,
                "Planned endpoint {$key} points at todo {$entry['todo']}, which is not in design.json .todos[].");
            $this->assertArrayNotHasKey($key, $served,
                "Planned endpoint {$key} is already being served. Move it out of its 'Planned - …' group.");
        }
    }

    /**
     * The walkthroughs decide when a spec is finished (BR-16), so their references have to
     * resolve to something real. A walk pointing at `BR-99` or at a todo nobody wrote is
     * worse than a walk with no references at all: it looks like coverage and is not. This
     * is EH-9's shape — the board and the body disagreeing — caught before a human wastes
     * an afternoon walking a checklist that closes nothing.
     */
    public function test_every_walkthrough_reference_resolves(): void
    {
        $design = $this->design();

        $known = [
            'closes' => array_merge(array_column($design['todos'], 'id'),
                array_map(fn ($s) => '§' . $s['id'], $design['specs'])),
            'specs' => array_map(fn ($s) => '§' . $s['id'], $design['specs']),
            'ucs' => array_column($design['userStories'], 'id'),
            'br' => array_column($design['businessRules']['items'], 'id'),
            'eh' => array_column($design['errorHunt']['items'], 'id'),
        ];

        $walkStatuses = array_column($design['legend']['walkStatuses'], 'key');
        $dangling = [];

        foreach ($design['walkthroughs']['items'] as $walk) {
            $this->assertContains($walk['status'], $walkStatuses,
                "{$walk['id']} carries status '{$walk['status']}', which is not in legend.walkStatuses — "
                . 'the dashboard would paint it as unknown (EH-9).');

            if (! empty($walk['todoId'])) {
                $this->assertContains($walk['todoId'], array_column($design['todos'], 'id'),
                    "{$walk['id']}.todoId points at a todo that is not on the board.");
            }

            foreach ($known as $field => $valid) {
                foreach ($walk[$field] ?? [] as $ref) {
                    if (! in_array($ref, $valid, true)) {
                        $dangling[] = "{$walk['id']}.{$field} → {$ref}";
                    }
                }
            }
        }

        $this->assertSame([], $dangling, "Walkthroughs reference ids that do not exist:\n  "
            . implode("\n  ", $dangling) . "\n");
    }

    /**
     * BR-16 (`sin-bucles-de-codigo-seco`) given teeth.
     *
     * The rule says a spec is not finished by reading code — it is finished when its
     * walkthrough passes. That was a convention with nothing behind it, and conventions
     * with nothing behind them are how F11 came to be `done` on the strength of a test
     * suite while the walkthrough that was supposed to close it had never been run once.
     *
     * So: a todo may not sit at `done` while a walkthrough that claims to close it is
     * still `pending` or `failed`. This does not judge whether the code works — the suite
     * does that. It judges whether we are allowed to say so yet.
     */
    public function test_no_todo_is_done_while_the_walk_that_closes_it_is_not(): void
    {
        $design = $this->design();
        $todoStatus = array_column($design['todos'], 'status', 'id');
        $premature = [];

        foreach ($design['walkthroughs']['items'] as $walk) {
            if ($walk['status'] === 'passed') {
                continue;
            }
            foreach ($walk['closes'] ?? [] as $ref) {
                if (($todoStatus[$ref] ?? null) === 'done') {
                    $premature[] = "{$ref} is `done` but {$walk['id']} ({$walk['status']}) closes it";
                }
            }
        }

        $this->assertSame([], $premature, "A todo was closed before the walkthrough that closes it ran "
            . "(BR-16 — a spec is not finished by reading code):\n  " . implode("\n  ", $premature) . "\n");
    }

    /**
     * EH-10 at the root. Every URL the Angular app builds has to resolve to a route the
     * backend serves — the check that would have caught the delete-users button the day
     * its route was removed, instead of leaving it to fail silently in a user's hands.
     *
     * A `${...}` segment is a value computed at runtime, so it matches ANY segment: the
     * app writes `/chats/${chatId}/${action}` where the action is 'close' or 'join'.
     * Being permissive there is the point — this test hunts for paths with no possible
     * match, not for paths whose parameters it cannot guess.
     */
    public function test_every_url_the_frontend_calls_resolves_to_a_route(): void
    {
        $frontend = base_path('../frontend/src');
        if (! is_dir($frontend)) {
            $this->markTestSkipped('No frontend checked out next to the backend.');
        }

        $servedPaths = array_map(
            fn ($k) => explode(' ', $k, 2)[1],
            array_keys($this->servedRoutes())
        );

        $calls = $this->frontendCalls($frontend);

        // Without this, the day someone changes how the app builds URLs the extractor
        // quietly returns nothing and this test goes green on an empty set — passing
        // because it checked nothing. That is the exact shape of lie the file exists to
        // stop, so it must not be the shape of the file itself.
        $this->assertGreaterThan(40, count($calls),
            'Only ' . count($calls) . ' API calls found in the frontend. The extractor has stopped '
            . 'recognising how the app builds URLs, so this test is no longer checking anything.');

        $orphans = [];

        foreach ($calls as $call => $where) {
            $match = false;
            foreach ($servedPaths as $route) {
                if ($this->segmentsMatch($call, $route)) {
                    $match = true;
                    break;
                }
            }
            if (! $match) {
                $orphans[] = $call . '  ← ' . $where;
            }
        }

        $this->assertSame([], $orphans, "The UI calls URLs the API does not serve (EH-10 — a control that "
            . "only exists to answer 404):\n  " . implode("\n  ", $orphans) . "\n");
    }

    /** @return array<string,string> "/normalised/path" => "file:line" of the first sighting */
    private function frontendCalls(string $dir): array
    {
        $calls = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'ts') {
                continue;
            }
            $lines = file($file->getPathname());

            foreach ($lines as $n => $line) {
                // `${this.api}/client/campaigns/${id}` — the base is an interpolation whose
                // name contains "api", and the path is whatever follows until the literal ends.
                if (! preg_match_all('/\$\{[^}]*api[^}]*\}([^`\'"]*)/i', $line, $m)) {
                    continue;
                }
                foreach ($m[1] as $raw) {
                    $path = preg_replace('/\$\{[^}]*\}/', '{}', explode('?', $raw)[0]);
                    $path = rtrim($path, '/');
                    if ($path === '' || ! str_starts_with($path, '/')) {
                        continue;
                    }
                    $calls[$path] ??= basename(dirname($file->getPathname())) . '/'
                        . $file->getFilename() . ':' . ($n + 1);
                }
            }
        }

        return $calls;
    }

    /** Same segment count, and every segment either equal or a runtime value on either side. */
    private function segmentsMatch(string $call, string $route): bool
    {
        $a = explode('/', trim($call, '/'));
        $b = explode('/', trim($route, '/'));

        if (count($a) !== count($b)) {
            return false;
        }

        foreach ($a as $i => $seg) {
            if ($seg !== $b[$i] && $seg !== '{}' && $b[$i] !== '{}') {
                return false;
            }
        }

        return true;
    }
}
