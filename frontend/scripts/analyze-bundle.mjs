import fs from 'fs';
import path from 'path';
import zlib from 'zlib';
import vm from 'vm';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const frontendDir = path.resolve(__dirname, '..');
const nextDir = path.join(frontendDir, '.next');

const THRESHOLD_KB = 200;

function analyzeBundle() {
    console.log('=== Saga MeetSpace - Bundle Size & Performance Analyzer ===');
    console.log(`Ambang batas standar: <= ${THRESHOLD_KB} KB gzip\n`);

    const buildManifestPath = path.join(nextDir, 'build-manifest.json');
    if (!fs.existsSync(buildManifestPath)) {
        console.error('Error: Direktori .next belum terbentuk. Jalankan "npm run build" terlebih dahulu.');
        process.exit(1);
    }

    const buildManifest = JSON.parse(fs.readFileSync(buildManifestPath, 'utf8'));
    const rootMainFiles = buildManifest.rootMainFiles || [];

    const routes = ['/', '/admin', '/dashboard', '/jadwal', '/riwayat', '/ruangan'];
    let allPassed = true;

    for (const route of routes) {
        const manifestSegment = route === '/' ? '/page' : `${route}/page`;
        const manifestFileName = route === '/' 
            ? path.join(nextDir, 'server', 'app', 'page_client-reference-manifest.js')
            : path.join(nextDir, 'server', 'app', route.slice(1), 'page_client-reference-manifest.js');

        const allChunks = new Set();
        rootMainFiles.forEach(f => allChunks.add(f));

        if (fs.existsSync(manifestFileName)) {
            const code = fs.readFileSync(manifestFileName, 'utf8');
            const sandbox = { globalThis: {} };
            vm.createContext(sandbox);
            vm.runInContext(code, sandbox);

            const manifest = sandbox.globalThis.__RSC_MANIFEST?.[manifestSegment];
            if (manifest?.clientModules) {
                for (const [, data] of Object.entries(manifest.clientModules)) {
                    if (Array.isArray(data.chunks)) {
                        data.chunks.forEach(c => {
                            if (c.endsWith('.js')) allChunks.add(c);
                        });
                    }
                }
            }
        }

        let totalGzip = 0;
        let totalRaw = 0;

        for (const file of allChunks) {
            const fullPath = path.join(nextDir, file);
            if (fs.existsSync(fullPath)) {
                const buf = fs.readFileSync(fullPath);
                totalRaw += buf.length;
                totalGzip += zlib.gzipSync(buf).length;
            }
        }

        const gzipKB = Number((totalGzip / 1024).toFixed(1));
        const rawKB = Number((totalRaw / 1024).toFixed(1));
        const passed = gzipKB <= THRESHOLD_KB;

        if (!passed) {
            allPassed = false;
        }

        const status = passed ? 'PASS' : 'FAIL';
        console.log(`[${status}] Rute ${route.padEnd(12)} -> JS awal: ${gzipKB} KB gzip (${rawKB} KB raw)`);
    }

    console.log('\n-----------------------------------------------------------');
    if (allPassed) {
        console.log(`Hasil: SEMUA RUTE LOLOS (<= ${THRESHOLD_KB} KB gzip).`);
    } else {
        console.error(`Hasil: GAGAL (Terdapat rute yang melebihi ${THRESHOLD_KB} KB gzip).`);
        process.exit(1);
    }
}

analyzeBundle();
