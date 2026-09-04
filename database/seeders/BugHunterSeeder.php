<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Seeder;

/**
 * Seed content for Bug Hunter.
 *
 * Every defect here was reproduced by RUNNING the snippet — PHP 8.5.9 and Node
 * v24.18.0 — and the explanations record what actually happened, not what the
 * author assumed would happen (§70).
 *
 * Line numbers in `solution.lines` are 1-based over the snippet exactly as the
 * player sees it. They are the thing most likely to drift when a snippet is
 * edited, which is why the validator checks them and a test runs that validator
 * over this content.
 *
 * Idempotent by slug. Changing a snippet or a line number must bump `version`.
 */
class BugHunterSeeder extends Seeder
{
    public function run(): void
    {
        $experience = Experience::query()->where('slug', 'bug-hunter')->first();

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
                'slug' => 'php-average-off-by-one',
                'title' => 'The average that reads one too many',
                'description' => 'A helper that has worked in three projects and is wrong in all of them.',
                'objective' => 'Find the line containing the defect.',
                'difficulty' => 'easy',
                'type' => 'locate',
                'points' => 100,
                'estimated_minutes' => 4,
                'tags' => ['php', 'off-by-one', 'arrays'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'php',
                    'mode' => 'locate',
                    'context' => 'Returns the mean of a non-empty list of numbers.',
                    'snippet' => implode("\n", [
                        'function averageOf(array $numbers): float',
                        '{',
                        '    $total = 0;',
                        '',
                        '    for ($i = 0; $i <= count($numbers); $i++) {',
                        '        $total += $numbers[$i];',
                        '    }',
                        '',
                        '    return $total / count($numbers);',
                        '}',
                    ]),
                ],
                'solution' => ['lines' => [5], 'summary' => '<= should be <'],
                'explanation' => 'Line 5: the loop condition is `$i <= count($numbers)`, so it runs '
                    ."one iteration too many.\n\n"
                    .'With [1, 2, 3] the last iteration reads $numbers[3], which does not exist. PHP 8 '
                    .'emits "Warning: Undefined array key 3", evaluates the missing value as null, and '
                    .'adds 0 — so averageOf([1, 2, 3]) still returns 2, which is the right answer.

'
                    .'That luck is the danger. The total is unchanged because null adds zero, so the '
                    .'result is often still correct and the warning is the only evidence. On a system '
                    .'that logs warnings to a file nobody reads, this survives for years, and it '
                    ."breaks loudly the day the array is a list of objects instead of ints.\n\n"
                    .'A `foreach` avoids the class of bug entirely; so does array_sum($numbers) / count($numbers).',
            ],
            [
                'slug' => 'php-pagination-offset',
                'title' => 'Page one is missing',
                'description' => 'Users report that the newest ten records never appear. The query is fine.',
                'objective' => 'Find the line containing the defect.',
                'difficulty' => 'easy',
                'type' => 'locate',
                'points' => 100,
                'estimated_minutes' => 4,
                'tags' => ['php', 'pagination', 'logic'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'php',
                    'mode' => 'locate',
                    'context' => 'Returns one page of results. Pages are numbered from 1.',
                    'snippet' => implode("\n", [
                        'public function page(int $page, int $perPage = 10): array',
                        '{',
                        '    $page = max(1, $page);',
                        '',
                        '    $offset = $page * $perPage;',
                        '',
                        '    return $this->query',
                        '        ->orderByDesc(\'created_at\')',
                        '        ->offset($offset)',
                        '        ->limit($perPage)',
                        '        ->get();',
                        '}',
                    ]),
                ],
                'solution' => ['lines' => [5], 'summary' => 'offset should be ($page - 1) * $perPage'],
                'explanation' => "Line 5: the offset is `\$page * \$perPage`, so page 1 starts at row 10.\n\n"
                    .'Pages are numbered from 1 but offsets start at 0, so the conversion is '
                    .'($page - 1) * $perPage. As written, page 1 skips the first ten rows entirely — '
                    ."and because the list is ordered newest first, the ten skipped rows are the newest.\n\n"
                    .'Everything else about the code is correct, which is what makes it survive review. '
                    .'The clamp on line 3 even looks like the author thought carefully about edge '
                    ."cases.\n\n"
                    .'This is worth recognising by sight because it is invisible in testing: any page '
                    .'past the first returns plausible data, and only the very newest records go '
                    .'missing.',
            ],
            [
                'slug' => 'php-array-splice-mutates',
                'title' => 'The function that eats its argument',
                'description' => 'The caller\'s array is empty afterwards, and nobody can see why.',
                'objective' => 'Find the line containing the defect.',
                'difficulty' => 'medium',
                'type' => 'locate',
                'points' => 100,
                'estimated_minutes' => 5,
                'tags' => ['php', 'arrays', 'mutation'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'php',
                    'mode' => 'locate',
                    'context' => 'Returns the first N items. The caller keeps using its own array afterwards.',
                    'snippet' => implode("\n", [
                        'class Batcher',
                        '{',
                        '    public function firstBatch(array $items, int $size): array',
                        '    {',
                        '        if ($items === []) {',
                        '            return [];',
                        '        }',
                        '',
                        '        $batch = array_splice($items, 0, $size);',
                        '',
                        '        return $batch;',
                        '    }',
                        '}',
                    ]),
                ],
                'solution' => ['lines' => [9], 'summary' => 'array_splice mutates; array_slice does not'],
                'explanation' => "Line 9: `array_splice` REMOVES the elements it returns.\n\n"
                    .'Running it on ["a", "b", "c", "d"] with size 2 returns ["a", "b"] and leaves the '
                    .'array as ["c", "d"]. `array_slice` returns the same two and leaves the input alone.'.'

'
                    .'In PHP the argument is passed by value, so the CALLER\'s array is safe here — '
                    .'which is exactly why this is a medium rather than an easy. The bug bites when '
                    .'$items is a property, is passed by reference, or when the same method is later '
                    ."refactored to operate on \$this->items.\n\n"
                    .'The general shape is worth recognising: a function whose name promises a read '
                    .'("first", "get", "peek") performing a write. The names are one letter apart and '
                    .'the difference is destructive.',
            ],
            [
                'slug' => 'js-async-foreach',
                'title' => 'The total that is always zero',
                'description' => 'It awaits. It has await in it. It returns before any of it happens.',
                'objective' => 'Find the line containing the defect.',
                'difficulty' => 'medium',
                'type' => 'locate',
                'points' => 100,
                'estimated_minutes' => 6,
                'tags' => ['javascript', 'async', 'promises'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'javascript',
                    'mode' => 'locate',
                    'context' => 'Sums the price of every order, fetching each one.',
                    'snippet' => implode("\n", [
                        'async function totalFor(orderIds) {',
                        '    let total = 0;',
                        '',
                        '    orderIds.forEach(async (id) => {',
                        '        const order = await fetchOrder(id);',
                        '        total += order.price;',
                        '    });',
                        '',
                        '    return total;',
                        '}',
                    ]),
                ],
                'solution' => ['lines' => [4], 'summary' => 'forEach does not await its callback'],
                'explanation' => "Line 4: `forEach` ignores the promise its callback returns.\n\n"
                    .'Passing an async function to forEach starts every callback and immediately moves '
                    .'on. forEach has no idea it was handed promises — its return value is undefined '
                    .'and it never awaits anything. So `return total` on line 9 runs before any fetch '
                    ."has resolved, and the function returns 0.\n\n"
                    .'Reproduced: with three ids that each resolve after a tick, the result is 0, not '
                    ."their sum.\n\n"
                    .'The fix depends on intent. Sequential: `for (const id of orderIds) { ... await ... }`. '
                    ."Parallel, which is usually what was wanted here:\n\n"
                    ."  const orders = await Promise.all(orderIds.map(fetchOrder));\n"
                    ."  return orders.reduce((sum, o) => sum + o.price, 0);\n\n"
                    .'The `await` on line 5 is the misdirection: it is real, it works, and it makes the '
                    .'function look asynchronous in the way the author intended.',
            ],
            [
                'slug' => 'js-var-closure-loop',
                'title' => 'Three handlers, one number',
                'description' => 'Every button reports the same index. There are three buttons.',
                'objective' => 'Find the line containing the defect.',
                'difficulty' => 'medium',
                'type' => 'locate',
                'points' => 100,
                'estimated_minutes' => 5,
                'tags' => ['javascript', 'closures', 'scope'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'javascript',
                    'mode' => 'locate',
                    'context' => 'Registers one handler per item, each reporting its own index.',
                    'snippet' => implode("\n", [
                        'function attachHandlers(items) {',
                        '    const handlers = [];',
                        '',
                        '    for (var i = 0; i < items.length; i++) {',
                        '        handlers.push(() => {',
                        '            report(items[i], i);',
                        '        });',
                        '    }',
                        '',
                        '    return handlers;',
                        '}',
                    ]),
                ],
                'solution' => ['lines' => [4], 'summary' => 'var is function-scoped; use let'],
                'explanation' => 'Line 4: `var i` is function-scoped, so all three closures capture the '
                    ."same binding.\n\n"
                    .'By the time any handler runs the loop has finished and i is items.length. '
                    .'Reproduced: three closures built this way return [3, 3, 3] rather than [0, 1, 2], '
                    ."and items[3] is undefined.\n\n"
                    .'`let` is block-scoped and, specifically for `for` loops, the spec creates a fresh '
                    .'binding per iteration — which is why changing one keyword fixes it and the same '
                    ."code with let returns [0, 1, 2].\n\n"
                    .'The reason this belongs in a debugging exercise rather than a syntax quiz is that '
                    .'the failure is silent and delayed: nothing throws at registration, and the '
                    .'symptom shows up somewhere else entirely, in whatever `report` does with an '
                    .'undefined item.',
            ],
            [
                'slug' => 'php-balance-race',
                'title' => 'Two withdrawals, one balance',
                'description' => 'Correct under test, correct in review, wrong twice a month in production.',
                'objective' => 'Find the line containing the defect.',
                'difficulty' => 'hard',
                'type' => 'locate',
                'points' => 100,
                'estimated_minutes' => 8,
                'tags' => ['php', 'concurrency', 'database', 'race-condition'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'php',
                    'mode' => 'locate',
                    'context' => 'Withdraws an amount, refusing to overdraw. Called from a web request.',
                    'snippet' => implode("\n", [
                        'public function withdraw(int $accountId, int $amount): bool',
                        '{',
                        '    return DB::transaction(function () use ($accountId, $amount) {',
                        '        $account = Account::query()->find($accountId);',
                        '',
                        '        if ($account->balance < $amount) {',
                        '            return false;',
                        '        }',
                        '',
                        '        $account->balance -= $amount;',
                        '        $account->save();',
                        '',
                        '        return true;',
                        '    });',
                        '}',
                    ]),
                ],
                'solution' => ['lines' => [4], 'summary' => 'read without a row lock: find() should be lockForUpdate()'],
                'explanation' => 'Line 4: the row is read without a lock, so two concurrent requests both '
                    ."see the old balance.\n\n"
                    .'A transaction is not a lock. It gives atomicity — both writes land or neither '
                    .'does — but at the default isolation level it does not stop another transaction '
                    ."reading the same row at the same time.\n\n"
                    ."So with a balance of 100 and two simultaneous withdrawals of 100:\n\n"
                    ."  A reads 100     B reads 100\n"
                    ."  A: 100 >= 100   B: 100 >= 100\n"
                    ."  A writes 0      B writes 0\n\n"
                    ."Both succeed. 200 left the account and the balance says 0.\n\n"
                    .'The fix is to make the read take a lock the other transaction must wait for: '
                    .'`Account::query()->whereKey($accountId)->lockForUpdate()->first()`, which issues '
                    ."SELECT ... FOR UPDATE.\n\n"
                    .'This is hard because it is invisible everywhere a bug is normally caught. It is '
                    .'correct in review, correct under any single-threaded test, and only fails under '
                    .'genuine concurrency — which is also why the database, not the application, has '
                    .'to be the thing enforcing it.',
            ],
            [
                'slug' => 'distributed-lock-released-by-a-stranger',
                'title' => 'The lock that let three workers in',
                'description' => 'A payout runs twice a month, always under load, never in staging.',
                'objective' => 'Find the line containing the defect.',
                'difficulty' => 'expert',
                'type' => 'locate',
                'points' => 500,
                'estimated_minutes' => 12,
                'tags' => ['distributed-systems', 'locking', 'redis', 'concurrency'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'php',
                    'mode' => 'locate',
                    'context' => 'Take a lock so only one worker pays out a given payout, then release it.',
                    'snippet' => implode('
', [
                        'public function processPayout(int $payoutId): void',
                        '{',
                        '    $acquired = $this->redis->set(',
                        '        "payout:{$payoutId}", $this->workerId, ["NX", "EX" => 30]',
                        '    );',
                        '',
                        '    if (! $acquired) {',
                        '        return;',
                        '    }',
                        '',
                        '    try {',
                        '        $payout = $this->payouts->find($payoutId);',
                        '        $this->bank->transfer($payout->account, $payout->amount);',
                        '        $this->payouts->markSent($payoutId);',
                        '    } finally {',
                        '        $this->redis->del("payout:{$payoutId}");',
                        '    }',
                        '}',
                    ]),
                    'prompt' => 'Which line lets a second worker be paid out?',
                ],
                'solution' => [
                    'lines' => [16],
                    'summary' => 'The release does not check that the lock is still ours.',
                ],
                'explanation' => 'The defect is the unconditional delete in the finally block.

'
                    .'Acquisition is correct: SET NX EX is atomic, and the worker id is written as '
                    .'the value precisely so ownership can be checked later. Nothing checks it.

'
                    .'The failure needs the 30-second expiry to pass while the transfer is still '
                    .'running — a slow bank call, a long GC pause, or the worker being descheduled. '
                    .'At that moment the lock expires on its own and worker B legitimately acquires '
                    .'it. Worker A then finishes, reaches its finally, and deletes a key it no longer '
                    .'owns. The lock is now free while worker B still believes it holds it, so '
                    .'worker C acquires it too — and the payout goes out twice.

'
                    .'Nothing in the code is wrong in isolation, which is why this survives review '
                    .'and never reproduces in staging: it needs real latency and real concurrency at '
                    .'the same time.

'
                    .'The release must be conditional on ownership, and it must be ATOMIC — a GET '
                    .'followed by a DEL has the same race one layer down. In Redis that is a small '
                    .'Lua script comparing the value before deleting. The deeper fix is a fencing '
                    .'token: the lock hands out a monotonically increasing number, and the resource '
                    .'being protected rejects any write carrying a token older than the last one it '
                    .'accepted. That is the only version that stays correct when a process pauses '
                    .'for longer than the lease.',
            ],
        ];
    }
}
