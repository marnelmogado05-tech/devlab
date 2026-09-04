<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Seeder;

/**
 * Seed content for Cursed Code.
 *
 * EVERY answer here was verified by running the snippet — PHP 8.5.9 and Node
 * v24.18.0 — not from memory. A remembered output is not evidence, and a wrong
 * answer key is silent: it corrupts every score derived from it and the
 * difficulty calibration built on that success rate (§70).
 *
 * Idempotent by slug. Changing an answer, the options or the snippet must also
 * bump `version`, so historical attempts stay interpretable (§71).
 */
class CursedCodeSeeder extends Seeder
{
    public function run(): void
    {
        $experience = Experience::query()->where('slug', 'cursed-code')->first();

        if ($experience === null) {
            // ExperienceSeeder owns the row; without it there is nothing to
            // attach content to, and inventing one here would hide the mistake.
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
                'slug' => 'php-float-equality',
                'title' => 'Two tenths and a lie',
                'description' => 'A comparison that every language gets "wrong" in the same way.',
                'objective' => 'Predict what this prints.',
                'difficulty' => 'easy',
                'type' => 'guess_output',
                'points' => 100,
                'estimated_minutes' => 2,
                'tags' => ['php', 'floating-point'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'php',
                    'mode' => 'guess_output',
                    'snippet' => "<?php\n\nvar_dump(0.1 + 0.2 == 0.3);",
                    'prompt' => 'What does this print?',
                    'options' => [
                        ['key' => 'a', 'text' => 'bool(true)'],
                        ['key' => 'b', 'text' => 'bool(false)'],
                        ['key' => 'c', 'text' => 'float(0.3)'],
                        ['key' => 'd', 'text' => 'A TypeError'],
                    ],
                ],
                'solution' => ['answer' => 'b'],
                'explanation' => "It prints bool(false).\n\n"
                    .'0.1 and 0.2 have no exact representation in IEEE 754 binary floating point — '
                    .'the same reason 1/3 has no exact decimal representation. Each is stored as the '
                    .'nearest representable double, and the sum of those two approximations is '
                    ."0.30000000000000004, which is a different double from the one nearest to 0.3.\n\n"
                    .'This is not a PHP quirk. The same comparison is false in JavaScript, Python, '
                    ."Java and C.\n\n"
                    .'Compare floats by asking whether the difference is smaller than a tolerance you '
                    .'choose — abs(\$a - \$b) < PHP_FLOAT_EPSILON — or avoid the problem by working in '
                    .'integers, which is why money belongs in cents.',
            ],
            [
                'slug' => 'js-typeof-nan',
                'title' => 'Not a number, allegedly',
                'description' => 'The value whose entire purpose is to not be a number.',
                'objective' => 'Predict what this logs.',
                'difficulty' => 'easy',
                'type' => 'guess_output',
                'points' => 100,
                'estimated_minutes' => 2,
                'tags' => ['javascript', 'types'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'javascript',
                    'mode' => 'guess_output',
                    'snippet' => 'console.log(typeof NaN);',
                    'prompt' => 'What does this log?',
                    'options' => [
                        ['key' => 'a', 'text' => '"NaN"'],
                        ['key' => 'b', 'text' => '"number"'],
                        ['key' => 'c', 'text' => '"undefined"'],
                        ['key' => 'd', 'text' => '"object"'],
                    ],
                ],
                'solution' => ['answer' => 'b'],
                'explanation' => "It logs \"number\".\n\n"
                    .'NaN is a value OF the number type, not a separate type. IEEE 754 defines it as '
                    .'the result of an undefined numeric operation — 0/0, Math.sqrt(-1), '
                    ."parseInt(\"abc\") — and it has to live somewhere in the type system. It lives in Number.\n\n"
                    .'The genuinely useful consequence is that NaN is the only value not equal to '
                    .'itself, since IEEE 754 says every comparison involving NaN is false. That is '
                    .'what Number.isNaN() and Object.is() exist for; `x === NaN` is always false, '
                    .'including when x is NaN.',
            ],
            [
                'slug' => 'js-default-sort',
                'title' => 'Sorted, for a given value of sorted',
                'description' => 'Three numbers, one sort, no comparator.',
                'objective' => 'Predict what this logs.',
                'difficulty' => 'medium',
                'type' => 'guess_output',
                'points' => 100,
                'estimated_minutes' => 3,
                'tags' => ['javascript', 'arrays', 'sorting'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'javascript',
                    'mode' => 'guess_output',
                    'snippet' => 'console.log([10, 9, 1].sort());',
                    'prompt' => 'What does this log?',
                    'options' => [
                        ['key' => 'a', 'text' => '[ 1, 9, 10 ]'],
                        ['key' => 'b', 'text' => '[ 1, 10, 9 ]'],
                        ['key' => 'c', 'text' => '[ 10, 9, 1 ]'],
                        ['key' => 'd', 'text' => '[ 9, 10, 1 ]'],
                    ],
                ],
                'solution' => ['answer' => 'b'],
                'explanation' => "It logs [ 1, 10, 9 ].\n\n"
                    .'Array.prototype.sort with no comparator converts every element to a string and '
                    .'sorts by UTF-16 code unit order. As strings, "1" < "10" < "9", because '
                    ."comparison is character by character and \"1\" sorts before \"9\".\n\n"
                    .'The spec is explicit about it: if no comparator is supplied, elements are '
                    ."converted with ToString and compared as strings.\n\n"
                    .'Pass a comparator whenever the array is not strings: [10, 9, 1].sort((a, b) => a - b). '
                    .'This is a genuinely common production bug, because it looks correct on '
                    .'single-digit test data and breaks the moment a value reaches ten.',
            ],
            [
                'slug' => 'php-string-to-zero',
                'title' => 'The comparison that changed its mind',
                'description' => 'A famous PHP gotcha. It has a version number attached.',
                'objective' => 'Predict what this prints on PHP 8.',
                'difficulty' => 'medium',
                'type' => 'guess_output',
                'points' => 100,
                'estimated_minutes' => 3,
                'tags' => ['php', 'type-juggling'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'php',
                    'mode' => 'guess_output',
                    'snippet' => "<?php\n\n// PHP 8\nvar_dump(\"abc\" == 0);",
                    'prompt' => 'What does this print on PHP 8?',
                    'options' => [
                        ['key' => 'a', 'text' => 'bool(true)'],
                        ['key' => 'b', 'text' => 'bool(false)'],
                        ['key' => 'c', 'text' => 'A TypeError'],
                        ['key' => 'd', 'text' => 'int(0)'],
                    ],
                ],
                'solution' => ['answer' => 'b'],
                'explanation' => "On PHP 8 it prints bool(false). On PHP 7 it printed bool(true).\n\n"
                    .'This is the single most consequential change in the "Saner string to number '
                    .'comparisons" RFC, accepted for PHP 8. Before it, comparing a string to a number '
                    .'cast the STRING to a number: "abc" became 0, so "abc" == 0 was true. Since PHP 8, '
                    .'a non-numeric string causes the NUMBER to be cast to a string instead, so the '
                    ."comparison is \"abc\" == \"0\", which is false.\n\n"
                    .'Numeric strings still compare numerically: "1e3" == "1000" is true in both '
                    ."versions, because both sides are numeric strings.\n\n"
                    .'The reason this mattered is that `if ($password == 0)` used to be true for any '
                    .'non-numeric password. Use === when you mean identical, and it never comes up.',
            ],
            [
                'slug' => 'js-map-parseint',
                'title' => 'Three strings walk into a map',
                'description' => 'A one-liner that has shipped more bugs than it has any right to.',
                'objective' => 'Predict what this logs.',
                'difficulty' => 'medium',
                'type' => 'guess_output',
                'points' => 100,
                'estimated_minutes' => 4,
                'tags' => ['javascript', 'arrays', 'arity'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'javascript',
                    'mode' => 'guess_output',
                    'snippet' => 'console.log(["1", "7", "11"].map(parseInt));',
                    'prompt' => 'What does this log?',
                    'options' => [
                        ['key' => 'a', 'text' => '[ 1, 7, 11 ]'],
                        ['key' => 'b', 'text' => '[ 1, NaN, 3 ]'],
                        ['key' => 'c', 'text' => '[ 1, NaN, NaN ]'],
                        ['key' => 'd', 'text' => '[ "1", "7", "11" ]'],
                    ],
                ],
                'solution' => ['answer' => 'b'],
                'explanation' => "It logs [ 1, NaN, 3 ].\n\n"
                    .'map calls its callback with three arguments: element, index, array. parseInt '
                    ."takes two: string and radix. So the index is being passed as the radix.\n\n"
                    ."  parseInt(\"1\", 0)   radix 0 means \"guess\", which gives base 10 → 1\n"
                    ."  parseInt(\"7\", 1)   base 1 is not a valid radix → NaN\n"
                    ."  parseInt(\"11\", 2)  binary → 3\n\n"
                    .'The lesson is about arity, not about parseInt. Passing a function reference to '
                    .'map is only safe when that function ignores the extra arguments. Wrap it when '
                    .'it does not: .map(s => parseInt(s, 10)), or use Number, which takes exactly one '
                    .'argument.',
            ],
            [
                'slug' => 'js-math-max-empty',
                'title' => 'The maximum of nothing',
                'description' => 'It returns a number. It is not the number you expect.',
                'objective' => 'Explain why Math.max() returns -Infinity.',
                'difficulty' => 'hard',
                'type' => 'explain_behaviour',
                'points' => 100,
                'estimated_minutes' => 4,
                'tags' => ['javascript', 'math', 'spec'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'javascript',
                    'mode' => 'explain_behaviour',
                    'snippet' => "console.log(Math.max());\n// -Infinity",
                    'prompt' => 'Why is the result -Infinity rather than 0, NaN or an error?',
                    'options' => [
                        ['key' => 'a', 'text' => 'It is an unspecified edge case; engines differ.'],
                        ['key' => 'b', 'text' => '-Infinity is the identity element for max, so it is the correct starting value for an empty set.'],
                        ['key' => 'c', 'text' => 'Missing arguments become undefined, which coerces to -Infinity.'],
                        ['key' => 'd', 'text' => 'Math.max returns the smallest possible number when it cannot compare anything.'],
                    ],
                ],
                'solution' => ['answer' => 'b'],
                'explanation' => "The answer is the identity element.\n\n"
                    .'Math.max is specified to start from -Infinity and keep the larger value as it '
                    .'walks its arguments. With no arguments it never updates, so the starting value '
                    ."is returned.\n\n"
                    .'That starting value is not arbitrary. -Infinity is the identity for max, the way '
                    .'0 is for addition and 1 is for multiplication: max(x, -Infinity) === x for every '
                    .'x. It is the only choice that keeps max(max(a), max(b)) === max(a, b) true when '
                    ."either set is empty, which is what makes reducing in chunks safe.\n\n"
                    .'Math.min() returns Infinity for the mirror-image reason.\n\n'
                    .'The practical trap is Math.max(...values) on an array that turns out to be '
                    .'empty: the result is -Infinity, which is a number, so it passes a typeof check '
                    .'and then poisons whatever arithmetic follows.',
            ],
            [
                'slug' => 'php-dangling-foreach-reference',
                'title' => 'The loop that ate its own tail',
                'description' => 'Two loops. The second one only reads. The array changes anyway.',
                'objective' => 'Predict what this prints.',
                'difficulty' => 'expert',
                'type' => 'guess_output',
                'points' => 500,
                'estimated_minutes' => 8,
                'tags' => ['php', 'references', 'foreach'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'language' => 'php',
                    'mode' => 'guess_output',
                    'snippet' => '<?php

'
                        ."\$items = ['a', 'b', 'c'];

"
                        .'foreach ($items as &$item) {
'
                        .'    $item = strtoupper($item);
'
                        .'}

'
                        .'foreach ($items as $item) {
'
                        .'    // deliberately empty
'
                        .'}

'
                        .'print_r($items);',
                    'prompt' => 'What does this print?',
                    'options' => [
                        ['key' => 'a', 'text' => 'Array ( [0] => A [1] => B [2] => C )'],
                        ['key' => 'b', 'text' => 'Array ( [0] => A [1] => B [2] => B )'],
                        ['key' => 'c', 'text' => 'Array ( [0] => a [1] => b [2] => c )'],
                        ['key' => 'd', 'text' => 'Array ( [0] => A [1] => A [2] => A )'],
                    ],
                ],
                'solution' => ['answer' => 'b'],
                'explanation' => 'It prints [A, B, B]. Verified on PHP 8.5.9.

'
                    .'The first loop is the ordinary part: each element is upper-cased through the '
                    .'reference, giving [A, B, C]. What matters is what SURVIVES it — after a '
                    .'foreach by reference, $item is still a reference to the last element, and PHP '
                    .'does not unset it when the loop ends.

'
                    .'So the second loop is not reading into a fresh variable. Every iteration '
                    ."assigns into \$items[2]. Pass one writes 'A' there, pass two writes 'B', and "
                    ."pass three reads \$items[2] — which is now 'B' — and writes it back to "
                    .'itself. The array ends [A, B, B].

'
                    .'The tell is that the last element always ends up holding the SECOND-TO-LAST '
                    .'value: the final read happens after the slot has already been overwritten.

'
                    .'`unset($item)` immediately after any foreach by reference ends it. This is '
                    .'why some teams ban `foreach ($x as &$y)` outright — the bug appears in code '
                    .'far from the reference, and reads as data corruption rather than as a loop '
                    .'problem.',
            ],
        ];
    }
}
