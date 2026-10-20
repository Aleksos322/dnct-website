import crypto from 'node:crypto';
import {statSync,readFileSync} from 'node:fs';
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
export async function rateHit(database,subject,scope='message',second=5,minute=50) {
 const connection=await database.getConnection();
 try{
  await connection.beginTransaction();
  const key=hash(scope+'\0'+subject);
  await connection.execute("INSERT INTO messaging_limits(bucket,hits,updated_at) VALUES(?,'[]',NOW()) ON DUPLICATE KEY UPDATE bucket=VALUES(bucket)",[key]);
  const [[row]]=await connection.execute('SELECT hits,blocked_until FROM messaging_limits WHERE bucket=? FOR UPDATE',[key]);
  const now=Date.now();
  const hits=JSON.parse(row.hits).filter(t=>t>now-60000);
  let until=Number(row.blocked_until);
  if(until<=now){
   if(hits.filter(t=>t>now-1000).length>=second)until=now+3000;
   else if(hits.length>=minute)until=Math.max(now+3000,hits[0]+60000);
   else{hits.push(now);until=0;}
  }
  await connection.execute('UPDATE messaging_limits SET hits=?,blocked_until=?,updated_at=NOW() WHERE bucket=?',[JSON.stringify(hits),until,key]);
  await connection.commit();return until>now?Math.ceil((until-now)/1000):0;
 }catch(error){await connection.rollback();throw error;}finally{connection.release();}
}
export async function consumeGrant(database,token,scope){
 if(typeof token!=='string'||!/^[a-f0-9]{64}$/.test(token))return null;
 const connection=await database.getConnection();
 try{
  await connection.beginTransaction();
  const [[grant]]=await connection.execute('SELECT g.*,s.subject_key FROM messaging_ws_grants g JOIN messaging_security_sessions s ON s.session_hash=g.session_hash WHERE g.token_hash=? AND g.scope=? AND g.consumed=0 AND g.expires_at>NOW() AND s.expires_at>NOW() AND s.revoked=0 FOR UPDATE',[hash(token),scope]);
  if(!grant){await connection.rollback();return null;}
  await connection.execute('UPDATE messaging_ws_grants SET consumed=1 WHERE token_hash=?',[hash(token)]);
  await connection.commit();return grant;
 }catch(error){await connection.rollback();throw error;}finally{connection.release();}
}
let identityConfig=null;
const identityCache=new Map();
export function configureIdentity(config){identityConfig=config;}
async function identityValid(database,session){
 if(!session.username)return true;
 if(!identityConfig?.AUTHENTIK_URL||!identityConfig?.AUTHENTIK_API_TOKEN)return false;
 let cached=identityCache.get(session.username);
 if(!cached||cached.until<Date.now()){
  try{
   const url=identityConfig.AUTHENTIK_URL.replace(/\/$/,'')+'/api/v3/core/users/?username='+encodeURIComponent(session.username)+'&page_size=100';
   const response=await fetch(url,{headers:{Authorization:'Bearer '+identityConfig.AUTHENTIK_API_TOKEN},signal:AbortSignal.timeout(5000),redirect:'error'});
   if(!response.ok)return false;
   const body=await response.json(),user=body.results?.find(u=>u.username===session.username);
   const fingerprint=user?hash(JSON.stringify([Boolean(user.is_active),Boolean(user.is_superuser),(user.groups||[]).map(String).sort()])):'';
   cached={fingerprint,active:Boolean(user?.is_active),until:Date.now()+60000};identityCache.set(session.username,cached);
  }catch{return false;}
 }
 if(!cached.active||cached.fingerprint!==session.identity_fingerprint){
  await database.execute('UPDATE messaging_security_sessions SET revoked=1 WHERE session_hash=?',[session.session_hash]);return false;
 }
 return Boolean(cached.active && cached.fingerprint===session.identity_fingerprint);
}
export async function sessionValid(database,grant){
 const [[session]]=await database.execute('SELECT * FROM messaging_security_sessions WHERE session_hash=? AND revoked=0 AND expires_at>NOW()',[grant.session_hash]);
 if(!session)return false;
 const root='/var/www/dnct.pl/public_html/storage/sso-logout/';
 try {if(session.sid_hash&&statSync(root+'sid-'+session.sid_hash).mtimeMs/1000>Number(session.logged_at))return false;}catch(e){if(e.code!=='ENOENT')return false;}
 try {if(session.sub_hash&&Number(readFileSync(root+'sub-'+session.sub_hash,'utf8'))>=Number(session.logged_at))return false;}catch(e){if(e.code!=='ENOENT')return false;}
 return await identityValid(database,session);
}


export async function securityAudit(database,event,grant=null){
 if(!/^[a-z_]{3,48}$/.test(event))return;
 const subject=grant?.subject_key||hash('unauthenticated'),ip=hash('websocket'),key=hash(event+':'+subject+':0:'+Math.floor(Date.now()/60000));
 try{
  await database.execute('INSERT INTO messaging_security_audit(event_key,event_type,subject_key,ip_hash,resource_id,occurred_at,last_seen) VALUES(?,?,?,?,0,NOW(),NOW()) ON DUPLICATE KEY UPDATE hits=hits+1,last_seen=NOW()',[key,event,subject,ip]);
 }catch{console.error('messaging_security: audit_unavailable');}
}
