<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Seeder;

/**
 * Seed content for Code Arena.
 *
 * Every answer key here was produced by RUNNING the reference solution over the
 * case inputs, not by reasoning about what it would return. A key written by
 * hand is a key that is wrong roughly as often as the author is tired, and a
 * wrong key in this experience is invisible: the challenge simply looks harder
 * than it is, because every correct submission fails one case.
 *
 * Cases are chosen so that the hidden ones are the interesting ones. A sample
 * exists to pin down the contract — what shape comes back, what the edges mean —
 * and the hidden cases are where the problem actually lives. Inputs are public
 * for both; only the expected outputs of hidden cases are withheld, and those
 * live in `solution`, which no client and no sandbox ever receives (ADR 0008).
 *
 * Idempotent by slug. Changing a case list or a key must bump `version`.
 */
class CodeArenaSeeder extends Seeder
{
    public function run(): void
    {
        $experience = Experience::query()->where('slug', 'code-arena')->first();

        if ($experience === null) {
            return;
        }

        foreach ($this->challenges() as $challenge) {
            Challenge::query()->updateOrCreate(
                ['slug' => $challenge['slug']],
                [...$challenge, 'experience_id' => $experience->id],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function challenges(): array
    {
        return [
            [
                'slug' => 'normalise-a-slug',
                'title' => 'The URL-safe title',
                'description' => 'Turn any title into something that can live in a URL.',
                'objective' => 'Implement the function so every case passes.',
                'difficulty' => 'easy',
                'type' => 'implement',
                'points' => 100,
                'estimated_minutes' => 10,
                'tags' => ['php', 'strings', 'regex'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'runtime' => 'php-8.4',
                    'entry' => 'normalise_slug',
                    'signature' => 'function normalise_slug(string $title): string',
                    'brief' => 'Lowercase the title, replace every run of characters that is not '
                        ."a-z or 0-9 with a single hyphen, and trim hyphens from both ends.\n\n"
                        .'ASCII only: anything else counts as a separator, accented letters '
                        .'included. That is a simplification, and a deliberate one — real '
                        .'transliteration needs a library, and pretending otherwise is how a slug '
                        .'function quietly mangles half its input.',
                    'starter' => implode("\n", [
                        '<?php',
                        '',
                        'function normalise_slug(string $title): string',
                        '{',
                        '    // Your code here.',
                        '    return $title;',
                        '}',
                    ]),
                    'cases' => [
                        ['args' => ['Hello World'], 'sample' => true, 'expected' => 'hello-world', 'label' => 'The ordinary case'],
                        ['args' => ['  PHP 8.4 & You!  '], 'sample' => true, 'expected' => 'php-8-4-you', 'label' => 'Punctuation and padding'],
                        ['args' => ['---'], 'sample' => false, 'label' => 'Nothing but separators'],
                        ['args' => [''], 'sample' => false, 'label' => 'Empty'],
                        ['args' => ['Already-a-slug'], 'sample' => false, 'label' => 'Already done'],
                        ['args' => ['Ünicode is not handled'], 'sample' => false, 'label' => 'Outside ASCII'],
                    ],
                ],
                'solution' => [
                    'expected' => [
                        'hello-world',
                        'php-8-4-you',
                        '',
                        '',
                        'already-a-slug',
                        'nicode-is-not-handled',
                    ],
                    'reference' => implode("\n", [
                        '<?php',
                        '',
                        'function normalise_slug(string $title): string',
                        '{',
                        '    $hyphenated = preg_replace(\'/[^a-z0-9]+/\', \'-\', strtolower($title));',
                        '',
                        '    return trim((string) $hyphenated, \'-\');',
                        '}',
                    ]),
                ],
                'explanation' => "The whole problem is the order of operations.\n\n"
                    .'Lowercase FIRST, then substitute. Doing it the other way means the character '
                    .'class has to cover A-Z as well, and the first person to edit the regex will '
                    ."forget one half of it.\n\n"
                    .'Replace RUNS, not single characters. `[^a-z0-9]` without the `+` turns '
                    ."'8.4 &' into a string of hyphens, and then you are writing a second pass to "
                    ."collapse them.\n\n"
                    .'Trim LAST. A title with padding produces leading and trailing hyphens, and a '
                    ."slug that starts with one is a URL that looks broken even though it works.\n\n"
                    .'The accented-letter case is the one worth arguing about. Dropping the Ü '
                    .'silently is defensible only because the brief says so — in production this '
                    .'is exactly where a slug function needs a transliteration table, and where '
                    .'quietly deleting characters produces two different titles with the same slug.',
            ],

            [
                'slug' => 'parse-a-semver',
                'title' => 'Three numbers and a lot of things that are not',
                'description' => 'Read a version string, or decide it is not one.',
                'objective' => 'Implement the function so every case passes.',
                'difficulty' => 'easy',
                'type' => 'implement',
                'points' => 100,
                'estimated_minutes' => 10,
                'tags' => ['php', 'parsing', 'validation'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'runtime' => 'php-8.4',
                    'entry' => 'parse_semver',
                    'signature' => 'function parse_semver(string $version): array',
                    'brief' => "Return ['major' => int, 'minor' => int, 'patch' => int] for a "
                        ."version that is exactly three dot-separated runs of digits.\n\n"
                        .'Anything else returns an empty array. Exactly three: not two, not four, '
                        ."and no leading 'v'. Pre-release and build metadata are out of scope.",
                    'starter' => implode("\n", [
                        '<?php',
                        '',
                        'function parse_semver(string $version): array',
                        '{',
                        '    // Your code here.',
                        '    return [];',
                        '}',
                    ]),
                    'cases' => [
                        ['args' => ['1.2.3'], 'sample' => true, 'expected' => ['major' => 1, 'minor' => 2, 'patch' => 3], 'label' => 'The ordinary case'],
                        ['args' => ['1.2'], 'sample' => true, 'expected' => [], 'label' => 'Too few parts'],
                        ['args' => ['10.20.30'], 'sample' => false, 'label' => 'More than one digit'],
                        ['args' => ['1.2.3.4'], 'sample' => false, 'label' => 'Too many parts'],
                        ['args' => ['v1.2.3'], 'sample' => false, 'label' => 'A tag, not a version'],
                        ['args' => ['0.0.0'], 'sample' => false, 'label' => 'All zeroes'],
                    ],
                ],
                'solution' => [
                    'expected' => [
                        ['major' => 1, 'minor' => 2, 'patch' => 3],
                        [],
                        ['major' => 10, 'minor' => 20, 'patch' => 30],
                        [],
                        [],
                        ['major' => 0, 'minor' => 0, 'patch' => 0],
                    ],
                    'reference' => implode("\n", [
                        '<?php',
                        '',
                        'function parse_semver(string $version): array',
                        '{',
                        '    if (preg_match(\'/^(\d+)\.(\d+)\.(\d+)$/\', $version, $m) !== 1) {',
                        '        return [];',
                        '    }',
                        '',
                        '    return [\'major\' => (int) $m[1], \'minor\' => (int) $m[2], \'patch\' => (int) $m[3]];',
                        '}',
                    ]),
                ],
                'explanation' => 'Two cases catch nearly everyone, and they catch them for the '
                    ."same reason: an unanchored pattern.\n\n"
                    .'`v1.2.3` and `1.2.3.4` both CONTAIN a valid version. Without `^` and `$` the '
                    .'match succeeds on both, and the function confidently returns 1.2.3 for a '
                    .'string that is not a version at all. Anchors are not decoration here; they '
                    ."are the entire specification.\n\n"
                    .'The all-zeroes case is the other trap, and it is a shape you will meet again. '
                    .'`0.0.0` is perfectly valid, so any implementation that decides success by '
                    .'asking whether the numbers are truthy reports failure on it. In PHP, `if '
                    .'($major)` is false for zero — which is why `preg_match` is compared against '
                    .'`1` rather than used as a boolean, and why the parse result is signalled by '
                    ."an empty array rather than by the values themselves.\n\n"
                    .'Returning [] for failure rather than null or false is a real choice, and the '
                    .'reason is the declared return type: one shape out, always, so the caller '
                    .'never has to ask which kind of nothing it got.',
            ],

            [
                'slug' => 'merge-overlapping-intervals',
                'title' => 'Two bookings, one room',
                'description' => 'Collapse a list of ranges into the fewest that cover the same ground.',
                'objective' => 'Implement the function so every case passes.',
                'difficulty' => 'medium',
                'type' => 'implement',
                'points' => 150,
                'estimated_minutes' => 15,
                'tags' => ['php', 'algorithms', 'sorting'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'runtime' => 'php-8.4',
                    'entry' => 'merge_intervals',
                    'signature' => 'function merge_intervals(array $intervals): array',
                    'brief' => 'Each interval is [start, end] with start <= end. Return the '
                        ."merged set, sorted by start, as a list.\n\n"
                        .'Intervals that TOUCH merge: [1, 4] and [4, 5] become [1, 5]. The input '
                        .'is not sorted.',
                    'starter' => implode("\n", [
                        '<?php',
                        '',
                        'function merge_intervals(array $intervals): array',
                        '{',
                        '    // Your code here.',
                        '    return $intervals;',
                        '}',
                    ]),
                    'cases' => [
                        ['args' => [[[1, 3], [2, 6], [8, 10]]], 'sample' => true, 'expected' => [[1, 6], [8, 10]], 'label' => 'One overlap, one gap'],
                        ['args' => [[]], 'sample' => true, 'expected' => [], 'label' => 'Nothing to merge'],
                        ['args' => [[[5, 6], [1, 2]]], 'sample' => false, 'label' => 'Out of order'],
                        ['args' => [[[1, 4], [4, 5]]], 'sample' => false, 'label' => 'Touching, not overlapping'],
                        ['args' => [[[1, 10], [2, 3]]], 'sample' => false, 'label' => 'Fully contained'],
                        ['args' => [[[1, 2], [3, 4], [5, 6]]], 'sample' => false, 'label' => 'No overlaps at all'],
                    ],
                ],
                'solution' => [
                    'expected' => [
                        [[1, 6], [8, 10]],
                        [],
                        [[1, 2], [5, 6]],
                        [[1, 5]],
                        [[1, 10]],
                        [[1, 2], [3, 4], [5, 6]],
                    ],
                    'reference' => implode("\n", [
                        '<?php',
                        '',
                        'function merge_intervals(array $intervals): array',
                        '{',
                        '    if ($intervals === []) {',
                        '        return [];',
                        '    }',
                        '',
                        '    usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);',
                        '',
                        '    $merged = [array_shift($intervals)];',
                        '',
                        '    foreach ($intervals as $interval) {',
                        '        $last = count($merged) - 1;',
                        '',
                        '        if ($interval[0] <= $merged[$last][1]) {',
                        '            $merged[$last][1] = max($merged[$last][1], $interval[1]);',
                        '        } else {',
                        '            $merged[] = $interval;',
                        '        }',
                        '    }',
                        '',
                        '    return $merged;',
                        '}',
                    ]),
                ],
                'explanation' => 'Sorting by start is what makes this a single pass. Once the list '
                    .'is ordered, the only interval a new one can overlap is the last one kept — '
                    ."everything before it ends earlier and is already settled.\n\n"
                    ."Two cases separate a working solution from one that looks like it works:\n\n"
                    .'[1, 4] and [4, 5] TOUCH. The comparison must be `<=`, not `<`. With `<` you '
                    .'get two intervals that share an endpoint, which for a room booking means '
                    ."double-booking the instant they meet.\n\n"
                    .'[1, 10] and [2, 3] is full containment, and it is where `max` earns its '
                    ."keep. Assigning the new interval's end unconditionally shortens the merged "
                    .'range from 10 to 3 and silently loses seven units of it. The bug survives '
                    .'every test whose intervals happen to arrive in increasing order of end — '
                    .'which is most hand-written tests, because that is how people write examples.',
            ],

            [
                'slug' => 'capped-exponential-backoff',
                'title' => 'Back off, but not forever',
                'description' => 'Produce the delays between retries, and stop doubling somewhere sensible.',
                'objective' => 'Implement the function so every case passes.',
                'difficulty' => 'medium',
                'type' => 'implement',
                'points' => 150,
                'estimated_minutes' => 12,
                'tags' => ['php', 'retries', 'reliability'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'runtime' => 'php-8.4',
                    'entry' => 'retry_delays',
                    'signature' => 'function retry_delays(int $attempts, int $base, int $cap): array',
                    'brief' => 'Return the delay before each retry, in milliseconds, as a list of '
                        ."exactly \$attempts entries.\n\n"
                        .'The first retry waits $base. Each one after that waits twice the '
                        ."previous, and no delay ever exceeds \$cap.\n\n"
                        .'No jitter: the point here is the sequence, and randomness would make the '
                        .'answer unverifiable.',
                    'starter' => implode("\n", [
                        '<?php',
                        '',
                        'function retry_delays(int $attempts, int $base, int $cap): array',
                        '{',
                        '    // Your code here.',
                        '    return [];',
                        '}',
                    ]),
                    'cases' => [
                        ['args' => [5, 100, 1000], 'sample' => true, 'expected' => [100, 200, 400, 800, 1000], 'label' => 'Doubling into the cap'],
                        ['args' => [1, 50, 999], 'sample' => true, 'expected' => [50], 'label' => 'A single retry'],
                        ['args' => [0, 100, 1000], 'sample' => false, 'label' => 'No retries at all'],
                        ['args' => [3, 1000, 500], 'sample' => false, 'label' => 'A cap below the base'],
                        ['args' => [4, 25, 100], 'sample' => false, 'label' => 'Reaching the cap exactly'],
                    ],
                ],
                'solution' => [
                    'expected' => [
                        [100, 200, 400, 800, 1000],
                        [50],
                        [],
                        [500, 500, 500],
                        [25, 50, 100, 100],
                    ],
                    'reference' => implode("\n", [
                        '<?php',
                        '',
                        'function retry_delays(int $attempts, int $base, int $cap): array',
                        '{',
                        '    $delays = [];',
                        '',
                        '    for ($attempt = 1; $attempt <= $attempts; $attempt++) {',
                        '        $delays[] = min($cap, $base * (2 ** ($attempt - 1)));',
                        '    }',
                        '',
                        '    return $delays;',
                        '}',
                    ]),
                ],
                'explanation' => 'The first delay is $base, not $base * 2. That is the '
                    .'off-by-one, and it hides well: every delay in the sequence is still '
                    .'plausible, just one step too far along, and the only visible symptom is that '
                    ."the client is slightly more impatient than the configuration claims.\n\n"
                    .'A cap BELOW the base is the case worth thinking about. `min` applied at every '
                    .'step handles it without a special case and returns [500, 500, 500]; an '
                    .'implementation that only starts capping once doubling has begun returns '
                    ."[1000, 500, 500] and violates its own configuration on the first retry.\n\n"
                    .'Zero attempts must return an empty list, not one delay. A loop written as '
                    .'do-while returns a delay for a retry that never happens, and the caller '
                    ."duly waits before not retrying.\n\n"
                    .'Worth saying what this deliberately omits: real backoff needs jitter. Without '
                    .'it, every client that failed at the same moment retries at the same moment, '
                    .'and the thundering herd that took the service down takes it down again on a '
                    .'schedule you designed.',
            ],

            [
                'slug' => 'install-in-dependency-order',
                'title' => 'Install order, or a cycle',
                'description' => 'Work out what has to be installed first, and notice when that is impossible.',
                'objective' => 'Implement the function so every case passes.',
                'difficulty' => 'hard',
                'type' => 'implement',
                'points' => 200,
                'estimated_minutes' => 20,
                'tags' => ['php', 'graphs', 'algorithms', 'dependencies'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'runtime' => 'php-8.4',
                    'entry' => 'dependency_order',
                    'signature' => 'function dependency_order(array $graph): array',
                    'brief' => 'The graph maps each package to the list of packages it depends '
                        .'on. Return an install order in which every package appears after all of '
                        ."its dependencies.\n\n"
                        .'Where several packages are ready at once, take them in alphabetical '
                        .'order — the answer has to be a single list, so the tie-break is part of '
                        ."the specification.\n\n"
                        .'Return an empty list if no such order exists.',
                    'starter' => implode("\n", [
                        '<?php',
                        '',
                        'function dependency_order(array $graph): array',
                        '{',
                        '    // Your code here.',
                        '    return [];',
                        '}',
                    ]),
                    'cases' => [
                        ['args' => [['app' => ['lib'], 'lib' => []]], 'sample' => true, 'expected' => ['lib', 'app'], 'label' => 'One dependency'],
                        ['args' => [['a' => [], 'c' => [], 'b' => []]], 'sample' => true, 'expected' => ['a', 'b', 'c'], 'label' => 'All independent, alphabetical'],
                        ['args' => [['x' => ['y'], 'y' => ['x']]], 'sample' => false, 'label' => 'A cycle'],
                        ['args' => [['app' => ['db', 'cache'], 'cache' => ['config'], 'db' => ['config'], 'config' => []]], 'sample' => false, 'label' => 'A diamond'],
                        ['args' => [[]], 'sample' => false, 'label' => 'Nothing to install'],
                    ],
                ],
                'solution' => [
                    'expected' => [
                        ['lib', 'app'],
                        ['a', 'b', 'c'],
                        [],
                        ['config', 'cache', 'db', 'app'],
                        [],
                    ],
                    'reference' => implode("\n", [
                        '<?php',
                        '',
                        'function dependency_order(array $graph): array',
                        '{',
                        '    $pending = [];',
                        '',
                        '    foreach ($graph as $package => $dependencies) {',
                        '        $pending[$package] = array_values(array_unique($dependencies));',
                        '    }',
                        '',
                        '    $order = [];',
                        '',
                        '    while ($pending !== []) {',
                        '        $ready = [];',
                        '',
                        '        foreach ($pending as $package => $dependencies) {',
                        '            if (array_diff($dependencies, $order) === []) {',
                        '                $ready[] = $package;',
                        '            }',
                        '        }',
                        '',
                        '        if ($ready === []) {',
                        '            return [];',
                        '        }',
                        '',
                        '        sort($ready);',
                        '',
                        '        foreach ($ready as $package) {',
                        '            $order[] = $package;',
                        '            unset($pending[$package]);',
                        '        }',
                        '    }',
                        '',
                        '    return $order;',
                        '}',
                    ]),
                ],
                'explanation' => 'This is a topological sort, and the two things that make it a '
                    ."real problem are the tie-break and the cycle.\n\n"
                    .'The tie-break exists because a dependency graph does not have one correct '
                    .'order — it has a set of valid ones. Any grader comparing against a single '
                    .'list has to say which. Sorting the ready set alphabetically is arbitrary, '
                    .'and being arbitrary is fine as long as it is STATED: an unstated tie-break '
                    .'is a challenge that fails correct answers for reasons the player cannot '
                    ."see.\n\n"
                    .'The cycle is the part that separates a working implementation from a hanging '
                    .'one. Detection is not an extra pass: if nothing is ready and packages '
                    .'remain, every one of them is waiting on something that is also waiting, '
                    .'which is the definition of a cycle. Without that check the loop simply never '
                    .'terminates, and in this experience that is not a crash — it is a case that '
                    ."times out while the rest of the run carries on.\n\n"
                    .'The diamond checks that a package with two paths to the same dependency is '
                    .'installed once, not twice. `config` is required by both `cache` and `db`, '
                    .'and an implementation that appends on every discovery rather than on '
                    .'resolution emits it twice.',
            ],

            [
                'slug' => 'sliding-window-rate-limit',
                'title' => 'The limit that does not reset',
                'description' => 'Decide which requests get through, without the burst a fixed window allows.',
                'objective' => 'Implement the function so every case passes.',
                'difficulty' => 'expert',
                'type' => 'implement',
                'points' => 500,
                'estimated_minutes' => 25,
                'tags' => ['php', 'rate-limiting', 'algorithms', 'reliability'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'runtime' => 'php-8.4',
                    'entry' => 'sliding_window_allow',
                    'signature' => 'function sliding_window_allow(array $timestamps, int $window, int $limit): array',
                    'brief' => 'Requests arrive at the given timestamps, in order. Return a list '
                        .'of booleans: true if that request is allowed, false if it is '
                        ."rejected.\n\n"
                        .'A request at time t is allowed when fewer than $limit ALLOWED requests '
                        .'fall in the window (t - $window, t]. The window is half-open: a request '
                        ."exactly \$window ago has just left it.\n\n"
                        .'Rejected requests do not count towards the limit — being turned away '
                        .'must not make the next rejection more likely.',
                    'starter' => implode("\n", [
                        '<?php',
                        '',
                        'function sliding_window_allow(array $timestamps, int $window, int $limit): array',
                        '{',
                        '    // Your code here.',
                        '    return [];',
                        '}',
                    ]),
                    'cases' => [
                        ['args' => [[0, 1, 2, 3], 10, 2], 'sample' => true, 'expected' => [true, true, false, false], 'label' => 'A burst against a limit of two'],
                        ['args' => [[0, 10, 20], 10, 1], 'sample' => true, 'expected' => [true, true, true], 'label' => 'Spaced exactly a window apart'],
                        ['args' => [[0, 9, 10, 11], 10, 1], 'sample' => false, 'label' => 'On and around the boundary'],
                        ['args' => [[], 5, 1], 'sample' => false, 'label' => 'No requests'],
                        ['args' => [[5, 5, 5], 10, 2], 'sample' => false, 'label' => 'Simultaneous'],
                    ],
                ],
                'solution' => [
                    'expected' => [
                        [true, true, false, false],
                        [true, true, true],
                        [true, false, true, false],
                        [],
                        [true, true, false],
                    ],
                    'reference' => implode("\n", [
                        '<?php',
                        '',
                        'function sliding_window_allow(array $timestamps, int $window, int $limit): array',
                        '{',
                        '    $allowed = [];',
                        '    $verdicts = [];',
                        '',
                        '    foreach ($timestamps as $timestamp) {',
                        '        $live = array_filter($allowed, fn (int $t): bool => $t > $timestamp - $window);',
                        '',
                        '        $ok = count($live) < $limit;',
                        '',
                        '        if ($ok) {',
                        '            $allowed[] = $timestamp;',
                        '        }',
                        '',
                        '        $verdicts[] = $ok;',
                        '    }',
                        '',
                        '    return $verdicts;',
                        '}',
                    ]),
                ],
                'explanation' => "Three decisions, and each one is a different production incident.\n\n"
                    .'**The window slides.** A fixed window — reset a counter every $window '
                    .'seconds — allows twice the limit across a boundary: fill the quota at the '
                    .'end of one window and again at the start of the next. DevLab has a Bug '
                    .'Hunter challenge about exactly that burst. Here you are writing the version '
                    .'that does not have it, and the difference is that the window is measured '
                    ."from the CURRENT request rather than from a clock everybody shares.\n\n"
                    .'**The window is half-open.** `(t - window, t]` means a request exactly '
                    .'$window ago has left. Case [0, 9, 10, 11] is the whole test: at t=10 the '
                    .'request at t=0 is exactly a window old and no longer counts, so it is '
                    .'allowed — while t=9 was refused a moment earlier. Writing `>=` instead of '
                    .'`>` changes that one verdict and nothing else, which is why boundaries need '
                    ."a case rather than an argument.\n\n"
                    .'**Rejections do not count.** Only allowed requests are recorded. The '
                    .'alternative is a limiter that punishes a client for having been throttled: '
                    .'a caller retrying hard would keep its own window permanently full and never '
                    .'recover, which is the failure mode where a rate limiter turns a spike into '
                    ."an outage.\n\n"
                    .'The simultaneous case is the reminder that timestamps are not unique. Three '
                    .'requests at t=5 with a limit of two means two pass and one does not — the '
                    .'comparison is on count, not on distinctness, and a set would lose two of '
                    .'them.',
            ],
        ];
    }
}
