<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Seeder;

/**
 * Seed content for System Design Lab.
 *
 * Every challenge here declares a `reference` design, and a test runs the
 * validator over this content — which scores that reference against its own
 * rubric. A rubric naming an option that does not exist is unsatisfiable, so
 * every attempt would fail while nothing anywhere reported an error (§70).
 *
 * The designs are the conventional ones, not clever ones. This experience
 * teaches the shape of a standard answer and, through `none_of`, the cost of
 * reaching for machinery the brief does not need.
 *
 * Idempotent by slug. Changing a slot, an option or a rubric must bump `version`.
 */
class SystemDesignLabSeeder extends Seeder
{
    public function run(): void
    {
        $experience = Experience::query()->where('slug', 'system-design-lab')->first();

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
                'slug' => 'url-shortener-read-heavy',
                'title' => 'A URL shortener that is almost all reads',
                'description' => 'Ten thousand reads for every write. The shape of the traffic '
                    .'decides the shape of the system.',
                'objective' => 'Design a system that meets every requirement.',
                'difficulty' => 'easy',
                'type' => 'design',
                'points' => 100,
                'estimated_minutes' => 6,
                'tags' => ['caching', 'scaling', 'databases'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'scenario' => 'A URL shortener. One million redirects per second at peak, '
                        .'about a hundred new links per second. A link, once created, never '
                        .'changes. Losing one is unacceptable — somebody printed it on a poster.',
                    'requirements' => [
                        ['key' => 'read_throughput', 'text' => 'Serve a million redirects per second'],
                        ['key' => 'durability', 'text' => 'Never lose a link that was created'],
                        ['key' => 'proportionate', 'text' => 'Add no machinery the traffic does not justify'],
                    ],
                    'slots' => [
                        [
                            'key' => 'cache',
                            'label' => 'Read path',
                            'hint' => 'Costs memory and a consistency question; buys read throughput.',
                            'options' => [
                                ['key' => 'none', 'text' => 'Read straight from the database every time'],
                                ['key' => 'read_through', 'text' => 'A read-through cache in front of the database'],
                                ['key' => 'write_through', 'text' => 'A write-through cache: every write populates it'],
                            ],
                        ],
                        [
                            'key' => 'storage',
                            'label' => 'Storage',
                            'hint' => 'Links are immutable and looked up by one key.',
                            'options' => [
                                ['key' => 'single', 'text' => 'A single database instance'],
                                ['key' => 'replicated', 'text' => 'A primary with replicas and durable writes'],
                                ['key' => 'memory_only', 'text' => 'Keep links in memory only'],
                            ],
                        ],
                        [
                            'key' => 'queue',
                            'label' => 'Write path',
                            'hint' => 'A hundred writes per second is not a lot.',
                            'options' => [
                                ['key' => 'direct', 'text' => 'Write synchronously and return the link'],
                                ['key' => 'queued', 'text' => 'Queue the write and return before it lands'],
                            ],
                        ],
                    ],
                ],
                'solution' => [
                    'rubric' => [
                        [
                            'requirement' => 'read_throughput',
                            'any_of' => ['cache=read_through', 'cache=write_through'],
                            'explanation' => 'A million reads a second must not reach the database. '
                                .'Either cache works here; the links are immutable, so the usual '
                                .'invalidation problem does not exist.',
                        ],
                        [
                            'requirement' => 'durability',
                            'all_of' => ['storage=replicated'],
                            'explanation' => 'A single instance loses everything with the machine, '
                                .'and memory alone loses everything on restart. The poster is still '
                                .'on the wall.',
                        ],
                        [
                            'requirement' => 'proportionate',
                            'none_of' => ['queue=queued'],
                            'explanation' => 'A hundred writes per second needs no queue. Queueing '
                                .'it means telling the user their link exists before it does, and '
                                .'buys nothing at this volume.',
                        ],
                    ],
                    'reference' => ['cache' => 'read_through', 'storage' => 'replicated', 'queue' => 'direct'],
                    'pass_mark' => 1.0,
                ],
                'explanation' => 'Read-heavy and immutable is the friendliest combination in '
                    .'distributed systems: you can cache without ever having to invalidate. The '
                    .'trap is the write path — a queue looks like scaling, but at a hundred writes '
                    .'per second it only adds a window where a link the user was just shown does '
                    .'not resolve.',
            ],
            [
                'slug' => 'checkout-under-black-friday',
                'title' => 'Checkout, on the one day it matters',
                'description' => 'Traffic goes up forty times for six hours. Money is involved.',
                'objective' => 'Design a system that meets every requirement.',
                'difficulty' => 'medium',
                'type' => 'design',
                'points' => 150,
                'estimated_minutes' => 8,
                'tags' => ['queues', 'scaling', 'consistency'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'scenario' => 'An online shop. For six hours a year, checkout traffic is forty '
                        .'times normal. Orders must not be lost, and stock must not be oversold — '
                        .'selling the last item twice costs a refund and a complaint. Confirmation '
                        .'emails can wait.',
                    'requirements' => [
                        ['key' => 'absorb_spike', 'text' => 'Absorb a forty-times spike without dropping orders'],
                        ['key' => 'no_overselling', 'text' => 'Never sell the same unit of stock twice'],
                        ['key' => 'email_not_blocking', 'text' => 'Do not make the customer wait for an email'],
                    ],
                    'slots' => [
                        [
                            'key' => 'orders',
                            'label' => 'Order intake',
                            'hint' => 'Where the spike lands first.',
                            'options' => [
                                ['key' => 'synchronous', 'text' => 'Process each order synchronously in the request'],
                                ['key' => 'queued', 'text' => 'Accept the order onto a durable queue, process behind it'],
                                ['key' => 'dropped', 'text' => 'Shed load above a threshold and ask them to retry'],
                            ],
                        ],
                        [
                            'key' => 'stock',
                            'label' => 'Stock decrement',
                            'hint' => 'Two customers, one item, same millisecond.',
                            'options' => [
                                ['key' => 'read_then_write', 'text' => 'Read the count, decide, then write the new count'],
                                ['key' => 'atomic', 'text' => 'A conditional atomic decrement that fails if stock is gone'],
                                ['key' => 'cached_count', 'text' => 'Decrement a cached counter and reconcile later'],
                            ],
                        ],
                        [
                            'key' => 'email',
                            'label' => 'Confirmation email',
                            'options' => [
                                ['key' => 'inline', 'text' => 'Send it before responding to the customer'],
                                ['key' => 'background', 'text' => 'Hand it to a background worker'],
                            ],
                        ],
                    ],
                ],
                'solution' => [
                    'rubric' => [
                        [
                            'requirement' => 'absorb_spike',
                            'all_of' => ['orders=queued'],
                            'explanation' => 'A durable queue turns a spike into a backlog. '
                                .'Synchronous processing turns it into timeouts, and shedding load '
                                .'drops the orders the requirement says must not be dropped.',
                        ],
                        [
                            'requirement' => 'no_overselling',
                            'all_of' => ['stock=atomic'],
                            'explanation' => 'Read-then-write is the classic race: both requests '
                                .'read one, both write zero, two customers own the last item. A '
                                .'cached counter has the same race with a longer window.',
                        ],
                        [
                            'requirement' => 'email_not_blocking',
                            'all_of' => ['email=background'],
                            'explanation' => 'An inline send couples checkout to a mail provider — '
                                .'when the provider is slow, checkout is slow.',
                        ],
                    ],
                    'reference' => ['orders' => 'queued', 'stock' => 'atomic', 'email' => 'background'],
                    'pass_mark' => 1.0,
                ],
                'explanation' => 'Queueing absorbs the spike, but it does not solve overselling — '
                    .'a queue serialises intake, not the stock decision, and workers run in '
                    .'parallel behind it. The atomicity has to live where the contention is.',
            ],
            [
                'slug' => 'analytics-pipeline-lossy',
                'title' => 'Analytics that nobody dies for',
                'description' => 'Ten million events an hour, and a dashboard nobody reads at 3am.',
                'objective' => 'Design a system that meets every requirement.',
                'difficulty' => 'medium',
                'type' => 'design',
                'points' => 150,
                'estimated_minutes' => 7,
                'tags' => ['pipelines', 'scaling', 'trade-offs'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'scenario' => 'Page-view analytics. Ten million events an hour. The dashboard '
                        .'is read a few times a day and nobody makes a decision on the last five '
                        .'minutes of data. Losing a fraction of a percent of events changes no '
                        .'conclusion. The budget is small and shrinking.',
                    'requirements' => [
                        ['key' => 'ingest_rate', 'text' => 'Ingest ten million events an hour'],
                        ['key' => 'cheap_reads', 'text' => 'Serve the dashboard without scanning raw events'],
                        ['key' => 'proportionate_cost', 'text' => 'Spend nothing on guarantees the data does not need'],
                    ],
                    'slots' => [
                        [
                            'key' => 'ingest',
                            'label' => 'Ingest',
                            'options' => [
                                ['key' => 'per_event_insert', 'text' => 'One database insert per event'],
                                ['key' => 'batched', 'text' => 'Buffer in memory and write in batches'],
                                ['key' => 'two_phase', 'text' => 'A two-phase commit across the app and the store'],
                            ],
                        ],
                        [
                            'key' => 'reads',
                            'label' => 'Dashboard reads',
                            'options' => [
                                ['key' => 'scan_raw', 'text' => 'Aggregate raw events on each page load'],
                                ['key' => 'rollups', 'text' => 'Pre-aggregate into hourly rollups'],
                            ],
                        ],
                        [
                            'key' => 'delivery',
                            'label' => 'Delivery guarantee',
                            'hint' => 'Every guarantee above "mostly" has a price.',
                            'options' => [
                                ['key' => 'at_most_once', 'text' => 'At most once: fast, occasionally lossy'],
                                ['key' => 'exactly_once', 'text' => 'Exactly once, with deduplication and acknowledgements'],
                            ],
                        ],
                    ],
                ],
                'solution' => [
                    'rubric' => [
                        [
                            'requirement' => 'ingest_rate',
                            'all_of' => ['ingest=batched'],
                            'explanation' => 'Ten million an hour is roughly three thousand a '
                                .'second. One insert each makes the database the bottleneck; '
                                .'batching turns thousands of round trips into a handful.',
                        ],
                        [
                            'requirement' => 'cheap_reads',
                            'all_of' => ['reads=rollups'],
                            'explanation' => 'Scanning raw events per page load makes the cheapest '
                                .'operation in the system its most expensive query.',
                        ],
                        [
                            'requirement' => 'proportionate_cost',
                            'none_of' => ['delivery=exactly_once', 'ingest=two_phase'],
                            'explanation' => 'Exactly-once delivery and two-phase commit are real '
                                .'engineering costs bought to protect data the brief says can be '
                                .'lost by a fraction of a percent without changing a conclusion.',
                        ],
                    ],
                    'reference' => ['ingest' => 'batched', 'reads' => 'rollups', 'delivery' => 'at_most_once'],
                    'pass_mark' => 1.0,
                ],
                'explanation' => 'The hardest judgement in system design is what NOT to guarantee. '
                    .'Exactly-once is the correct answer to a different question — here it is money '
                    .'and latency spent defending page views nobody will miss.',
            ],
            [
                'slug' => 'session-store-across-regions',
                'title' => 'Sessions that survive a region',
                'description' => 'Two regions, one login, and a failover nobody rehearsed.',
                'objective' => 'Design a system that meets every requirement.',
                'difficulty' => 'hard',
                'type' => 'design',
                'points' => 200,
                'estimated_minutes' => 9,
                'tags' => ['availability', 'consistency', 'replication'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'scenario' => 'A web application served from two regions. A user signed in to '
                        .'one region must stay signed in if that region fails. Sessions are small, '
                        .'written on login and read on every request. A session appearing a second '
                        .'or two late in the other region is acceptable; being signed out is not.',
                    'requirements' => [
                        ['key' => 'survives_region_loss', 'text' => 'Keep users signed in when a region fails'],
                        ['key' => 'read_latency', 'text' => 'Read the session without crossing regions'],
                        ['key' => 'no_false_availability', 'text' => 'Do not claim availability that a single node cannot provide'],
                    ],
                    'slots' => [
                        [
                            'key' => 'placement',
                            'label' => 'Where sessions live',
                            'options' => [
                                ['key' => 'sticky_local', 'text' => 'In the region that created them, with sticky routing'],
                                ['key' => 'replicated', 'text' => 'Replicated asynchronously to both regions'],
                                ['key' => 'single_region', 'text' => 'In one primary region, read across the link'],
                            ],
                        ],
                        [
                            'key' => 'consistency',
                            'label' => 'Replication guarantee',
                            'hint' => 'The brief says a second or two of lag is acceptable.',
                            'options' => [
                                ['key' => 'eventual', 'text' => 'Eventually consistent, asynchronous'],
                                ['key' => 'synchronous', 'text' => 'Synchronous: a login is not complete until both regions have it'],
                            ],
                        ],
                        [
                            'key' => 'store',
                            'label' => 'Store',
                            'options' => [
                                ['key' => 'single_node', 'text' => 'A single cache node per region'],
                                ['key' => 'clustered', 'text' => 'A clustered store with a replica per region'],
                            ],
                        ],
                    ],
                ],
                'solution' => [
                    'rubric' => [
                        [
                            'requirement' => 'survives_region_loss',
                            'all_of' => ['placement=replicated'],
                            'explanation' => 'Sticky routing to the region that created the session '
                                .'means the session dies with the region. A single primary read '
                                .'across the link fails for the same reason when the primary is the '
                                .'one that went.',
                        ],
                        [
                            'requirement' => 'read_latency',
                            'none_of' => ['placement=single_region', 'consistency=synchronous'],
                            'explanation' => 'A cross-region read on every request pays the link '
                                .'latency forever. Synchronous replication pays it on every login '
                                .'to buy a guarantee the brief explicitly does not want.',
                        ],
                        [
                            'requirement' => 'no_false_availability',
                            'all_of' => ['store=clustered'],
                            'explanation' => 'A single node per region is a single point of failure '
                                .'inside a design whose entire purpose is surviving failure.',
                        ],
                    ],
                    'reference' => ['placement' => 'replicated', 'consistency' => 'eventual', 'store' => 'clustered'],
                    'pass_mark' => 1.0,
                ],
                'explanation' => 'The brief hands you the CAP trade-off in one sentence: a second '
                    .'of lag is fine, being signed out is not. That is a choice of availability '
                    .'over consistency, and every synchronous option is you overruling the brief.',
            ],
            [
                'slug' => 'notification-fanout',
                'title' => 'One post, four hundred thousand followers',
                'description' => 'Fan-out is a choice between paying on write and paying on read.',
                'objective' => 'Design a system that meets every requirement.',
                'difficulty' => 'hard',
                'type' => 'design',
                'points' => 200,
                'estimated_minutes' => 9,
                'tags' => ['fanout', 'queues', 'scaling'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'scenario' => 'A social feed. Most accounts have a few hundred followers; a '
                        .'handful have hundreds of thousands. When someone posts, their followers '
                        .'should see it within a minute or so. Opening the app must be fast — that '
                        .'is the thing users notice.',
                    'requirements' => [
                        ['key' => 'fast_feed_open', 'text' => 'Open the feed without computing it from scratch'],
                        ['key' => 'celebrity_posts', 'text' => 'Let an account with 400,000 followers post without stalling'],
                        ['key' => 'no_request_blocking', 'text' => 'Never make the posting user wait for fan-out'],
                    ],
                    'slots' => [
                        [
                            'key' => 'strategy',
                            'label' => 'Fan-out strategy',
                            'hint' => 'On write you pay per follower. On read you pay per open.',
                            'options' => [
                                ['key' => 'on_read', 'text' => 'Fan-out on read: assemble each feed when it is opened'],
                                ['key' => 'on_write', 'text' => 'Fan-out on write: push into every follower feed'],
                                ['key' => 'hybrid', 'text' => 'Fan-out on write, except for large accounts, which are merged on read'],
                            ],
                        ],
                        [
                            'key' => 'execution',
                            'label' => 'When fan-out runs',
                            'options' => [
                                ['key' => 'in_request', 'text' => 'During the post request'],
                                ['key' => 'workers', 'text' => 'On background workers, from a queue'],
                            ],
                        ],
                        [
                            'key' => 'feed_store',
                            'label' => 'Feed storage',
                            'options' => [
                                ['key' => 'materialised', 'text' => 'A materialised list per user'],
                                ['key' => 'query_time', 'text' => 'No stored feed; query posts by followed accounts each time'],
                            ],
                        ],
                    ],
                ],
                'solution' => [
                    'rubric' => [
                        [
                            'requirement' => 'fast_feed_open',
                            'all_of' => ['feed_store=materialised'],
                            'explanation' => 'A stored feed is a read of one list. Querying by '
                                .'followed accounts on every open is the expensive path, and it is '
                                .'the path taken most often.',
                        ],
                        [
                            'requirement' => 'celebrity_posts',
                            'all_of' => ['strategy=hybrid'],
                            'explanation' => 'Pure fan-out on write means 400,000 list writes for '
                                .'one post. Pure fan-out on read punishes every ordinary user for '
                                .'the existence of a few large accounts. The hybrid pays per '
                                .'follower where that is cheap and merges where it is not.',
                        ],
                        [
                            'requirement' => 'no_request_blocking',
                            'all_of' => ['execution=workers'],
                            'explanation' => 'Fan-out in the request ties the poster to the size of '
                                .'their own audience — the more followers you have, the slower '
                                .'posting gets.',
                        ],
                    ],
                    'reference' => ['strategy' => 'hybrid', 'execution' => 'workers', 'feed_store' => 'materialised'],
                    'pass_mark' => 1.0,
                ],
                'explanation' => 'Fan-out has no right answer, only a right answer per account '
                    .'size — which is why every large feed converges on the hybrid. The tell is a '
                    .'distribution with a long tail: whenever the average and the maximum differ by '
                    .'orders of magnitude, one strategy will not serve both.',
            ],
            [
                'slug' => 'internal-tool-overengineered',
                'title' => 'Forty people and a spreadsheet',
                'description' => 'The requirement most often failed is the one that says "do less".',
                'objective' => 'Design a system that meets every requirement.',
                'difficulty' => 'easy',
                'type' => 'design',
                'points' => 100,
                'estimated_minutes' => 5,
                'tags' => ['trade-offs', 'yagni', 'architecture'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'scenario' => 'An internal tool for booking meeting rooms. Forty employees, '
                        .'perhaps two hundred bookings a week, all in one office and one timezone. '
                        .'One engineer maintains it, part time. It must not lose a booking, and it '
                        .'must still work in three years when that engineer has moved on.',
                    'requirements' => [
                        ['key' => 'durable_bookings', 'text' => 'Never lose a booking'],
                        ['key' => 'maintainable', 'text' => 'Stay maintainable by one part-time engineer'],
                        ['key' => 'no_premature_scale', 'text' => 'Add nothing the load does not justify'],
                    ],
                    'slots' => [
                        [
                            'key' => 'shape',
                            'label' => 'Application shape',
                            'options' => [
                                ['key' => 'monolith', 'text' => 'A single application with a relational database'],
                                ['key' => 'microservices', 'text' => 'Separate booking, notification and user services'],
                            ],
                        ],
                        [
                            'key' => 'storage',
                            'label' => 'Storage',
                            'options' => [
                                ['key' => 'managed_sql', 'text' => 'A managed relational database with backups'],
                                ['key' => 'sharded', 'text' => 'A sharded cluster, partitioned by office'],
                                ['key' => 'files', 'text' => 'JSON files on the application server'],
                            ],
                        ],
                        [
                            'key' => 'infra',
                            'label' => 'Deployment',
                            'options' => [
                                ['key' => 'single_service', 'text' => 'One deployed service behind a load balancer'],
                                ['key' => 'kubernetes', 'text' => 'A Kubernetes cluster with autoscaling'],
                            ],
                        ],
                    ],
                ],
                'solution' => [
                    'rubric' => [
                        [
                            'requirement' => 'durable_bookings',
                            'all_of' => ['storage=managed_sql'],
                            'explanation' => 'JSON files on the application server have no backup '
                                .'story and no concurrent-write story. Managed SQL gives both '
                                .'without anybody having to think about it.',
                        ],
                        [
                            'requirement' => 'maintainable',
                            'none_of' => ['shape=microservices', 'infra=kubernetes'],
                            'explanation' => 'Three services and a Kubernetes cluster is a full-time '
                                .'operations job attached to a tool used two hundred times a week. '
                                .'The engineer who inherits it will not have that time.',
                        ],
                        [
                            'requirement' => 'no_premature_scale',
                            'none_of' => ['storage=sharded'],
                            'explanation' => 'Sharding by office, for one office, is a partition key '
                                .'with one value — all of the complexity and none of the benefit.',
                        ],
                    ],
                    'reference' => ['shape' => 'monolith', 'storage' => 'managed_sql', 'infra' => 'single_service'],
                    'pass_mark' => 1.0,
                ],
                'explanation' => 'Every option here is defensible somewhere, which is what makes it '
                    .'a trap. Two hundred bookings a week is roughly one every twenty minutes '
                    .'during office hours — a load a single small server would not notice. The '
                    .'skill being tested is reading the numbers before reaching for the pattern.',
            ],
            [
                'slug' => 'payments-that-must-not-charge-twice',
                'title' => 'The retry that costs a customer twice',
                'description' => 'A client times out, retries, and the card is charged again.',
                'objective' => 'Design a system that meets every requirement.',
                'difficulty' => 'expert',
                'type' => 'design',
                'points' => 500,
                'estimated_minutes' => 14,
                'tags' => ['distributed-systems', 'idempotency', 'consistency', 'payments'],
                'status' => Challenge::STATUS_PUBLISHED,
                'published_at' => now(),
                'version' => 1,
                'configuration' => [
                    'scenario' => 'A payment API. Mobile clients on poor connections time out and '
                        .'retry automatically - the request often succeeded, and the client has no '
                        .'way to know. A customer must never be charged twice, a retry must return '
                        .'the ORIGINAL outcome rather than starting a new payment, and a client that '
                        .'reads the payment back immediately must see it.',
                    'requirements' => [
                        ['key' => 'no_double_charge', 'text' => 'Never charge a customer twice for one payment'],
                        ['key' => 'retry_returns_original', 'text' => 'A retry returns the original outcome, not a new one'],
                        ['key' => 'read_your_writes', 'text' => 'A client reading the payment back immediately sees it'],
                        ['key' => 'no_guessing', 'text' => 'Do not rely on guesses about timing or clocks'],
                    ],
                    'slots' => [
                        [
                            'key' => 'dedupe',
                            'label' => 'Duplicate detection',
                            'hint' => 'The client cannot tell a lost response from a failed request.',
                            'options' => [
                                ['key' => 'none', 'text' => 'None: treat every request as new'],
                                ['key' => 'timestamp_window', 'text' => 'Reject a charge identical to one seen in the last 60 seconds'],
                                ['key' => 'idempotency_key', 'text' => 'The client sends a key; the outcome is stored against it and replayed'],
                            ],
                        ],
                        [
                            'key' => 'read_path',
                            'label' => 'Reading a payment back',
                            'options' => [
                                ['key' => 'replica', 'text' => 'From a read replica'],
                                ['key' => 'primary', 'text' => 'From the primary'],
                            ],
                        ],
                        [
                            'key' => 'settlement',
                            'label' => 'When the card is charged',
                            'options' => [
                                ['key' => 'synchronous', 'text' => 'During the request, before responding'],
                                ['key' => 'queued_no_key', 'text' => 'Queued, with the job carrying no client key'],
                            ],
                        ],
                    ],
                ],
                'solution' => [
                    'rubric' => [
                        [
                            'requirement' => 'no_double_charge',
                            'all_of' => ['dedupe=idempotency_key'],
                            'explanation' => 'Only a key the CLIENT chooses survives its own retry. '
                                .'Anything the server derives is derived again on the second request.',
                        ],
                        [
                            'requirement' => 'retry_returns_original',
                            'none_of' => ['settlement=queued_no_key'],
                            'explanation' => 'A job carrying no key cannot be matched to the retry, '
                                .'so the retry enqueues a second charge and the client is told '
                                .'nothing about the first.',
                        ],
                        [
                            'requirement' => 'read_your_writes',
                            'all_of' => ['read_path=primary'],
                            'explanation' => 'Replication lag is measured in milliseconds and a '
                                .'retry arrives in milliseconds. Reading a just-written payment '
                                .'from a replica is the one read guaranteed to race.',
                        ],
                        [
                            'requirement' => 'no_guessing',
                            'none_of' => ['dedupe=timestamp_window', 'dedupe=none'],
                            'explanation' => 'A time window encodes a guess about clock skew, retry '
                                .'delay and queue depth. It fails silently when any of the three '
                                .'changes, and it fails towards charging twice.',
                        ],
                    ],
                    'reference' => ['dedupe' => 'idempotency_key', 'read_path' => 'primary', 'settlement' => 'synchronous'],
                    'pass_mark' => 1.0,
                ],
                'explanation' => 'Exactly-once delivery does not exist. What exists is at-least-once '
                    .'delivery plus an idempotent receiver, and the idempotency key is how the '
                    ."receiver recognises the repeat.\n\n"
                    .'The key has to come from the client, because the client is the only party that '
                    .'knows its retry is a retry. A server-side fingerprint - amount, customer, '
                    .'timestamp - is recomputed identically for a genuine second purchase of the '
                    ."same thing, and refusing that is its own bug.\n\n"
                    .'The read path is the half people miss. You can get deduplication perfectly '
                    .'right and still fail, because the retry reads from a replica that has not '
                    .'caught up, concludes no payment exists, and starts one.',
            ],
        ];
    }
}
