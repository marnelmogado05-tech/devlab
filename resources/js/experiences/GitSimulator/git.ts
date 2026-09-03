/**
 * A Git model for the browser.
 *
 * This is the second implementation of the rules in
 * `app/Services/Challenge/GitSimulator/GitSimulation.php`, and ADR 0006 records
 * why it exists twice. The short version: the player has to SEE the graph change
 * as they type, and law 1 says the server is the only authority on whether they
 * solved it.
 *
 * So this decides nothing. It draws what the player typed. The command sequence
 * goes to the server, which replays it against its own model and returns the
 * verdict. Drift between the two degrades the picture, never the grading — a
 * player seeing a wrong graph gets a wrong answer they can see the reason for,
 * rather than a right answer silently rejected.
 *
 * Written against the same contract document as the PHP model, in the same order,
 * so the two read as translations of each other.
 */

export interface Commit {
    id: string;
    message: string;
    parents: string[];
}

export interface RepositoryState {
    commits: Commit[];
    branches: Record<string, string>;
    head: string;
}

export const COMMANDS = [
    'commit',
    'branch',
    'checkout',
    'switch',
    'merge',
    'reset',
    'revert',
    'cherry-pick',
    'rebase',
] as const;

export class Repository {
    private commits = new Map<string, Commit>();
    private branchMap = new Map<string, string>();
    private branch: string | null = null;
    private detachedAt: string | null = null;
    private sequence = 0;

    static from(state: RepositoryState): Repository {
        const repository = new Repository();

        for (const commit of state.commits) {
            repository.commits.set(commit.id, {
                id: commit.id,
                message: commit.message,
                parents: [...(commit.parents ?? [])],
            });
        }

        for (const [name, at] of Object.entries(state.branches)) {
            repository.branchMap.set(name, at);
        }

        if (repository.branchMap.has(state.head)) {
            repository.branch = state.head;
        } else {
            repository.detachedAt = state.head;
        }

        return repository;
    }

    clone(): Repository {
        const copy = new Repository();

        for (const [id, commit] of this.commits) {
            copy.commits.set(id, { ...commit, parents: [...commit.parents] });
        }

        copy.branchMap = new Map(this.branchMap);
        copy.branch = this.branch;
        copy.detachedAt = this.detachedAt;
        copy.sequence = this.sequence;

        return copy;
    }

    head(): string | null {
        return this.branch !== null
            ? (this.branchMap.get(this.branch) ?? null)
            : this.detachedAt;
    }

    currentBranch(): string | null {
        return this.branch;
    }

    isDetached(): boolean {
        return this.branch === null;
    }

    allCommits(): Commit[] {
        return [...this.commits.values()];
    }

    branches(): Record<string, string> {
        return Object.fromEntries(this.branchMap);
    }

    /** Branch names pointing at a commit, for labelling the graph. */
    labelsAt(id: string): string[] {
        return [...this.branchMap.entries()]
            .filter(([, at]) => at === id)
            .map(([name]) => name);
    }

    private resolve(reference: string): string | null {
        if (reference === 'HEAD') {
            return this.head();
        }

        return (
            this.branchMap.get(reference) ??
            (this.commits.has(reference) ? reference : null)
        );
    }

    private ancestry(from: string | null): string[] {
        if (from === null) {
            return [];
        }

        const seen = new Set<string>();
        const queue = [from];

        while (queue.length > 0) {
            const id = queue.shift();

            if (id === undefined || seen.has(id) || !this.commits.has(id)) {
                continue;
            }

            seen.add(id);
            queue.push(...(this.commits.get(id)?.parents ?? []));
        }

        return [...seen];
    }

    private isAncestor(candidate: string, of: string | null): boolean {
        return this.ancestry(of).includes(candidate);
    }

    private newCommit(message: string, parents: string[]): string {
        const id = `n${++this.sequence}`;

        this.commits.set(id, { id, message, parents });

        return id;
    }

    private moveHead(commit: string): void {
        if (this.branch !== null) {
            this.branchMap.set(this.branch, commit);

            return;
        }

        this.detachedAt = commit;
    }

    /**
     * Apply one command. Returns the reason it was refused, or null.
     *
     * Never throws: the input is a player's typing, and an exception on a typo
     * is a worse experience than being told the command is not understood.
     */
    apply(command: string, allowed?: string[]): string | null {
        const tokens = tokenise(command);

        if (tokens.length === 0) {
            return 'Empty command.';
        }

        if (tokens.shift() !== 'git') {
            return 'Commands must start with "git".';
        }

        const name = tokens.shift() ?? '';

        if (!(COMMANDS as readonly string[]).includes(name)) {
            return `This simulator does not implement 'git ${name}'.`;
        }

        if (allowed && !allowed.includes(name)) {
            return `This challenge does not allow 'git ${name}'.`;
        }

        switch (name) {
            case 'commit':
                return this.commit(tokens);
            case 'branch':
                return this.branchCommand(tokens);
            case 'checkout':
            case 'switch':
                return this.checkout(tokens);
            case 'merge':
                return this.merge(tokens);
            case 'reset':
                return this.reset(tokens);
            case 'revert':
                return this.revert(tokens);
            case 'cherry-pick':
                return this.cherryPick(tokens);
            case 'rebase':
                return this.rebase(tokens);
            default:
                return `This simulator does not implement 'git ${name}'.`;
        }
    }

    private commit(tokens: string[]): string | null {
        const message = flagValue(tokens, ['-m', '--message']);

        if (!message) {
            return 'git commit needs a message: git commit -m "..."';
        }

        const head = this.head();

        this.moveHead(this.newCommit(message, head === null ? [] : [head]));

        return null;
    }

    private branchCommand(tokens: string[]): string | null {
        const remove = tokens.includes('-d') || tokens.includes('-D');
        const names = operands(tokens);

        if (names.length === 0) {
            return 'git branch needs a name.';
        }

        const name = names[0];

        if (remove) {
            if (!this.branchMap.has(name)) {
                return `There is no branch '${name}'.`;
            }

            if (name === this.branch) {
                return `Cannot delete '${name}': it is the branch you are on.`;
            }

            this.branchMap.delete(name);

            return null;
        }

        if (this.branchMap.has(name)) {
            return `Branch '${name}' already exists.`;
        }

        // `git branch <name> <start-point>` branches from somewhere other than
        // HEAD, which matters because checking out first changes where the
        // player ends up.
        const at = names[1] ? this.resolve(names[1]) : this.head();

        if (at === null) {
            return names[1]
                ? `There is no branch or commit called '${names[1]}'.`
                : 'There is nothing to branch from yet.';
        }

        this.branchMap.set(name, at);

        return null;
    }

    private checkout(tokens: string[]): string | null {
        const create = tokens.includes('-b') || tokens.includes('-c');
        const targets = operands(tokens);

        if (targets.length === 0) {
            return 'git checkout needs a branch or commit.';
        }

        const target = targets[0];

        if (create) {
            if (this.branchMap.has(target)) {
                return `Branch '${target}' already exists.`;
            }

            const at = targets[1] ? this.resolve(targets[1]) : this.head();

            if (at === null) {
                return targets[1]
                    ? `There is no branch or commit called '${targets[1]}'.`
                    : 'There is nothing to branch from yet.';
            }

            this.branchMap.set(target, at);
            this.branch = target;
            this.detachedAt = null;

            return null;
        }

        if (this.branchMap.has(target)) {
            this.branch = target;
            this.detachedAt = null;

            return null;
        }

        if (this.commits.has(target)) {
            this.branch = null;
            this.detachedAt = target;

            return null;
        }

        return `There is no branch or commit called '${target}'.`;
    }

    private merge(tokens: string[]): string | null {
        const targets = operands(tokens);

        if (targets.length === 0) {
            return 'git merge needs something to merge.';
        }

        const target = this.resolve(targets[0]);
        const head = this.head();

        if (target === null) {
            return `There is no branch or commit called '${targets[0]}'.`;
        }

        if (head === null) {
            return 'There is nothing to merge into yet.';
        }

        if (this.isAncestor(target, head)) {
            return `Already up to date: '${targets[0]}' is already in this history.`;
        }

        if (this.isAncestor(head, target)) {
            this.moveHead(target);

            return null;
        }

        this.moveHead(
            this.newCommit(`Merge branch '${targets[0]}'`, [head, target]),
        );

        return null;
    }

    private reset(tokens: string[]): string | null {
        const targets = operands(tokens);

        if (targets.length === 0) {
            return 'git reset needs a target: git reset --hard <ref>';
        }

        const target = this.resolve(targets[0]);

        if (target === null) {
            return `There is no branch or commit called '${targets[0]}'.`;
        }

        // --soft, --mixed and --hard all move the pointer here: this model has
        // no working tree or index for them to differ over (ADR 0006).
        this.moveHead(target);

        return null;
    }

    private revert(tokens: string[]): string | null {
        const targets = operands(tokens);

        if (targets.length === 0) {
            return 'git revert needs a commit.';
        }

        const target = this.resolve(targets[0]);
        const head = this.head();

        if (target === null) {
            return `There is no branch or commit called '${targets[0]}'.`;
        }

        if (head === null) {
            return 'There is nothing to revert onto yet.';
        }

        const message = this.commits.get(target)?.message ?? '';

        this.moveHead(this.newCommit(`Revert "${message}"`, [head]));

        return null;
    }

    private cherryPick(tokens: string[]): string | null {
        const targets = operands(tokens);

        if (targets.length === 0) {
            return 'git cherry-pick needs a commit.';
        }

        const target = this.resolve(targets[0]);
        const head = this.head();

        if (target === null) {
            return `There is no branch or commit called '${targets[0]}'.`;
        }

        if (head === null) {
            return 'There is nothing to cherry-pick onto yet.';
        }

        this.moveHead(
            this.newCommit(this.commits.get(target)?.message ?? '', [head]),
        );

        return null;
    }

    private rebase(tokens: string[]): string | null {
        const targets = operands(tokens);

        if (targets.length === 0) {
            return 'git rebase needs something to rebase onto.';
        }

        const onto = this.resolve(targets[0]);
        const head = this.head();

        if (onto === null) {
            return `There is no branch or commit called '${targets[0]}'.`;
        }

        if (head === null) {
            return 'There is nothing to rebase yet.';
        }

        if (this.isAncestor(head, onto)) {
            this.moveHead(onto);

            return null;
        }

        if (this.isAncestor(onto, head)) {
            return `Already up to date: '${targets[0]}' is already in this history.`;
        }

        const ontoAncestry = new Set(this.ancestry(onto));
        const unique = this.ancestry(head).filter(
            (id) => !ontoAncestry.has(id),
        );

        if (
            unique.length > 1 &&
            unique.some((id) => (this.commits.get(id)?.parents.length ?? 0) > 1)
        ) {
            return 'This simulator cannot rebase a history that contains a merge.';
        }

        // Oldest first: ancestry walks parents, so its natural order is
        // newest-first and replaying that way would invert the history.
        let base = onto;

        for (const id of [...unique].reverse()) {
            base = this.newCommit(this.commits.get(id)?.message ?? '', [base]);
        }

        this.moveHead(base);

        return null;
    }
}

/**
 * Split on whitespace, keeping quoted strings together.
 *
 * Written out rather than done with a regex because commit messages are the one
 * place a player types free text, and a message that loses its spaces produces a
 * different commit — a wrong answer with no visible cause.
 */
export function tokenise(command: string): string[] {
    const tokens: string[] = [];
    let current = '';
    let quote: string | null = null;
    let started = false;

    for (const character of command.trim()) {
        if (quote !== null) {
            if (character === quote) {
                quote = null;
            } else {
                current += character;
            }

            continue;
        }

        if (character === '"' || character === "'") {
            quote = character;
            started = true;

            continue;
        }

        if (character === ' ' || character === '\t') {
            if (current !== '' || started) {
                tokens.push(current);
                current = '';
                started = false;
            }

            continue;
        }

        current += character;
    }

    if (current !== '' || started) {
        tokens.push(current);
    }

    return tokens;
}

function flagValue(tokens: string[], flags: string[]): string | null {
    for (const [index, token] of tokens.entries()) {
        if (flags.includes(token)) {
            return tokens[index + 1] ?? null;
        }

        for (const flag of flags) {
            if (token.startsWith(`${flag}=`)) {
                return token.slice(flag.length + 1);
            }
        }
    }

    return null;
}

function operands(tokens: string[]): string[] {
    return tokens.filter((token) => token !== '' && !token.startsWith('-'));
}

/**
 * Replay a whole sequence from a starting state.
 *
 * Stops at the first command that will not apply, the way the server does: Git
 * is stateful, and every command after a failed one was typed against a
 * repository that never existed.
 */
export function replay(
    state: RepositoryState,
    commands: string[],
    allowed?: string[],
): { repository: Repository; applied: number; error: string | null } {
    const repository = Repository.from(state);

    for (const [index, command] of commands.entries()) {
        const error = repository.apply(command, allowed);

        if (error !== null) {
            return { repository, applied: index, error };
        }
    }

    return { repository, applied: commands.length, error: null };
}
