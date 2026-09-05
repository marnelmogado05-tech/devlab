<?php

namespace App\Services\Challenge;

/**
 * Which platform capabilities each experience is allowed to reach for.
 *
 * A capability is something an experience needs FROM the platform that most
 * experiences do not: today, running the player's code. Five of the six
 * playable experiences evaluate a value the client sent and want nothing from
 * the execution engine at all.
 *
 * This exists because Code Arena's need for execution was expressed as a
 * concrete type-hint in generic classes and as no check whatsoever on the route
 * that spends the budget — so any open attempt, from any experience, could
 * queue containers. Naming the capability is what lets the platform refuse.
 *
 * The first piece of the `ExperienceDefinition` described in
 * {@see docs/adr/0009-experiences-declare-themselves.md}, landed on its own
 * because the hole it closes is a spending one. When the definition arrives it
 * absorbs this registry rather than sitting beside it: the shape here — slug
 * plus a declared set, registered in a provider — is the shape it will have.
 *
 * Registration happens in a service provider, not here, so adding an experience
 * never means editing this class. The default is DENY: an experience that has
 * not declared a capability does not have it, which is the right way round for
 * a gate on compute spend.
 */
class ExperienceCapabilities
{
    /**
     * Runs player-submitted code through the sandbox (ADR 0007, ADR 0008).
     *
     * Every cost the execution engine can incur — a queue job, a container, a
     * run from the attempt's budget — is downstream of this one string.
     */
    public const EXECUTION = 'execution';

    /** @var array<string, array<int, string>> */
    private array $capabilities = [];

    /**
     * @param  array<int, string>  $capabilities
     */
    public function register(string $experienceSlug, array $capabilities): void
    {
        $this->capabilities[$experienceSlug] = $capabilities;
    }

    public function has(string $experienceSlug, string $capability): bool
    {
        return in_array($capability, $this->capabilities[$experienceSlug] ?? [], strict: true);
    }
}
