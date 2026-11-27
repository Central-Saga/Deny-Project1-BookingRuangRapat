import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

/**
 * Frontend Environment Configuration Audit Test (Audit 496 Parameter SUS - Issue #180).
 */
describe('EnvironmentConfigurationAudit - Frontend', () => {
  it('documents all process.env variables (CI, NEXT_PUBLIC_API_URL, NODE_ENV) in .env.example', () => {
    const envExamplePath = path.resolve(__dirname, '../../.env.example');
    expect(fs.existsSync(envExamplePath)).toBe(true);

    const content = fs.readFileSync(envExamplePath, 'utf-8');
    expect(content).toMatch(/CI=/);
    expect(content).toMatch(/NEXT_PUBLIC_API_URL=/);
    expect(content).toMatch(/NODE_ENV=/);
  });

  it('provides templates for development, production, test, and local environments', () => {
    const requiredFiles = [
      '.env.example',
      '.env.development.example',
      '.env.production.example',
      '.env.test.example',
      '.env.local.example',
    ];

    for (const file of requiredFiles) {
      const filePath = path.resolve(__dirname, '../../', file);
      expect(fs.existsSync(filePath), `File ${file} should exist in frontend`).toBe(true);

      const content = fs.readFileSync(filePath, 'utf-8');
      expect(content).toMatch(/NEXT_PUBLIC_API_URL=/);
    }
  });

  it('configures distinct API URLs for development and production templates', () => {
    const devPath = path.resolve(__dirname, '../../.env.development.example');
    const prodPath = path.resolve(__dirname, '../../.env.production.example');

    const devContent = fs.readFileSync(devPath, 'utf-8');
    const prodContent = fs.readFileSync(prodPath, 'utf-8');

    expect(devContent).toMatch(/localhost/);
    expect(prodContent).toMatch(/https:\/\//);
  });
});
