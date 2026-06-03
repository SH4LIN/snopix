import type { FullConfig } from '@playwright/test';
import { execFileSync } from 'child_process';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';

export default async function globalTeardown(_config: FullConfig): Promise<void> {
	const timestamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
	const runName   = `snopix-test-${timestamp}`;
	const artifactDir = path.join(os.tmpdir(), runName);
	const archive     = path.join(os.homedir(), 'Desktop', `${runName}.zip`);
	const pluginRoot  = process.cwd();

	fs.mkdirSync(path.join(artifactDir, 'e2e'), { recursive: true });

	// E2E: HTML report (screenshots are embedded in here)
	const reportDir = path.join(pluginRoot, 'playwright-report');
	if (fs.existsSync(reportDir)) {
		fs.cpSync(reportDir, path.join(artifactDir, 'e2e', 'playwright-report'), { recursive: true });
	}

	// E2E: raw test-results (traces, per-step screenshots)
	const resultsDir = path.join(pluginRoot, 'test-results');
	if (fs.existsSync(resultsDir)) {
		fs.cpSync(resultsDir, path.join(artifactDir, 'e2e', 'test-results'), { recursive: true });
	}

	// PHPUnit: JUnit XML written by phpunit-unit.xml.dist / phpunit-integration.xml.dist
	for (const [src, dest] of [
		['/tmp/snopix-phpunit-unit.xml', 'unit-results.xml'],
		['/tmp/snopix-phpunit-integration.xml', 'integration-results.xml'],
	] as const) {
		if (fs.existsSync(src)) {
			fs.copyFileSync(src, path.join(artifactDir, dest));
		}
	}

	// Bundle — execFileSync avoids shell interpolation
	try {
		execFileSync(
			'zip',
			['-r', archive, path.basename(artifactDir), '-x', '*.git*'],
			{ cwd: path.dirname(artifactDir), stdio: 'pipe' }
		);
		console.log(`\nArtifact → ${archive}`);
	} catch (err) {
		console.error('Could not create zip:', err);
		return;
	}

	// Schedule auto-deletion after 24 h via at(1) with a script file (no shell pipe)
	const cleanupScript = path.join(os.tmpdir(), `${runName}-cleanup.sh`);
	fs.writeFileSync(cleanupScript, `#!/bin/sh\nrm -rf "${artifactDir}" "${archive}" "${cleanupScript}"\n`, { mode: 0o700 });
	try {
		execFileSync('at', ['-f', cleanupScript, 'now', '+', '24', 'hours'], { stdio: 'pipe' });
		console.log('Auto-deletes in 24 h.');
	} catch {
		console.log(`Manual cleanup: rm -rf "${artifactDir}" "${archive}"`);
	}
}
