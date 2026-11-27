import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

/**
 * Frontend Container & Release Audit Test (Audit 496 Parameter SUS - Issue #182).
 */
describe('ContainerAndReleaseDeploymentAudit - Frontend', () => {
  it('verifies frontend Dockerfile is multi-stage and uses non-root user', () => {
    const dockerfilePath = path.resolve(__dirname, '../../Dockerfile');
    expect(fs.existsSync(dockerfilePath)).toBe(true);

    const content = fs.readFileSync(dockerfilePath, 'utf-8');

    // Multi-stage verification (deps, builder, runner)
    const fromMatches = content.match(/FROM\s+/g);
    expect(fromMatches && fromMatches.length).toBeGreaterThanOrEqual(2);

    expect(content).toMatch(/AS\s+deps/i);
    expect(content).toMatch(/AS\s+builder/i);
    expect(content).toMatch(/AS\s+runner/i);

    // Non-root user verification
    expect(content).toMatch(/USER\s+nextjs/);
  });
});
