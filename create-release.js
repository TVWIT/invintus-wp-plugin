const fs = require('fs');
const path = require('path');
const util = require('util');
const { exec } = require('child_process');
const fsExtra = require('fs-extra');

const execPromise = util.promisify(exec);

// ─── CLI arguments ─────────────────────────────
const args = process.argv.slice(2);
const outdirArg = args.find(arg => arg.startsWith('outdir='));
const versionArg = args.find(arg => arg.startsWith('version='));
const doPush = args.includes('--push');
const doRelease = args.includes('--create-release');
const skipZip = args.includes('--no-zip');
const skipTag = args.includes('--no-tag');

const outputDir = outdirArg ? outdirArg.split('=')[1] : path.dirname(__filename);
const newVersion = versionArg ? versionArg.split('=')[1] : null;

const composerJson = JSON.parse(fs.readFileSync('composer.json', 'utf8'));

// ─── Version Updater ───────────────────────────
async function updateVersionInFiles(version) {
  console.log(`📝 Updating version to ${version}...`);

  const invintusFile = 'invintus.php';
  let invintusContent = fs.readFileSync(invintusFile, 'utf8');
  invintusContent = invintusContent.replace(/Version:\s*\d+\.\d+\.\d+/, `Version: ${version}`);
  invintusContent = invintusContent.replace(/define\( 'INVINTUS_PLUGIN_VERSION', '\d+\.\d+\.\d+' \);/, `define( 'INVINTUS_PLUGIN_VERSION', '${version}' );`);
  fs.writeFileSync(invintusFile, invintusContent);

  const packageJsonFile = 'package.json';
  const packageJsonContent = JSON.parse(fs.readFileSync(packageJsonFile, 'utf8'));
  packageJsonContent.version = version;
  fs.writeFileSync(packageJsonFile, JSON.stringify(packageJsonContent, null, 2));

  console.log(`✅ Version updated in PHP and package.json`);
}

// ─── Build & Composer ──────────────────────────
async function runNpmBuild() {
  console.log('📦 Running npm build...');
  await execPromise('npm run build', { maxBuffer: 1024 * 1024 * 10 });
  console.log('✅ npm build completed');
}

async function runComposerInstall() {
  console.log('📦 Running composer install...');
  await execPromise('composer install --no-dev --optimize-autoloader', { maxBuffer: 1024 * 1024 * 10 });
  console.log('✅ Composer install completed');
}

// ─── ZIP Creator ───────────────────────────────
async function createZip() {
  const pluginSlug = composerJson.name.split('/').pop();
  const zipName = `${pluginSlug}.zip`;
  const zipPath = path.join(outputDir, zipName);

  const excludes = [
    '.*',
    'create-release.js',
    'package.json',
    'package-lock.json',
    'composer.lock',
    '*.zip',
    'tests/*',
    'src/*',
    'README.md',
    'CHANGELOG.md',
    'pnpm-lock.yaml',
    'phpcs.xml.dist',
    'webpack.config.js',
    '.dist-push/*',
  ];

  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  console.log('📦 Creating zip file...');
  const zipCommand = `zip -r "${zipPath}" . -x ${excludes.map(p => `"${p}"`).join(' ')}`;
  await execPromise(zipCommand, { maxBuffer: 1024 * 1024 * 10 });

  console.log(`✅ Created zip: ${zipName}`);
  return zipPath;
}

// ─── Dist Push & Tag ───────────────────────────
async function pushToDistRepo(rawVersion, zipPath) {
  const tagVersion = `v${rawVersion}`;
  const DIST_REPO_URL = 'https://github.com/TVWIT/invintus-wp-plugin-dist.git';
  const MAIN_REPO = 'TVWIT/invintus-wp-plugin';
  const DIST_WORK_DIR = path.join(__dirname, '.dist-push');

  if (fs.existsSync(DIST_WORK_DIR)) fsExtra.removeSync(DIST_WORK_DIR);

  console.log(`📂 Cloning dist repo...`);
  await execPromise(`git clone ${DIST_REPO_URL} ${DIST_WORK_DIR}`);

  console.log('🧹 Cleaning dist contents...');
  await execPromise('git rm -rf .', { cwd: DIST_WORK_DIR });

  const excludes = new Set([
    '.git', '.github', 'node_modules', '.dist-push', 'tests', 'src',
    'create-release.js', 'package-lock.json',
    'README.md', 'CHANGELOG.md',
    'webpack.config.js', 'phpcs.xml.dist', 'pnpm-lock.yaml'
  ]);

  const buildFiles = fs.readdirSync('.').filter(f => !excludes.has(f));
  for (const file of buildFiles) {
    fsExtra.copySync(path.join('.', file), path.join(DIST_WORK_DIR, file));
  }

  // Write minimal composer.json for Packagist
  const distComposerJson = {
    name: "tvwit/invintus-wp-plugin",
    type: "wordpress-plugin",
    description: "Compiled release version of the Invintus WordPress Plugin",
    license: "MIT",
    require: {}
  };
  fs.writeFileSync(path.join(DIST_WORK_DIR, "composer.json"), JSON.stringify(distComposerJson, null, 2));

  console.log('📦 Committing and pushing to dist...');
  await execPromise('git add .', { cwd: DIST_WORK_DIR });
  try {
    await execPromise(`git commit -m "Release ${tagVersion}"`, { cwd: DIST_WORK_DIR });
  } catch (err) {
    if (err.stdout && err.stdout.includes('nothing to commit')) {
      console.log('ℹ️  No changes to commit. Skipping git commit, tag, and push.');
      return;
    } else {
      throw err;
    }
  }

  if (!skipTag) {
    await execPromise(`git tag ${tagVersion}`, { cwd: DIST_WORK_DIR });
  }

  await execPromise('git push origin main', { cwd: DIST_WORK_DIR });
  if (!skipTag) {
    await execPromise('git push origin --tags', { cwd: DIST_WORK_DIR });
  }

  // Also tag and push to main repo
  if (!skipTag) {
    try {
      await execPromise(`git tag ${tagVersion}`);
    } catch {}
    try {
      await execPromise(`git push origin ${tagVersion}`);
    } catch {}
  }

  if (doRelease && zipPath) {
    console.log('🚀 Creating GitHub release in dist repo...');
    await execPromise(`gh release create ${tagVersion} "${zipPath}" --title "${tagVersion}" --notes "Automated release build" --repo TVWIT/invintus-wp-plugin-dist`);
    console.log('🎉 GitHub release created in dist repo');

    console.log('🚀 Creating GitHub release in main repo...');
    await execPromise(`gh release create ${tagVersion} "${zipPath}" --title "${tagVersion}" --notes "Automated release build" --repo ${MAIN_REPO}`);
    console.log('🎉 GitHub release created in main repo');
  }

  fsExtra.removeSync(DIST_WORK_DIR);
  console.log('🧽 Cleaned up dist folder');
}

// ─── Main Runner ───────────────────────────────
async function main() {
  let version = newVersion;

  if (!version) {
    version = composerJson.version;
    if (!version) {
      console.error('❌ No version provided and composer.json is missing "version".');
      process.exit(1);
    }
  }

  const rawVersion = version.replace(/^v/, '');
  const tagVersion = `v${rawVersion}`;

  if (!/^\d+\.\d+\.\d+$/.test(rawVersion)) {
    console.warn(`⚠️  Version "${rawVersion}" looks suspicious — expected format is x.y.z`);
  }

  await updateVersionInFiles(rawVersion);
  await runNpmBuild();
  await runComposerInstall();

  let zipPath = null;
  if (!skipZip) {
    zipPath = await createZip();
  }

  if (doPush) {
    await pushToDistRepo(rawVersion, zipPath);
  }
}

main();
