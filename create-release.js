const fs = require('fs');
const { exec } = require('child_process');
const util = require('util');
const path = require('path');
const execPromise = util.promisify(exec);

// Parse command line arguments for outdir= and version=
const args = process.argv.slice(2);
const outdirArg = args.find(arg => arg.startsWith('outdir='));
const versionArg = args.find(arg => arg.startsWith('version='));
const outputDir = outdirArg ? outdirArg.split('=')[1] : path.dirname(__filename);
const newVersion = versionArg ? versionArg.split('=')[1] : null;

// Read composer.json to get the version
const composerJson = JSON.parse(fs.readFileSync('composer.json', 'utf8'));

async function updateVersionInFiles(version) {
  if (!version) return;

  // Update version in invintus.php
  const invintusFile = 'invintus.php';
  let invintusContent = fs.readFileSync(invintusFile, 'utf8');
  invintusContent = invintusContent.replace(/Version:\s*\d+\.\d+\.\d+/, `Version: ${version}`);
  invintusContent = invintusContent.replace(/define\( 'INVINTUS_PLUGIN_VERSION', '\d+\.\d+\.\d+' \);/, `define( 'INVINTUS_PLUGIN_VERSION', '${version}' );`);
  fs.writeFileSync(invintusFile, invintusContent);

  // Update version in package.json
  const packageJsonFile = 'package.json';
  const packageJsonContent = JSON.parse(fs.readFileSync(packageJsonFile, 'utf8'));
  packageJsonContent.version = version;
  fs.writeFileSync(packageJsonFile, JSON.stringify(packageJsonContent, null, 2));

  console.log(`✅ Version updated to ${version}`);
}

async function runNpmBuild() {
  try {
    console.log('📦 Running npm build...');
    await execPromise('npm run build', {
      maxBuffer: 1024 * 1024 * 10, // 10MB buffer
    });
    console.log('✅ npm build completed');
  } catch (error) {
    console.error('Error running npm build:', error.message);
    process.exit(1);
  }
}

async function runComposerInstall() {
  try {
    console.log('📦 Running composer install...');
    await execPromise('composer install --no-dev --optimize-autoloader', {
      maxBuffer: 1024 * 1024 * 10, // 10MB buffer
    });
    console.log('✅ Composer install completed');
  } catch (error) {
    console.error('Error running composer install:', error.message);
    process.exit(1);
  }
}

async function createZip() {
  const pluginSlug = composerJson.name.split('/').pop();
  const zipName = `${pluginSlug}.zip`;
  const zipPath = path.join(outputDir, zipName);

  // Files/directories to exclude from the zip
  const excludes = [
    '.*',
    'node_modules/*',
    'create-release.js',
    'package.json',
    'package-lock.json',
    'composer.lock',
    'composer.json',
    '*.zip',
    'tests/*',
    'src/*',
    'README.md',
    'CHANGELOG.md',
    'pnpm-lock.yaml',
    'phpcs.xml.dist',
    'webpack.config.js',
  ];

  try {
    // Create output directory if it doesn't exist
    if (!fs.existsSync(outputDir)) {
      fs.mkdirSync(outputDir, { recursive: true });
    }

    console.log('📦 Creating zip file...');
    console.log(`📍 Output directory: ${outputDir}`);

    // Create zip file with proper exclude syntax
    const zipCommand = `zip -r "${zipPath}" . -x ${excludes.map(pattern => `"${pattern}"`).join(' ')}`;
    await execPromise(zipCommand, {
      maxBuffer: 1024 * 1024 * 10, // 10MB buffer
    });

    console.log(`✅ Created zip file: ${zipName}`);
    console.log('\n🔔 Next steps:');
    console.log('1. Go to GitHub and create a new release');
    console.log(`2. Upload the zip file: ${zipPath}`);
  } catch (error) {
    console.error('Error creating zip:', error.message);
    process.exit(1);
  }
}

async function main() {
  if (newVersion) {
    await updateVersionInFiles(newVersion);
  }
  await runNpmBuild();
  await runComposerInstall();
  await createZip();
}

main();
