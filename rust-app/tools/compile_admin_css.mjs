// Install the official pinned compiler in a disposable directory:
// npm install --prefix /tmp/rust-admin-less --ignore-scripts less@4.9.1
// LESS_COMPILER=/tmp/rust-admin-less/node_modules/less/bin/lessc node tools/compile_admin_css.mjs
import {readFile,writeFile,mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {spawnSync} from 'node:child_process';
const source=resolve('../app/less/styles.less');
const compiler=process.env.LESS_COMPILER;if(!compiler)throw new Error('Set LESS_COMPILER to official less@4.9.1 bin/lessc');
const version=spawnSync(process.execPath,[compiler,'--version'],{encoding:'utf8'});if(version.status||!version.stdout.includes('4.9.1'))throw new Error('LESS compiler must be version 4.9.1');
const temp=await mkdtemp(join(tmpdir(),'rust-admin-css-'));
try {
 const input=join(temp,'styles.less');
 // Keep external font CSS imports as CSS, without fetching remote resources during compilation.
 await writeFile(input,(await readFile(source,'utf8')).replaceAll('@import url(','@import (css) url('));
 const result=spawnSync(process.execPath,[compiler,'--math=always','--compress','--include-path='+resolve('../app/less'),input,resolve('data/admin-styles.css')],{stdio:'inherit'});
 if(result.status)throw new Error('LESS compilation failed');
} finally {await rm(temp,{recursive:true,force:true});}
