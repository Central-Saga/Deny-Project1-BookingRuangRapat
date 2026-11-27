<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Node & Dependency Locking Audit Test (Audit 496 Parameter SUS - Issue #183).
 *
 * Menguji:
 * 1. Versi Node di-pin di .nvmrc dan .node-version (root & frontend) sesuai CI (Node 20).
 * 2. engines.node di package.json dan package-lock.json terpasang dan sama dengan CI (Node 20).
 * 3. Lockfile dependensi (package-lock.json, composer.lock) ada dan mengunci versi paket.
 * 4. Verifikasi bukti audit menghasilkan status "ada & sama dengan CI".
 */
class NodeAndDependencyLockingAuditTest extends TestCase
{
    /**
     * Uji 1: Versi Node di-pin dalam .nvmrc dan .node-version sama dengan CI.
     */
    public function test_node_version_is_pinned_in_nvmrc_and_node_version_files_matching_ci(): void
    {
        $ciWorkflowPath = base_path('../.github/workflows/ci.yml');
        $this->assertFileExists($ciWorkflowPath, 'Workflow CI (.github/workflows/ci.yml) harus ada.');

        $ciContent = file_get_contents($ciWorkflowPath);
        $this->assertMatchesRegularExpression('/node-version:\s*(\d+)/', $ciContent, 'CI harus mendefinisikan node-version.');
        preg_match('/node-version:\s*(\d+)/', $ciContent, $matches);
        $ciNodeVersion = $matches[1] ?? '20';

        $rootNvmrc = base_path('../.nvmrc');
        $this->assertFileExists($rootNvmrc, 'File .nvmrc di root proyek harus ada.');
        $this->assertSame($ciNodeVersion, trim(file_get_contents($rootNvmrc)), 'Root .nvmrc harus sesuai dengan versi Node di CI.');

        $rootNodeVersion = base_path('../.node-version');
        $this->assertFileExists($rootNodeVersion, 'File .node-version di root proyek harus ada.');
        $this->assertSame($ciNodeVersion, trim(file_get_contents($rootNodeVersion)), 'Root .node-version harus sesuai dengan versi Node di CI.');

        $frontendNvmrc = base_path('../frontend/.nvmrc');
        $this->assertFileExists($frontendNvmrc, 'File .nvmrc di direktori frontend harus ada.');
        $this->assertSame($ciNodeVersion, trim(file_get_contents($frontendNvmrc)), 'Frontend .nvmrc harus sesuai dengan versi Node di CI.');

        $frontendNodeVersion = base_path('../frontend/.node-version');
        $this->assertFileExists($frontendNodeVersion, 'File .node-version di direktori frontend harus ada.');
        $this->assertSame($ciNodeVersion, trim(file_get_contents($frontendNodeVersion)), 'Frontend .node-version harus sesuai dengan versi Node di CI.');
    }

    /**
     * Uji 2: engines.node di package.json (root dan frontend) sama dengan CI.
     */
    public function test_engines_node_is_configured_in_root_and_frontend_package_json_matching_ci(): void
    {
        $ciWorkflowPath = base_path('../.github/workflows/ci.yml');
        $ciContent = file_get_contents($ciWorkflowPath);
        preg_match('/node-version:\s*(\d+)/', $ciContent, $matches);
        $ciNodeVersion = $matches[1] ?? '20';

        // Root package.json
        $rootPkgPath = base_path('../package.json');
        $this->assertFileExists($rootPkgPath, 'Root package.json harus ada.');
        $rootPkg = json_decode(file_get_contents($rootPkgPath), true);
        $this->assertArrayHasKey('engines', $rootPkg, 'Root package.json harus memiliki field engines.');
        $this->assertArrayHasKey('node', $rootPkg['engines'], 'Root package.json engines harus memiliki node.');
        $this->assertSame($ciNodeVersion, (string) $rootPkg['engines']['node'], 'Root engines.node harus sama dengan CI.');

        // Frontend package.json
        $frontendPkgPath = base_path('../frontend/package.json');
        $this->assertFileExists($frontendPkgPath, 'Frontend package.json harus ada.');
        $frontendPkg = json_decode(file_get_contents($frontendPkgPath), true);
        $this->assertArrayHasKey('engines', $frontendPkg, 'Frontend package.json harus memiliki field engines.');
        $this->assertArrayHasKey('node', $frontendPkg['engines'], 'Frontend package.json engines harus memiliki node.');
        $this->assertSame($ciNodeVersion, (string) $frontendPkg['engines']['node'], 'Frontend engines.node harus sama dengan CI.');

        // Root package-lock.json
        $rootLockPath = base_path('../package-lock.json');
        $this->assertFileExists($rootLockPath, 'Root package-lock.json harus ada.');
        $rootLock = json_decode(file_get_contents($rootLockPath), true);
        $this->assertArrayHasKey('engines', $rootLock['packages'][''] ?? [], 'Root package-lock.json root package harus mendefinisikan engines.');
        $this->assertSame($ciNodeVersion, (string) ($rootLock['packages']['']['engines']['node'] ?? ''), 'Root package-lock.json engines.node harus sama dengan CI.');

        // Frontend package-lock.json
        $frontendLockPath = base_path('../frontend/package-lock.json');
        $this->assertFileExists($frontendLockPath, 'Frontend package-lock.json harus ada.');
        $frontendLock = json_decode(file_get_contents($frontendLockPath), true);
        $this->assertArrayHasKey('engines', $frontendLock['packages'][''] ?? [], 'Frontend package-lock.json root package harus mendefinisikan engines.');
        $this->assertSame($ciNodeVersion, (string) ($frontendLock['packages']['']['engines']['node'] ?? ''), 'Frontend package-lock.json engines.node harus sama dengan CI.');
    }

    /**
     * Uji 3: File lock dependensi (package-lock.json & composer.lock) lengkap dan valid.
     */
    public function test_dependency_lockfiles_exist_and_lock_dependencies(): void
    {
        // 1. Root package-lock.json
        $rootLockPath = base_path('../package-lock.json');
        $this->assertFileExists($rootLockPath, 'package-lock.json root harus ada.');
        $rootLockData = json_decode(file_get_contents($rootLockPath), true);
        $this->assertNotNull($rootLockData, 'package-lock.json root harus JSON valid.');
        $this->assertArrayHasKey('packages', $rootLockData, 'package-lock.json root harus memiliki packages.');

        // 2. Frontend package-lock.json
        $frontendLockPath = base_path('../frontend/package-lock.json');
        $this->assertFileExists($frontendLockPath, 'package-lock.json frontend harus ada.');
        $frontendLockData = json_decode(file_get_contents($frontendLockPath), true);
        $this->assertNotNull($frontendLockData, 'package-lock.json frontend harus JSON valid.');
        $this->assertArrayHasKey('packages', $frontendLockData, 'package-lock.json frontend harus memiliki packages.');

        // 3. Backend composer.lock
        $composerLockPath = base_path('composer.lock');
        $this->assertFileExists($composerLockPath, 'composer.lock backend harus ada.');
        $composerLockData = json_decode(file_get_contents($composerLockPath), true);
        $this->assertNotNull($composerLockData, 'composer.lock harus JSON valid.');
        $this->assertArrayHasKey('packages', $composerLockData, 'composer.lock harus memiliki packages.');
        $this->assertNotEmpty($composerLockData['packages'], 'composer.lock packages tidak boleh kosong.');
    }

    /**
     * Uji 4: Simulasi audit membuktikan ambang lulus "ada & sama dengan CI".
     */
    public function test_audit_evidence_expectation_returns_ada_dan_sama_dengan_ci(): void
    {
        $ciWorkflowPath = base_path('../.github/workflows/ci.yml');
        $ciContent = file_get_contents($ciWorkflowPath);
        preg_match('/node-version:\s*(\d+)/', $ciContent, $matches);
        $ciNodeVersion = $matches[1] ?? '20';

        $rootNvmrc = base_path('../.nvmrc');
        $pin = file_exists($rootNvmrc) ? trim(file_get_contents($rootNvmrc)) : '-';

        $rootPkgPath = base_path('../package.json');
        $rootPkg = file_exists($rootPkgPath) ? json_decode(file_get_contents($rootPkgPath), true) : [];
        $enginesNode = $rootPkg['engines']['node'] ?? '-';

        $this->assertSame($ciNodeVersion, $pin, "pin ({$pin}) harus sama dengan CI ({$ciNodeVersion}).");
        $this->assertSame($ciNodeVersion, $enginesNode, "engines.node ({$enginesNode}) harus sama dengan CI ({$ciNodeVersion}).");

        $status = ($pin !== '-' && $enginesNode !== '-' && $pin === $ciNodeVersion && $enginesNode === $ciNodeVersion)
            ? 'ada & sama dengan CI'
            : 'tidak lulus';

        $this->assertSame('ada & sama dengan CI', $status);
    }
}
