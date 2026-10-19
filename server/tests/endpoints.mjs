import {PHP, loadPHPRuntime} from '@php-wasm/universal';
import {getPHPLoaderModule} from '@php-wasm/node-8-4';
import fs from 'node:fs';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
const php = new PHP(await loadPHPRuntime(await getPHPLoaderModule()));
for (const dir of ['/public','/public/cms','/private']) php.mkdir(dir);
const root=fileURLToPath(new URL('../../', import.meta.url)).replace(/\/$/, '');
for (const file of ['lib.php','login.php','api.php']) await php.writeFile('/public/cms/'+file,fs.readFileSync(root+'/server/cms/'+file));
await php.writeFile('/private/acl.php',"<?php const MASTER_ACL=['cms_bosque'=>['editor']];");
await php.writeFile('/private/helper.php',"<?php function require_auth(string $label): string { return 'editor'; }");
await php.writeFile('/private/schema.json',fs.readFileSync(root+'/cms-schema.json'));
await php.writeFile('/private/config.php',`<?php return [
'acl_file'=>'/private/acl.php','auth_helper_file'=>'/private/helper.php','database'=>'/private/session.sqlite',
'sites'=>['bosque'=>['repo'=>'example/repo','branch'=>'main','acl'=>'cms_bosque','origin'=>'https://editor.example.edu','schema_file'=>'/private/schema.json','github_token_env'=>'TEST_TOKEN'],
'other'=>['repo'=>'example/other','branch'=>'main','acl'=>'cms_bosque','origin'=>'https://editor.example.edu','schema_file'=>'/private/schema.json','github_token_env'=>'TEST_TOKEN']]];`);
async function request(file, query={}, options={}) {
 const {origin='https://editor.example.edu',token, data, method=data?'POST':'GET', secret='x'.repeat(40)}=options;
 const result=await php.runStream({scriptPath:'/public/cms/'+file,relativeUri:'/cms/'+file+'?'+new URLSearchParams(query),protocol:'https',method,
 headers:{Origin:origin,...(data?{'Content-Type':'application/json'}:{}),...(token?{Authorization:'Bearer '+token}:{})},body:data?new TextEncoder().encode(JSON.stringify(data)):undefined,
 env:{CMS_CONFIG_FILE:'/private/config.php',LDAP_AUTH_SECRET:secret},$_SERVER:{DOCUMENT_ROOT:'/public'},usePrimaryPhp:true});
 const text=await result.stdoutText, errors=await result.stderrText; assert.equal(errors,'');
 return {text,status:await result.httpStatusCode,headers:await result.headers,json:()=>JSON.parse(text)};
}
let n=0;
function status(response,code) { assert.equal(response.status,code,response.text); n++; }
const verifier='v'.repeat(64),state='a'.repeat(64),challenge=createHash('sha256').update(verifier).digest('base64url');
status(await request('login.php',{site:'bosque',state,challenge},{secret:''}),503);
status(await request('login.php',{site:'bosque',state:'bad',challenge}),400);
const login=await request('login.php',{site:'bosque',state,challenge}); status(login,200);
const code=JSON.parse(login.text.match(/postMessage\((\{.*?\}),/)[1]).code;
status(await request('api.php',{site:'bosque',action:'exchange'},{data:{code,verifier:'z'.repeat(64)}}),401);
const session=await request('api.php',{site:'bosque',action:'exchange'},{data:{code,verifier}}); status(session,200);
assert.equal(session.json().approved,true); n++;
const token=session.json().token;
status(await request('api.php',{site:'bosque',action:'exchange'},{data:{code,verifier}}),401);
status(await request('api.php',{site:'bosque',action:'session'},{token}),200);
status(await request('api.php',{site:'other',action:'session'},{token}),401);
status(await request('api.php',{site:'bosque',action:'session'},{token,origin:'https://attacker.example'}),403);
status(await request('api.php',{site:'bosque',action:'session'}),401);
const preflight=await request('api.php',{site:'bosque'},{method:'OPTIONS'}); status(preflight,204);
status(await request('api.php',{site:'bosque',action:'logout'},{token,data:{}}),200);
status(await request('api.php',{site:'bosque',action:'session'},{token}),401);
console.log(n+' PHP endpoint checks passed using a fixture identity helper; no LDAP or GitHub requests made.');
