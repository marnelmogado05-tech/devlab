import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

/*
 * Unmount between tests.
 *
 * happy-dom keeps one document for the whole file, so without this every render
 * accumulates and `getByRole` starts finding two of everything — which fails in
 * a way that looks like a component bug rather than a leaked fixture.
 */
afterEach(() => {
    cleanup();
});
