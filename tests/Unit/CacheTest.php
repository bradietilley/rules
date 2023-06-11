<?php

use BradieTilley\Rules\Rule;
use BradieTilley\Rules\RuleCache;
use Illuminate\Validation\Rules\File;
use SebastianBergmann\Timer\Timer;

function output(string $line): void
{
    fwrite(STDERR, $line.PHP_EOL);
}

test('rule cache test', function () {
    $runs = [
        '1 iteration' => 1,
        '5 iterations' => 5,
        '10 iterations' => 10,
        // '50 iterations' => 50,
        // '100 iterations' => 100,
        // '500 iterations' => 500,
        '1000 iterations' => 1000,
    ];

    foreach ($runs as $iterations) {
        $runOriginal = function (int $iterations) {
            foreach (range(1, $iterations) as $i) {
                $example = [
                    'first_name' => [
                        'required',
                        'string',
                        'min:1',
                        'max:100',
                    ],
                    'last_name' => [
                        'required',
                        'string',
                        'min:1',
                        'max:100',
                    ],
                    'email' => [
                        'required',
                        'string',
                        'email',
                    ],
                    'phone' => [
                        'required',
                        'string',
                        'min:9',
                        'max:11',
                    ],
                    'avatar' => [
                        'required',
                        File::types([
                            'image/jpeg',
                            'image/png',
                        ])->min(10)->max(10000),
                    ],
                ];
            }
        };

        $runWithout = function (int $iterations) {
            foreach (range(1, $iterations) as $i) {
                $example = [
                    'first_name' => Rule::make()
                        ->required()
                        ->string()
                        ->min(1)
                        ->max(100),
                    'last_name' => Rule::make()
                        ->required()
                        ->string()
                        ->min(1)
                        ->max(100),
                    'email' => Rule::make()
                        ->required()
                        ->string()
                        ->email(),
                    'phone' => Rule::make()
                        ->required()
                        ->string()
                        ->min(9)
                        ->max(11),
                    'avatar' => Rule::make()
                        ->required()
                        ->file(minKilobytes: 10, maxKilobytes: 10000, allowedMimetypes: [
                            'image/jpeg',
                            'image/png',
                        ]),
                ];
            }
        };

        $runWith = function (int $iterations) {
            RuleCache::reset();

            foreach (range(1, $iterations) as $i) {
                $example = Rule::cache('something', fn () => [
                    'first_name' => Rule::make()
                        ->required()
                        ->string()
                        ->min(1)
                        ->max(100),
                    'last_name' => Rule::make()
                        ->required()
                        ->string()
                        ->min(1)
                        ->max(100),
                    'email' => Rule::make()
                        ->required()
                        ->string()
                        ->email(),
                    'phone' => Rule::make()
                        ->required()
                        ->string()
                        ->min(9)
                        ->max(11),
                    'avatar' => Rule::make()
                        ->required()
                        ->file(minKilobytes: 10, maxKilobytes: 10000, allowedMimetypes: [
                            'image/jpeg',
                            'image/png',
                        ]),
                ]);
            }
        };

        /**
         * Run both once to ensure any overhead caused by first
         * invocation/class loading is not counted in the timer
         * used for benchmarking.
         */
        $runOriginal(1);
        $runWithout(1);
        $runWith(1);

        $timer = new Timer();
        $timer->start();
        $runOriginal($iterations);
        $original = $timer->stop();

        $timer = new Timer();
        $timer->start();
        $runWithout($iterations);
        $without = $timer->stop();

        $timer = new Timer();
        $timer->start();
        $runWith($iterations);
        $with = $timer->stop();

        $printBar = function (string $title, array $times) {
            output('');
            output('');
            output("==============================================");
            output("========= {$title}");
            output("==============================================");
            output('');

            $a = '▓';
            $b = '░';

            $max = collect($times)->max() * 1.01;

            foreach ($times as $key => $time) {
                $relative = ($time / $max) * 100;
                $remain = (100 - $relative);

                $keyFormatted = str_pad($key, 83);
                $timeFormatted = str_pad(round($time), 5, ' ', STR_PAD_LEFT) . ' µs';
                output("> {$keyFormatted} Time: {$timeFormatted}");

                $bar = str_repeat($a, round($relative)).str_repeat($b, round($remain));
                output($bar);
            }
        };

        // output([
        //     'iterations' => $iterations,
        //     'withoutPackage' => $originalMs = $original->asMilliseconds(),
        //     'withoutCaching' => $withoutMs = $without->asMilliseconds(),
        //     'withCaching' => $withMs = $with->asMilliseconds(),
        //     'msDifferenceCaching' => ($withoutMs - $withMs),
        //     'msDifferenceLaravel' => ($originalMs - $withMs),
        //     'pcDifferenceCaching' => ($withoutMs / $withMs),
        //     'pcDifferenceLaravel' => ($originalMs / $withMs),
        // ]);

        $printBar("Iterations: {$iterations}", [
            'Original without Laravel Rules' => $original->asMicroseconds(),
            'Laravel Rules without Caching' => $without->asMicroseconds(),
            'Laravel Rules with Caching On' => $with->asMicroseconds(),
        ]);
    }

    die('end');
});
