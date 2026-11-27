import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

describe('Node & Dependency Locking Audit (Audit 496 Parameter SUS - Issue #183)', () => {
  const rootDir = path.resolve(__dirname, '../../..');
  const frontendDir = path.resolve(rootDir, 'frontend');
  const ciWorkflowPath = path.resolve(rootDir, '.github/workflows/ci.yml');

  it('pins Node version in .nvmrc and .node-version matching CI (20)', () => {
    expect(fs.existsSync(ciWorkflowPath)).toBe(true);
    const ciContent = fs.readFileSync(ciWorkflowPath, 'utf8');
    const ciMatch = ciContent.match(/node-version:\s*(\d+)/);
    const ciNodeVersion = ciMatch ? ciMatch[1] : '20';

    // Root .nvmrc and .node-version
    const rootNvmrcPath = path.resolve(rootDir, '.nvmrc');
    const rootNodeVersionPath = path.resolve(rootDir, '.node-version');
    expect(fs.existsSync(rootNvmrcPath)).toBe(true);
    expect(fs.existsSync(rootNodeVersionPath)).toBe(true);
    expect(fs.readFileSync(rootNvmrcPath, 'utf8').trim()).toBe(ciNodeVersion);
    expect(fs.readFileSync(rootNodeVersionPath, 'utf8').trim()).toBe(ciNodeVersion);

    // Frontend .nvmrc and .node-version
    const frontendNvmrcPath = path.resolve(frontendDir, '.nvmrc');
    const frontendNodeVersionPath = path.resolve(frontendDir, '.node-version');
    expect(fs.existsSync(frontendNvmrcPath)).toBe(true);
    expect(fs.existsSync(frontendNodeVersionPath)).toBe(true);
    expect(fs.readFileSync(frontendNvmrcPath, 'utf8').trim()).toBe(ciNodeVersion);
    expect(fs.readFileSync(frontendNodeVersionPath, 'utf8').trim()).toBe(ciNodeVersion);
  });

  it('defines engines.node matching CI in root and frontend package.json', () => {
    const ciContent = fs.readFileSync(ciWorkflowPath, 'utf8');
    const ciMatch = ciContent.match(/node-version:\s*(\d+)/);
    const ciNodeVersion = ciMatch ? ciMatch[1] : '20';

    // Root package.json
    const rootPkg = JSON.parse(fs.readFileSync(path.resolve(rootDir, 'package.json'), 'utf8'));
    expect(rootPkg.engines).toBeDefined();
    expect(rootPkg.engines.node).toBe(ciNodeVersion);

    // Frontend package.json
    const frontendPkg = JSON.parse(fs.readFileSync(path.resolve(frontendDir, 'package.json'), 'utf8'));
    expect(frontendPkg.engines).toBeDefined();
    expect(frontendPkg.engines.node).toBe(ciNodeVersion);

    // Root package-lock.json
    const rootLock = JSON.parse(fs.readFileSync(path.resolve(rootDir, 'package-lock.json'), 'utf8'));
    expect(rootLock.packages[''].engines.node).toBe(ciNodeVersion);

    // Frontend package-lock.json
    const frontendLock = JSON.parse(fs.readFileSync(path.resolve(frontendDir, 'package-lock.json'), 'utf8'));
    expect(frontendLock.packages[''].engines.node).toBe(ciNodeVersion);
  });

  it('verifies lockfiles exist and lock dependencies', () => {
    const rootLockPath = path.resolve(rootDir, 'package-lock.json');
    const frontendLockPath = path.resolve(frontendDir, 'package-lock.json');
    const composerLockPath = path.resolve(rootDir, 'backend/composer.lock');

    expect(fs.existsSync(rootLockPath)).toBe(true);
    expect(fs.existsSync(frontendLockPath)).toBe(true);
    expect(fs.existsSync(composerLockPath)).toBe(true);

    const rootLock = JSON.parse(fs.readFileSync(rootLockPath, 'utf8'));
    const frontendLock = JSON.parse(fs.readFileSync(frontendLockPath, 'utf8'));
    const composerLock = JSON.parse(fs.readFileSync(composerLockPath, 'utf8'));

    expect(rootLock.packages).toBeDefined();
    expect(frontendLock.packages).toBeDefined();
    expect(composerLock.packages).toBeDefined();
    expect(Array.isArray(composerLock.packages)).toBe(true);
    expect(composerLock.packages.length).toBeGreaterThan(0);
  });

  it('confirms audit evidence returns "ada & sama dengan CI"', () => {
    const ciContent = fs.readFileSync(ciWorkflowPath, 'utf8');
    const ciMatch = ciContent.match(/node-version:\s*(\d+)/);
    const ciNodeVersion = ciMatch ? ciMatch[1] : '20';

    const rootNvmrcPath = path.resolve(rootDir, '.nvmrc');
    const rootPkg = JSON.parse(fs.readFileSync(path.resolve(rootDir, 'package.json'), 'utf8'));

    const pin = fs.existsSync(rootNvmrcPath) ? fs.readFileSync(rootNvmrcPath, 'utf8').trim() : '-';
    const enginesNode = rootPkg.engines?.node ?? '-';

    const status = pin !== '-' && enginesNode !== '-' && pin === ciNodeVersion && enginesNode === ciNodeVersion
      ? 'ada & sama dengan CI'
      : 'tidak lulus';

    expect(pin).toBe(ciNodeVersion);
    expect(enginesNode).toBe(ciNodeVersion);
    expect(status).toBe('ada & sama dengan CI');
  });
});
