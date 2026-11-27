import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

describe('Backend Testing Behavior & Quality Audit (Audit 496 Parameter TST - Issue #184)', () => {
  const rootDir = path.resolve(__dirname, '../../..');
  const backendTestsDir = path.resolve(rootDir, 'backend/tests');
  const ciWorkflowPath = path.resolve(rootDir, '.github/workflows/ci.yml');

  it('verifies CI workflow configures coverage: pcov and minimum threshold 60%', () => {
    expect(fs.existsSync(ciWorkflowPath)).toBe(true);
    const ciContent = fs.readFileSync(ciWorkflowPath, 'utf8');

    expect(ciContent).toContain('coverage: pcov');
    expect(ciContent).toMatch(/--coverage\s+--min=60/);
  });

  it('verifies 0 assertTrue(true) in backend test suite', () => {
    const testFiles: string[] = [];

    function findTestFiles(dir: string) {
      const entries = fs.readdirSync(dir, { withFileTypes: true });
      for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
          findTestFiles(fullPath);
        } else if (entry.isFile() && entry.name.endsWith('Test.php') && entry.name !== 'BackendTestingAuditTest.php') {
          testFiles.push(fullPath);
        }
      }
    }

    findTestFiles(backendTestsDir);
    expect(testFiles.length).toBeGreaterThan(20);

    const assertTrueMatches: string[] = [];
    for (const file of testFiles) {
      const content = fs.readFileSync(file, 'utf8');
      if (/\$this->assertTrue\s*\(\s*true\s*\)/.test(content)) {
        assertTrueMatches.push(path.basename(file));
      }
    }

    expect(assertTrueMatches).toEqual([]);
    expect(assertTrueMatches.length).toBe(0);
  });

  it('verifies assertJsonValidationErrors is actively used in validation tests', () => {
    let assertJsonValidationErrorsCount = 0;

    function countInDir(dir: string) {
      const entries = fs.readdirSync(dir, { withFileTypes: true });
      for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
          countInDir(fullPath);
        } else if (entry.isFile() && entry.name.endsWith('Test.php')) {
          const content = fs.readFileSync(fullPath, 'utf8');
          const matches = content.match(/assertJsonValidationErrors/g);
          if (matches) {
            assertJsonValidationErrorsCount += matches.length;
          }
        }
      }
    }

    countInDir(backendTestsDir);
    expect(assertJsonValidationErrorsCount).toBeGreaterThanOrEqual(5);
  });

  it('confirms audit standard definition of done returns "0 pelanggaran"', () => {
    const ciContent = fs.readFileSync(ciWorkflowPath, 'utf8');
    const hasPcov = ciContent.includes('coverage: pcov');
    const hasMin60 = /--coverage\s+--min=60/.test(ciContent);

    expect(hasPcov).toBe(true);
    expect(hasMin60).toBe(true);

    const status = hasPcov && hasMin60 ? '0 pelanggaran' : 'ada pelanggaran';
    expect(status).toBe('0 pelanggaran');
  });
});
