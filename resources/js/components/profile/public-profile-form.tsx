import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/public-profile';

export interface PublicProfile {
    username: string | null;
    display_name: string | null;
    bio: string | null;
    location: string | null;
    website: string | null;
    github_handle: string | null;
    is_public: boolean;
    preferred_difficulty: string | null;
    technologies: string[];
}

/**
 * The public identity, edited separately from the account it belongs to.
 *
 * The preference fields are not decoration: "I'm Bored" already reads
 * `preferences.difficulty` and `preferences.technologies` when weighting the
 * pool, and until now nothing ever wrote them.
 */
export function PublicProfileForm({
    profile,
    difficulties,
}: {
    profile: PublicProfile;
    difficulties: string[];
}) {
    return (
        <Form
            action={update()}
            method="put"
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <Field
                        name="username"
                        label="Username"
                        hint="Letters, numbers and single hyphens. This is your public handle."
                        defaultValue={profile.username ?? ''}
                        error={errors.username}
                        required
                    />

                    <Field
                        name="display_name"
                        label="Display name"
                        defaultValue={profile.display_name ?? ''}
                        error={errors.display_name}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="bio">Bio</Label>
                        <textarea
                            id="bio"
                            name="bio"
                            rows={3}
                            defaultValue={profile.bio ?? ''}
                            aria-describedby={
                                errors.bio ? 'bio-error' : undefined
                            }
                            aria-invalid={errors.bio ? true : undefined}
                            className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        />
                        {errors.bio && (
                            <p
                                id="bio-error"
                                role="alert"
                                className="text-destructive text-sm"
                            >
                                {errors.bio}
                            </p>
                        )}
                    </div>

                    <Field
                        name="location"
                        label="Location"
                        defaultValue={profile.location ?? ''}
                        error={errors.location}
                    />

                    <Field
                        name="website"
                        label="Website"
                        type="url"
                        placeholder="https://"
                        defaultValue={profile.website ?? ''}
                        error={errors.website}
                    />

                    <Field
                        name="github_handle"
                        label="GitHub handle"
                        defaultValue={profile.github_handle ?? ''}
                        error={errors.github_handle}
                    />

                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            Visibility
                        </legend>
                        <label className="flex items-start gap-3 text-sm">
                            {/*
                             * A hidden companion field, so unchecking actually
                             * submits false rather than omitting the key and
                             * leaving the previous value in place.
                             */}
                            <input type="hidden" name="is_public" value="0" />
                            <input
                                type="checkbox"
                                name="is_public"
                                value="1"
                                defaultChecked={profile.is_public}
                                className="accent-primary mt-1"
                            />
                            <span>
                                Show my activity publicly
                                <span className="text-muted-foreground block text-xs">
                                    Your level and rank stay visible either way
                                    — they already appear on the leaderboard.
                                </span>
                            </span>
                        </label>
                    </fieldset>

                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            What should &quot;I&apos;m Bored&quot; lean towards?
                        </legend>
                        <p className="text-muted-foreground text-xs">
                            Weights, not filters. It will still hand you
                            something unexpected on purpose.
                        </p>

                        <Label htmlFor="preferred_difficulty" className="mt-2">
                            Preferred difficulty
                        </Label>
                        <select
                            id="preferred_difficulty"
                            name="preferred_difficulty"
                            defaultValue={profile.preferred_difficulty ?? ''}
                            className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <option value="">No preference</option>
                            {difficulties.map((difficulty) => (
                                <option key={difficulty} value={difficulty}>
                                    {difficulty}
                                </option>
                            ))}
                        </select>

                        <Label htmlFor="technologies" className="mt-2">
                            Technologies you like
                        </Label>
                        <Input
                            id="technologies"
                            name="technologies"
                            defaultValue={profile.technologies.join(', ')}
                            placeholder="php, javascript, docker"
                            aria-describedby="technologies-hint"
                        />
                        <p
                            id="technologies-hint"
                            className="text-muted-foreground text-xs"
                        >
                            Comma separated. Matched against challenge tags.
                        </p>
                    </fieldset>

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Save profile'}
                    </Button>
                </>
            )}
        </Form>
    );
}

function Field({
    name,
    label,
    hint,
    error,
    ...props
}: {
    name: string;
    label: string;
    hint?: string;
    error?: string;
} & React.InputHTMLAttributes<HTMLInputElement>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                aria-describedby={
                    error ? `${name}-error` : hint ? `${name}-hint` : undefined
                }
                aria-invalid={error ? true : undefined}
                {...props}
            />
            {hint && !error && (
                <p
                    id={`${name}-hint`}
                    className="text-muted-foreground text-xs"
                >
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={`${name}-error`}
                    role="alert"
                    className="text-destructive text-sm"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
