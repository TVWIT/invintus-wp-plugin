const fs = require('fs');
const path = require('path');
const util = require('util');
const { exec } = require('child_process');
const fsExtra = require('fs-extra');

const execPromise = util.promisify(exec);

// ─── CLI Arguments ─────────────────────────────
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

const INCLUDE_LIST = [
  'build',
  'assets',
  'inc',
  'vendor',
  'templates',
  'views',
  'invintus.php',
  'LICENSE',
  'doc.md'
];

// ─── Tag Checker ───────────────────────────────
async function tagExists(tagName, cwd = '.') {
  try {
    await execPromise(`git rev-parse "${tagName}"`, { cwd });
    return true;
  } catch {
    return false;
  }
}

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

// ─── Dist Push & Tag ───────────────────────────
async function pushToDistRepo(rawVersion) {
  const tagVersion = `v${rawVersion}`;
  const DIST_REPO_URL = 'https://github.com/TVWIT/invintus-wp-plugin-dist.git';
  const MAIN_REPO = 'TVWIT/invintus-wp-plugin';
  const DIST_WORK_DIR = path.join(__dirname, '.dist-push');

  if (fs.existsSync(DIST_WORK_DIR)) fsExtra.removeSync(DIST_WORK_DIR);
  fs.mkdirSync(DIST_WORK_DIR, { recursive: true });

  console.log(`📂 Cloning dist repo...`);
  await execPromise(`git clone ${DIST_REPO_URL} ${DIST_WORK_DIR}`);

  console.log('🧹 Cleaning dist contents...');
  await execPromise('git rm -rf .', { cwd: DIST_WORK_DIR });

  for (const file of INCLUDE_LIST) {
    const srcPath = path.resolve(file);
    const destPath = path.join(DIST_WORK_DIR, file);
    if (fs.existsSync(srcPath)) {
      fsExtra.copySync(srcPath, destPath);
    } else {
      console.warn(`⚠️  Missing expected file/folder: ${file}`);
    }
  }

  // Write Packagist-compatible composer.json
  const distComposerJson = {
    name: "invintus/invintus-wp-plugin",
    type: "wordpress-plugin",
    description: "Official Invintus plugin for embedding live and on-demand video from Invintus Media Platform.",
    license: "MIT",
    homepage: "https://invintus.com",
    require: {}
  };
  fs.writeFileSync(path.join(DIST_WORK_DIR, "composer.json"), JSON.stringify(distComposerJson, null, 2));

  console.log('📦 Committing and pushing to dist...');
  await execPromise('git add .', { cwd: DIST_WORK_DIR });
  try {
    await execPromise(`git commit -m "Release ${tagVersion}"`, { cwd: DIST_WORK_DIR });
  } catch (err) {
    if (err.stdout && err.stdout.includes('nothing to commit')) {
      console.log('ℹ️  No changes to commit. Proceeding with tagging and release...');
    } else {
      throw err;
    }
  }

  if (!skipTag) {
    const distTagExists = await tagExists(tagVersion, DIST_WORK_DIR);
    if (!distTagExists) {
      await execPromise(`git tag ${tagVersion}`, { cwd: DIST_WORK_DIR });
      await execPromise(`git push origin ${tagVersion}`, { cwd: DIST_WORK_DIR });
    } else {
      console.log(`ℹ️ Tag ${tagVersion} already exists in dist repo.`);
    }
  }

  await execPromise('git push origin main', { cwd: DIST_WORK_DIR });

  if (!skipTag) {
    const mainTagExists = await tagExists(tagVersion);
    if (!mainTagExists) {
      await execPromise(`git tag ${tagVersion}`);
      await execPromise(`git push origin ${tagVersion}`);
    } else {
      console.log(`ℹ️ Tag ${tagVersion} already exists in main repo.`);
    }
  }

  return DIST_WORK_DIR;
}

// ─── ZIP Creator from Dist ─────────────────────
async function createZip(fromDir) {
  const pluginSlug = composerJson.name.split('/').pop();
  const zipName = `${pluginSlug}.zip`;
  const zipPath = path.join(outputDir, zipName);

  if (!fs.existsSync(fromDir)) {
    console.error(`❌ Can't zip — folder does not exist: ${fromDir}`);
    process.exit(1);
  }

  console.log('📦 Creating zip from dist-push directory...');
  const zipCommand = `cd "${fromDir}" && zip -r "${zipPath}" ${[...INCLUDE_LIST, 'composer.json'].map(f => `"${f}"`).join(' ')}`;
  await execPromise(zipCommand, { maxBuffer: 1024 * 1024 * 10 });

  console.log(`✅ Created zip: ${zipName}`);
  return zipPath;
}

// ─── GitHub Release Creator ────────────────────
async function createGitHubReleases(tagVersion, zipPath) {
  console.log('🚀 Creating GitHub release in dist repo...');
  await execPromise(`gh release create ${tagVersion} "${zipPath}" --title "${tagVersion}" --notes "Automated release build" --repo TVWIT/invintus-wp-plugin-dist`);
  console.log('🎉 GitHub release created in dist repo');

  console.log('🚀 Creating GitHub release in main repo...');
  await execPromise(`gh release create ${tagVersion} "${zipPath}" --title "${tagVersion}" --notes "Automated release build" --repo TVWIT/invintus-wp-plugin`);
  console.log('🎉 GitHub release created in main repo');
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

  let distPath = null;
  let zipPath = null;

  if (doPush) {
    distPath = await pushToDistRepo(rawVersion);
    if (!skipZip) {
      zipPath = await createZip(distPath);
    }
  }

  if (doRelease && zipPath) {
    await createGitHubReleases(tagVersion, zipPath);
  }
}

main();
