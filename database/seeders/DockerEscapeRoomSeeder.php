<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Seeder;

/**
 * Seed content for Docker Escape Room.
 *
 * Every fault here is one that behaves differently inside a container than
 * outside it — that is the class of bug this experience exists for, and the
 * class that costs real teams whole afternoons. None of them is a typo.
 *
 * The wrong fixes are all things a competent engineer would actually try. A
 * challenge whose distractors are obviously absurd tests reading, not diagnosis.
 *
 * Line numbers in `solution.line` are 1-based over the evidence panel exactly as
 * the player sees it, and are the thing most likely to drift when content is
 * edited — which is why the validator checks them and a test runs that validator
 * over this content (§70).
 *
 * Idempotent by slug. Changing evidence or a line number must bump `version`.
 */
class DockerEscapeRoomSeeder extends Seeder
{
    public function run(): void
    {
        $experience = Experience::query()->where('slug', 'docker-escape-room')->first();

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
                'slug' => 'bound-to-loopback',
                'title' => 'It says it is listening',
                'description' => 'The logs are cheerful. Nothing reaches the port.',
                'objective' => 'Find the fault and say what fixes it.',
                'difficulty' => 'easy',
                'type' => 'diagnose',
                'points' => 100,
                'estimated_minutes' => 6,
                'tags' => ['docker', 'networking'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'symptom' => 'The container starts and stays up. Its logs say it is listening. '
                        .'Every request from the host to http://localhost:3000 is refused.',
                    'evidence' => [
                        [
                            'key' => 'dockerfile',
                            'label' => 'Dockerfile',
                            'language' => 'dockerfile',
                            'content' => implode("\n", [
                                'FROM node:22-alpine',
                                'WORKDIR /app',
                                'COPY package*.json ./',
                                'RUN npm ci --omit=dev',
                                'COPY . .',
                                'EXPOSE 3000',
                                'CMD ["node", "server.js", "--host", "127.0.0.1"]',
                            ]),
                        ],
                        [
                            'key' => 'compose',
                            'label' => 'docker-compose.yml',
                            'language' => 'yaml',
                            'content' => implode("\n", [
                                'services:',
                                '    api:',
                                '        build: .',
                                '        ports:',
                                "            - '3000:3000'",
                            ]),
                        ],
                        [
                            'key' => 'logs',
                            'label' => 'Container logs',
                            'language' => 'text',
                            'selectable' => false,
                            'content' => implode("\n", [
                                'api  | server starting',
                                'api  | listening on 127.0.0.1:3000',
                            ]),
                        ],
                    ],
                    'fixes' => [
                        ['key' => 'bind_all', 'text' => 'Bind the server to 0.0.0.0 instead of 127.0.0.1'],
                        ['key' => 'publish', 'text' => 'Publish the port — it is missing from the compose file'],
                        ['key' => 'expose', 'text' => 'Add an EXPOSE instruction to the Dockerfile'],
                        ['key' => 'host_network', 'text' => 'Run the container with host networking'],
                    ],
                ],
                'solution' => [
                    'evidence' => 'dockerfile',
                    'line' => 7,
                    'fix' => 'bind_all',
                    'summary' => 'The process binds to the container\'s own loopback, which nothing outside the container shares.',
                ],
                'explanation' => 'Inside a container, 127.0.0.1 is the container\'s loopback, not the '
                    .'host\'s. A process bound there is reachable only from within that container, so '
                    .'the published port forwards to an address nothing is listening on. The port '
                    .'mapping is correct and EXPOSE is documentation — neither is the problem. Host '
                    .'networking would make it work by removing the network boundary, which is '
                    .'treating the symptom by deleting the container\'s isolation.',
            ],
            [
                'slug' => 'anonymous-volume-shadows-build',
                'title' => 'The dependencies were there a moment ago',
                'description' => 'It builds. It runs. It cannot find a module it definitely installed.',
                'objective' => 'Find the fault and say what fixes it.',
                'difficulty' => 'medium',
                'type' => 'diagnose',
                'points' => 150,
                'estimated_minutes' => 8,
                'tags' => ['docker', 'volumes', 'builds'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'symptom' => 'The image builds without error. Started through compose, the '
                        .'container exits immediately with "Cannot find module \'express\'". Running '
                        .'the same image with `docker run` works.',
                    'evidence' => [
                        [
                            'key' => 'dockerfile',
                            'label' => 'Dockerfile',
                            'language' => 'dockerfile',
                            'content' => implode("\n", [
                                'FROM node:22-alpine',
                                'WORKDIR /app',
                                'COPY package*.json ./',
                                'RUN npm ci',
                                'COPY . .',
                                'CMD ["node", "server.js"]',
                            ]),
                        ],
                        [
                            'key' => 'compose',
                            'label' => 'docker-compose.yml',
                            'language' => 'yaml',
                            'content' => implode("\n", [
                                'services:',
                                '    api:',
                                '        build: .',
                                '        volumes:',
                                '            - .:/app',
                                '        ports:',
                                "            - '3000:3000'",
                            ]),
                        ],
                        [
                            'key' => 'logs',
                            'label' => 'Container logs',
                            'language' => 'text',
                            'selectable' => false,
                            'content' => implode("\n", [
                                "api  | Error: Cannot find module 'express'",
                                'api  | Require stack:',
                                'api  | - /app/server.js',
                                'api exited with code 1',
                            ]),
                        ],
                    ],
                    'fixes' => [
                        ['key' => 'anon_volume', 'text' => 'Add an anonymous volume for /app/node_modules so the bind mount cannot hide it'],
                        ['key' => 'reinstall', 'text' => 'Rebuild the image with --no-cache'],
                        ['key' => 'add_dep', 'text' => 'Add express to package.json — it is missing'],
                        ['key' => 'workdir', 'text' => 'Change WORKDIR to a directory the mount does not cover'],
                    ],
                ],
                'solution' => [
                    'evidence' => 'compose',
                    'line' => 5,
                    'fix' => 'anon_volume',
                    'summary' => 'The bind mount replaces /app wholesale, including the node_modules the build put there.',
                ],
                'explanation' => 'The build installs dependencies into /app/node_modules inside the '
                    .'image. The compose bind mount then mounts the host directory over /app, and a '
                    .'host checkout has no node_modules — so the installed ones are hidden, not '
                    .'deleted. That is why `docker run` on the same image works: no mount, nothing '
                    .'hidden. The conventional fix is a second, anonymous volume at '
                    .'/app/node_modules, which takes precedence over the bind mount for that path. '
                    .'Rebuilding without cache changes nothing, because the image was never wrong.',
            ],
            [
                'slug' => 'depends-on-is-not-ready',
                'title' => 'depends_on did not wait',
                'description' => 'It works on the second start, every time.',
                'objective' => 'Find the fault and say what fixes it.',
                'difficulty' => 'medium',
                'type' => 'diagnose',
                'points' => 150,
                'estimated_minutes' => 8,
                'tags' => ['docker', 'compose', 'startup'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'symptom' => 'On a cold `docker compose up`, the API exits with a database '
                        .'connection error. Starting it again immediately afterwards works, and it '
                        .'has worked every time since.',
                    'evidence' => [
                        [
                            'key' => 'compose',
                            'label' => 'docker-compose.yml',
                            'language' => 'yaml',
                            'content' => implode("\n", [
                                'services:',
                                '    api:',
                                '        build: .',
                                '        depends_on:',
                                '            - db',
                                '        environment:',
                                '            DB_HOST: db',
                                '    db:',
                                '        image: postgres:17',
                                '        environment:',
                                '            POSTGRES_PASSWORD: secret',
                            ]),
                        ],
                        [
                            'key' => 'logs',
                            'label' => 'Container logs',
                            'language' => 'text',
                            'selectable' => false,
                            'content' => implode("\n", [
                                'db   | PostgreSQL init process complete; ready for start up.',
                                'api  | connecting to db:5432',
                                'api  | Error: connect ECONNREFUSED 172.19.0.2:5432',
                                'api exited with code 1',
                                'db   | database system is ready to accept connections',
                            ]),
                        ],
                        [
                            'key' => 'entrypoint',
                            'label' => 'entrypoint.sh',
                            'language' => 'bash',
                            'content' => implode("\n", [
                                '#!/usr/bin/env sh',
                                'set -e',
                                'node migrate.js',
                                'exec node server.js',
                            ]),
                        ],
                    ],
                    'fixes' => [
                        ['key' => 'healthcheck', 'text' => 'Give db a healthcheck and make depends_on wait for service_healthy'],
                        ['key' => 'restart', 'text' => 'Set restart: always on the API so it retries'],
                        ['key' => 'sleep', 'text' => 'Sleep for fifteen seconds at the top of the entrypoint'],
                        ['key' => 'network', 'text' => 'Put both services on an explicit user-defined network'],
                    ],
                ],
                'solution' => [
                    'evidence' => 'compose',
                    'line' => 4,
                    'fix' => 'healthcheck',
                    'summary' => 'Plain depends_on waits for the container to start, not for Postgres to accept connections.',
                ],
                'explanation' => 'Plain `depends_on` orders container STARTUP. It says nothing about '
                    .'readiness, and Postgres spends several seconds initialising before it accepts '
                    .'connections — the logs show the API failing between the two db lines. The '
                    .'ordering condition has to be a readiness condition: a healthcheck on db plus '
                    .'`condition: service_healthy`. `restart: always` makes it work by failing and '
                    .'retrying, which is a real pattern but leaves a crash in every cold start. A '
                    .'sleep is the same bet with worse odds.',
            ],
            [
                'slug' => 'env-set-at-build-time',
                'title' => 'The secret that baked itself in',
                'description' => 'Changing the environment variable changes nothing.',
                'objective' => 'Find the fault and say what fixes it.',
                'difficulty' => 'medium',
                'type' => 'diagnose',
                'points' => 150,
                'estimated_minutes' => 7,
                'tags' => ['docker', 'builds', 'configuration'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'symptom' => 'The API talks to the staging database no matter what API_URL is '
                        .'set to at run time. Changing the compose environment and restarting has no '
                        .'effect; only rebuilding the image does.',
                    'evidence' => [
                        [
                            'key' => 'dockerfile',
                            'label' => 'Dockerfile',
                            'language' => 'dockerfile',
                            'content' => implode("\n", [
                                'FROM node:22-alpine AS build',
                                'WORKDIR /app',
                                'COPY package*.json ./',
                                'RUN npm ci',
                                'COPY . .',
                                'RUN npm run build',
                                '',
                                'FROM node:22-alpine',
                                'WORKDIR /app',
                                'COPY --from=build /app/dist ./dist',
                                'CMD ["node", "dist/server.js"]',
                            ]),
                        ],
                        [
                            'key' => 'compose',
                            'label' => 'docker-compose.yml',
                            'language' => 'yaml',
                            'content' => implode("\n", [
                                'services:',
                                '    api:',
                                '        build: .',
                                '        environment:',
                                '            API_URL: https://api.production.example',
                            ]),
                        ],
                        [
                            'key' => 'source',
                            'label' => 'src/config.js',
                            'language' => 'javascript',
                            'content' => implode("\n", [
                                '// Inlined by the bundler at build time.',
                                'export const apiUrl = process.env.API_URL;',
                                '',
                                'export default { apiUrl };',
                            ]),
                        ],
                    ],
                    'fixes' => [
                        ['key' => 'read_at_runtime', 'text' => 'Read the value at run time rather than letting the bundler inline it at build time'],
                        ['key' => 'build_arg', 'text' => 'Pass API_URL as a build argument so the build sees it'],
                        ['key' => 'env_in_dockerfile', 'text' => 'Add ENV API_URL to the runtime stage of the Dockerfile'],
                        ['key' => 'rebuild', 'text' => 'Rebuild with --no-cache before each deploy'],
                    ],
                ],
                'solution' => [
                    'evidence' => 'source',
                    'line' => 2,
                    'fix' => 'read_at_runtime',
                    'summary' => 'The bundler replaces process.env.API_URL with a literal during npm run build.',
                ],
                'explanation' => 'The value is read during `npm run build`, in the build stage, and '
                    .'the bundler replaces the expression with whatever the string was then. By the '
                    .'time the runtime stage sets an environment variable there is no lookup left to '
                    .'affect — the literal is already in dist/. A build argument would change which '
                    .'value gets baked in, not the fact that one is. This is the difference between '
                    .'build-time and run-time configuration, and it is why the symptom is "only '
                    .'rebuilding helps".',
            ],
            [
                'slug' => 'shell-form-ignores-signals',
                'title' => 'It takes ten seconds to stop',
                'description' => 'Every deploy pauses. Nothing in the logs explains it.',
                'objective' => 'Find the fault and say what fixes it.',
                'difficulty' => 'hard',
                'type' => 'diagnose',
                'points' => 200,
                'estimated_minutes' => 9,
                'tags' => ['docker', 'signals', 'lifecycle'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'symptom' => '`docker compose stop` takes exactly ten seconds for this service '
                        .'and returns immediately for every other one. The application never logs '
                        .'its shutdown message, which it does correctly when run outside Docker.',
                    'evidence' => [
                        [
                            'key' => 'dockerfile',
                            'label' => 'Dockerfile',
                            'language' => 'dockerfile',
                            'content' => implode("\n", [
                                'FROM node:22-alpine',
                                'WORKDIR /app',
                                'COPY . .',
                                'RUN npm ci --omit=dev',
                                'CMD node server.js',
                            ]),
                        ],
                        [
                            'key' => 'source',
                            'label' => 'server.js',
                            'language' => 'javascript',
                            'content' => implode("\n", [
                                "process.on('SIGTERM', () => {",
                                "    console.log('shutting down');",
                                '    server.close(() => process.exit(0));',
                                '});',
                            ]),
                        ],
                        [
                            'key' => 'ps',
                            'label' => 'docker top output',
                            'language' => 'text',
                            'selectable' => false,
                            'content' => implode("\n", [
                                'PID    CMD',
                                '1      /bin/sh -c node server.js',
                                '7      node server.js',
                            ]),
                        ],
                    ],
                    'fixes' => [
                        ['key' => 'exec_form', 'text' => 'Use the exec form of CMD so the process is PID 1'],
                        ['key' => 'stop_grace', 'text' => 'Lower stop_grace_period so the wait is shorter'],
                        ['key' => 'sigkill', 'text' => 'Set STOPSIGNAL to SIGKILL'],
                        ['key' => 'handler', 'text' => 'Register the SIGTERM handler earlier in server.js'],
                    ],
                ],
                'solution' => [
                    'evidence' => 'dockerfile',
                    'line' => 5,
                    'fix' => 'exec_form',
                    'summary' => 'Shell-form CMD makes /bin/sh PID 1, and it does not forward SIGTERM to its child.',
                ],
                'explanation' => 'The shell form of CMD runs the command through /bin/sh, which '
                    .'becomes PID 1 — visible in the process list. Docker sends SIGTERM to PID 1, and '
                    .'that shell does not forward it, so the handler in server.js never runs and '
                    .'Docker gives up after the ten-second grace period and sends SIGKILL. The exec '
                    .'form, CMD ["node", "server.js"], makes node PID 1 and it receives the signal '
                    .'directly. Lowering the grace period makes the symptom shorter and the shutdown '
                    .'no cleaner; SIGKILL removes the possibility of a clean shutdown altogether.',
            ],
            [
                'slug' => 'copy-before-install-cache',
                'title' => 'Every build installs everything',
                'description' => 'A one-line change to the README rebuilds the whole dependency tree.',
                'objective' => 'Find the fault and say what fixes it.',
                'difficulty' => 'easy',
                'type' => 'diagnose',
                'points' => 100,
                'estimated_minutes' => 6,
                'tags' => ['docker', 'builds', 'caching'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'symptom' => 'Every build reinstalls all dependencies and takes four minutes, '
                        .'even when only a source file changed. The dependencies themselves have not '
                        .'changed in weeks.',
                    'evidence' => [
                        [
                            'key' => 'dockerfile',
                            'label' => 'Dockerfile',
                            'language' => 'dockerfile',
                            'content' => implode("\n", [
                                'FROM node:22-alpine',
                                'WORKDIR /app',
                                'COPY . .',
                                'RUN npm ci --omit=dev',
                                'EXPOSE 3000',
                                'CMD ["node", "server.js"]',
                            ]),
                        ],
                        [
                            'key' => 'buildlog',
                            'label' => 'Build output',
                            'language' => 'text',
                            'selectable' => false,
                            'content' => implode("\n", [
                                '=> CACHED [1/5] FROM node:22-alpine',
                                '=> CACHED [2/5] WORKDIR /app',
                                '=> [3/5] COPY . .                      0.4s',
                                '=> [4/5] RUN npm ci --omit=dev       231.7s',
                            ]),
                        ],
                        [
                            'key' => 'ignore',
                            'label' => '.dockerignore',
                            'language' => 'text',
                            'content' => implode("\n", [
                                'node_modules',
                                '.git',
                            ]),
                        ],
                    ],
                    'fixes' => [
                        ['key' => 'copy_manifest_first', 'text' => 'Copy package.json and the lockfile first, install, then copy the source'],
                        ['key' => 'ignore_more', 'text' => 'Add more entries to .dockerignore'],
                        ['key' => 'mount_cache', 'text' => 'Mount the npm cache and keep the order as it is'],
                        ['key' => 'npm_install', 'text' => 'Use npm install rather than npm ci'],
                    ],
                ],
                'solution' => [
                    'evidence' => 'dockerfile',
                    'line' => 3,
                    'fix' => 'copy_manifest_first',
                    'summary' => 'Copying the whole source before installing invalidates the install layer on every source change.',
                ],
                'explanation' => 'A layer is cached until one of its inputs changes. `COPY . .` makes '
                    .'every source file an input to the layer above the install, so touching the '
                    .'README invalidates it and everything after — the build log shows exactly that, '
                    .'with the install taking 231 seconds while the copy took 0.4. Copying the '
                    .'manifest and lockfile first means the install layer depends only on the '
                    .'dependencies, and is reused until they actually change. A cache mount makes '
                    .'the reinstall faster without making it unnecessary.',
            ],
            [
                'slug' => 'runtime-that-cannot-see-its-cgroup',
                'title' => 'Killed at 512 megabytes it never knew about',
                'description' => 'Exit 137, no stack trace, and a machine with 64GB of RAM.',
                'objective' => 'Find the fault and say what fixes it.',
                'difficulty' => 'expert',
                'type' => 'diagnose',
                'points' => 500,
                'estimated_minutes' => 13,
                'tags' => ['docker', 'cgroups', 'memory', 'runtime'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'symptom' => 'The worker container dies after a few minutes with exit code 137 '
                        .'and no application error. It processes the same jobs fine outside Docker '
                        .'on the same host, which has 64GB of RAM and is nowhere near full.',
                    'evidence' => [
                        [
                            'key' => 'dockerfile',
                            'label' => 'Dockerfile',
                            'language' => 'dockerfile',
                            'content' => implode("\n", [
                                'FROM php:8.4-cli-alpine',
                                'WORKDIR /app',
                                'COPY . .',
                                'RUN composer install --no-dev',
                                'CMD ["php", "-d", "memory_limit=-1", "worker.php"]',
                            ]),
                        ],
                        [
                            'key' => 'compose',
                            'label' => 'docker-compose.yml',
                            'language' => 'yaml',
                            'content' => implode("\n", [
                                'services:',
                                '    worker:',
                                '        build: .',
                                '        deploy:',
                                '            resources:',
                                '                limits:',
                                '                    memory: 512M',
                            ]),
                        ],
                        [
                            'key' => 'inspect',
                            'label' => 'docker inspect (excerpt)',
                            'language' => 'json',
                            'selectable' => false,
                            'content' => implode("\n", [
                                '"State": {',
                                '    "OOMKilled": true,',
                                '    "ExitCode": 137,',
                                '    "Error": ""',
                                '}',
                            ]),
                        ],
                        [
                            'key' => 'logs',
                            'label' => 'Container logs',
                            'language' => 'text',
                            'selectable' => false,
                            'content' => implode("\n", [
                                'worker | processing batch 41',
                                'worker | processing batch 42',
                                'worker exited with code 137',
                            ]),
                        ],
                    ],
                    'fixes' => [
                        ['key' => 'runtime_limit', 'text' => 'Give the runtime its own memory limit, set below the container limit'],
                        ['key' => 'raise_container', 'text' => 'Raise the container memory limit until it stops'],
                        ['key' => 'restart_always', 'text' => 'Add restart: always so the worker comes back'],
                        ['key' => 'allow_swap', 'text' => 'Allow swap so the process is not killed outright'],
                    ],
                ],
                'solution' => [
                    'evidence' => 'dockerfile',
                    'line' => 5,
                    'fix' => 'runtime_limit',
                    'summary' => 'memory_limit=-1 lets the process grow past a cgroup limit it never checks.',
                ],
                'explanation' => 'Exit 137 is 128 + 9 - SIGKILL - and OOMKilled: true says who sent '
                    ."it. Not the application: the kernel, on behalf of the cgroup.\n\n"
                    .'memory_limit=-1 tells PHP it may use everything. PHP then asks the operating '
                    .'system how much that is, and on a container without cgroup-aware reporting the '
                    .'answer is the HOST total - 64GB. So the process happily grows past 512MB, the '
                    ."cgroup enforces the limit it was never told about, and the kernel kills it.\n\n"
                    .'This is the same failure the JVM had for years before UseContainerSupport, '
                    .'and it is why every runtime now ships a container-aware default. The rule is '
                    .'that a runtime must be given a ceiling BELOW the container ceiling, so the '
                    ."runtime fails first - with a stack trace you can read.\n\n"
                    .'Raising the container limit moves the wall without removing it. Swap makes the '
                    .'failure slower rather than absent, and on most orchestrators is unavailable '
                    .'anyway. restart: always turns a crash into a crash loop.',
            ],
        ];
    }
}
