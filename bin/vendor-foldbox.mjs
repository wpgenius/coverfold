#!/usr/bin/env node
/**
 * Copies the built Foldbox files from node_modules/foldbox into
 * assets/vendor/foldbox/, which ships in the plugin (the release zip and
 * WordPress.org get no node_modules), and keeps the version references in
 * sync.
 *
 *   npm run vendor:foldbox         copy the files and update the version references
 *   npm run vendor:foldbox:check   fail if the shipped copy differs from the installed package (CI)
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const pkgDir = join( root, 'node_modules', 'foldbox' );
const destDir = join( root, 'assets', 'vendor', 'foldbox' );
const check = process.argv.includes( '--check' );

let version;
try {
	version = JSON.parse( readFileSync( join( pkgDir, 'package.json' ), 'utf8' ) ).version;
} catch {
	console.error( 'foldbox is not installed. Run `npm ci` first.' );
	process.exit( 1 );
}

const files = {
	'dist/foldbox.min.js': 'foldbox.min.js',
	'dist/foldbox.min.css': 'foldbox.min.css',
	LICENSE: 'LICENSE',
};

// Version references outside the copied files: [file, pattern, replacement].
const refs = [
	[ 'includes/class-coverfold-assets.php', /(const FOLDBOX_VERSION = ')[^']*(';)/, `$1${ version }$2` ],
	[ 'readme.txt', /(\[Foldbox\]\(https:\/\/github\.com\/wpgenius\/foldbox\) )\S+/, `$1${ version }` ],
	[ 'README.md', /(Foldbox )\d+\.\d+\.\d+( \(MIT\))/, `$1${ version }$2` ],
];

const read = ( file ) => {
	try {
		return readFileSync( file );
	} catch {
		return null;
	}
};

const problems = [];

mkdirSync( destDir, { recursive: true } );
for ( const [ from, to ] of Object.entries( files ) ) {
	const src = readFileSync( join( pkgDir, from ) );
	const dest = join( destDir, to );
	const current = read( dest );
	if ( current && current.equals( src ) ) {
		continue;
	}
	if ( check ) {
		problems.push( `assets/vendor/foldbox/${ to } differs from foldbox ${ version }` );
	} else {
		writeFileSync( dest, src );
		console.log( `Copied ${ from } -> assets/vendor/foldbox/${ to }` );
	}
}

for ( const [ file, pattern, replacement ] of refs ) {
	const path = join( root, file );
	const text = readFileSync( path, 'utf8' );
	if ( ! pattern.test( text ) ) {
		problems.push( `${ file }: Foldbox version reference not found` );
		continue;
	}
	const updated = text.replace( pattern, replacement );
	if ( updated === text ) {
		continue;
	}
	if ( check ) {
		problems.push( `${ file } does not name Foldbox ${ version }` );
	} else {
		writeFileSync( path, updated );
		console.log( `Updated the Foldbox version in ${ file }` );
	}
}

if ( problems.length ) {
	console.error( problems.join( '\n' ) );
	console.error( check ? 'Run `npm run vendor:foldbox` and commit the result.' : '' );
	process.exit( 1 );
}
console.log( check ? `assets/vendor/foldbox matches foldbox ${ version }.` : `Foldbox ${ version } is in assets/vendor/foldbox.` );
