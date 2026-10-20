import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import {readFileSync} from 'node:fs';
import mysql from '../realtime/node_modules/mysql2/promise.js';
import {consumeGrant,rateHit,sessionValid} from './messaging-security.mjs';
const config={};
for(const source of readFileSync('/var/www/dnct.pl/config/beta.env','utf8').split(/\r?\n/)){
 const line=source.trim(),pos=line.indexOf('=');if(pos<1||line.startsWith('#'))continue;
 let value=line.slice(pos+1).trim();if((value[0]==='"'&&value.at(-1)==='"')||(value[0]==="'"&&value.at(-1)==="'"))value=value.slice(1,-1);config[line.slice(0,pos)]=value;
}
const db=await mysql.createConnection({host:config.MY_DB_HOST,port:Number(config.MY_DB_PORT||3306),database:config.MY_DB_DATABASE,user:config.MY_DB_USERNAME,password:config.MY_DB_PASSWORD});
db.release=()=>{};
const pool={getConnection:async()=>db,execute:db.execute.bind(db)};
try{
 const [[name]]=await db.query('SELECT DATABASE() AS name');assert.equal(name.name,'nazwa_bazy');
 for(const table of ['messaging_ws_grants','messaging_security_sessions','messaging_limits']){
  const [[ddl]]=await db.query('SHOW CREATE TABLE '+table);
  await db.query(ddl['Create Table'].replace(/^CREATE TABLE/,'CREATE TEMPORARY TABLE'));
 }
 const hash=v=>crypto.createHash('sha256').update(v).digest('hex'),session=hash('session'),subject=hash('subject'),token='a'.repeat(64);
 await db.execute("INSERT INTO messaging_security_sessions(session_hash,subject_key,username,identity_fingerprint,expires_at) VALUES(?,?,'',?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))",[session,subject,hash('guest')]);
 await db.execute("INSERT INTO messaging_ws_grants(token_hash,session_hash,scope,user_id,ticket_id,role,participant_key,expires_at) VALUES(?,?,'chat','test',0,'user','test',DATE_ADD(NOW(),INTERVAL 5 MINUTE))",[hash(token),session]);
 assert.equal(await consumeGrant(pool,token,'support'),null);console.log('PASS token cannot cross scopes');
 const grant=await consumeGrant(pool,token,'chat');assert.equal(grant.subject_key,subject);
 assert.equal(await consumeGrant(pool,token,'chat'),null);console.log('PASS token replay rejected');
 assert.equal(await sessionValid(pool,grant),true);
 await db.execute('UPDATE messaging_security_sessions SET revoked=1');
 assert.equal(await sessionValid(pool,grant),false);console.log('PASS session revocation enforced');
 await db.execute('UPDATE messaging_ws_grants SET consumed=0');
 assert.equal(await consumeGrant(pool,token,'chat'),null);console.log('PASS revoked session cannot authenticate');
 await db.execute('UPDATE messaging_security_sessions SET revoked=0,expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND)');
 assert.equal(await consumeGrant(pool,token,'chat'),null);console.log('PASS expired session cannot authenticate');
 for(let i=0;i<5;i++)assert.equal(await rateHit(pool,subject),0);
 assert.equal(await rateHit(pool,subject),3);console.log('PASS WebSocket burst cap and cooldown');
 console.log('Temporary tables only; no real tokens or messages written.');
}finally{await db.end();}

