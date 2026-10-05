<?php
declare(strict_types=1);
#crypto API - Katalog + Krypto-Operationen. Self-contained (libsodium + OpenSSL),
#keine Abhaengigkeit zu externen Diensten. Antwort immer JSON.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$CAT=[
 ['php'=>'aes','alg'=>'AES-256-GCM','inf'=>'Authentifizierte symmetrische Verschlüsselung','ops'=>[
   'enc'=>['label'=>'Verschlüsseln','in'=>[['k'=>'data','l'=>'Klartext','t'=>'area'],['k'=>'key','l'=>'Schlüssel (Base64, 32 Byte)','opt'=>1,'gen'=>'k32']],'out'=>['key','out']],
   'dec'=>['label'=>'Entschlüsseln','in'=>[['k'=>'data','l'=>'Chiffre (Base64)','t'=>'area'],['k'=>'key','l'=>'Schlüssel (Base64)']],'out'=>['out']]]],
 ['php'=>'rsa','alg'=>'RSAES-OAEP-3072','inf'=>'Asymmetrische Verschlüsselung','ops'=>[
   'gen'=>['label'=>'Schlüsselpaar','in'=>[],'out'=>['pub','sec']],
   'enc'=>['label'=>'Verschlüsseln','in'=>[['k'=>'data','l'=>'Klartext (<= 342 Byte)','t'=>'area'],['k'=>'pub','l'=>'Public Key (PEM)','t'=>'area']],'out'=>['out']],
   'dec'=>['label'=>'Entschlüsseln','in'=>[['k'=>'data','l'=>'Chiffre (Base64)','t'=>'area'],['k'=>'sec','l'=>'Private Key (PEM)','t'=>'area']],'out'=>['out']]]],
 ['php'=>'eds','alg'=>'Ed25519','inf'=>'Digitale Signaturen','ops'=>[
   'gen'=>['label'=>'Schlüsselpaar','in'=>[],'out'=>['pub','sec']],
   'sgn'=>['label'=>'Signieren','in'=>[['k'=>'data','l'=>'Nachricht','t'=>'area'],['k'=>'sec','l'=>'Secret Key (Base64)']],'out'=>['sig']],
   'vrf'=>['label'=>'Prüfen','in'=>[['k'=>'data','l'=>'Nachricht','t'=>'area'],['k'=>'sig','l'=>'Signatur (Base64)'],['k'=>'pub','l'=>'Public Key (Base64)']],'out'=>['valid']]]],
 ['php'=>'dhe','alg'=>'X25519','inf'=>'Schlüsselaustausch','ops'=>[
   'gen'=>['label'=>'Schlüsselpaar','in'=>[],'out'=>['pub','sec']],
   'agr'=>['label'=>'Gemeinsames Geheimnis','in'=>[['k'=>'sec','l'=>'Eigener Secret Key (Base64)'],['k'=>'pub','l'=>'Fremder Public Key (Base64)']],'out'=>['shared']]]],
 ['php'=>'kdf','alg'=>'HKDF-SHA256','inf'=>'Schlüsselableitung','ops'=>[
   'ext'=>['label'=>'Extract','in'=>[['k'=>'ikm','l'=>'Eingabe-Keying-Material','t'=>'area'],['k'=>'salt','l'=>'Salt','opt'=>1]],'out'=>['prk']],
   'exp'=>['label'=>'Expand','in'=>[['k'=>'prk','l'=>'PRK (Base64)'],['k'=>'info','l'=>'Info','opt'=>1],['k'=>'len','l'=>'Länge (Byte)','def'=>'32']],'out'=>['okm']]]],
 ['php'=>'mac','alg'=>'HMAC-SHA256','inf'=>'Nachrichtenauthentifizierung','ops'=>[
   'tag'=>['label'=>'Tag erzeugen','in'=>[['k'=>'data','l'=>'Nachricht','t'=>'area'],['k'=>'key','l'=>'Schlüssel']],'out'=>['tag']],
   'vrf'=>['label'=>'Tag prüfen','in'=>[['k'=>'data','l'=>'Nachricht','t'=>'area'],['k'=>'key','l'=>'Schlüssel'],['k'=>'tag','l'=>'Tag (Hex)']],'out'=>['valid']]]],
 ['php'=>'hsh','alg'=>'SHA-256','inf'=>'Kryptografisches Hashing','ops'=>[
   'dig'=>['label'=>'Hashen','in'=>[['k'=>'data','l'=>'Eingabe','t'=>'area']],'out'=>['digest']],
   'cmp'=>['label'=>'Vergleichen','in'=>[['k'=>'data','l'=>'Eingabe','t'=>'area'],['k'=>'digest','l'=>'Erwarteter Hash (Hex)']],'out'=>['valid']]]],
 ['php'=>'box','alg'=>'crypto_box','inf'=>'Authentifizierte Public-Key-Verschlüsselung','ops'=>[
   'gen'=>['label'=>'Schlüsselpaar','in'=>[],'out'=>['pub','sec']],
   'enc'=>['label'=>'Verschlüsseln','in'=>[['k'=>'data','l'=>'Klartext','t'=>'area'],['k'=>'sec','l'=>'Eigener Secret Key (Base64)'],['k'=>'pub','l'=>'Empfänger Public Key (Base64)']],'out'=>['out']],
   'dec'=>['label'=>'Entschlüsseln','in'=>[['k'=>'data','l'=>'Chiffre (Base64)','t'=>'area'],['k'=>'sec','l'=>'Eigener Secret Key (Base64)'],['k'=>'pub','l'=>'Absender Public Key (Base64)']],'out'=>['out']]]],
 ['php'=>'pwd','alg'=>'Argon2id','inf'=>'Passwort-Hashing','ops'=>[
   'hsh'=>['label'=>'Hashen','in'=>[['k'=>'data','l'=>'Passwort']],'out'=>['hash']],
   'vrf'=>['label'=>'Prüfen','in'=>[['k'=>'data','l'=>'Passwort'],['k'=>'hash','l'=>'Hash']],'out'=>['valid']]]],
];

$JF=JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
function j($v){global $JF;echo json_encode($v,$JF);exit;}
function ok(array $d){j(['ok'=>true]+$d);}
function bad(string $m){http_response_code(400);j(['ok'=>false,'error'=>$m]);}
function inp(string $k):string{$v=$_POST[$k]??$_GET[$k]??'';return is_string($v)?$v:'';}
function need(string $k):string{$v=inp($k);if($v==='')bad('Feld fehlt: '.$k);return $v;}
function b64e(string $b):string{return base64_encode($b);}
function b64d(string $s,string $k='Wert'):string{$s=trim($s);$r=base64_decode(strtr($s,'-_','+/'),true);if($r===false)bad('Ungültiges Base64: '.$k);return $r;}
function needsodium(){if(!extension_loaded('sodium'))bad('PHP-Erweiterung "sodium" nicht verfügbar.');}
function needopenssl(){if(!function_exists('openssl_encrypt'))bad('PHP-Erweiterung "openssl" nicht verfügbar.');}

$t=inp('t');$o=inp('o');
if($t===''){j($CAT);}
$known=false;foreach($CAT as $c){if($c['php']===$t){$known=true;if(!isset($c['ops'][$o]))bad('Unbekannte Operation.');break;}}
if(!$known)bad('Unbekanntes Verfahren.');
if(strlen(inp('data'))>1048576)bad('Eingabe zu groß (max 1 MB).');

$op=$t.'.'.$o;
switch($op){

/* AES-256-GCM */
case 'aes.enc':{needopenssl();$data=need('data');$kin=inp('key');
  $key=$kin!==''?b64d($kin,'Schlüssel'):random_bytes(32);
  if(strlen($key)!==32)bad('Schlüssel muss 32 Byte sein (Base64).');
  $iv=random_bytes(12);$tag='';
  $ct=openssl_encrypt($data,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'',16);
  if($ct===false)bad('Verschlüsselung fehlgeschlagen.');
  ok(['key'=>b64e($key),'out'=>b64e($iv.$tag.$ct)]);}
case 'aes.dec':{needopenssl();$raw=b64d(need('data'),'Chiffre');$key=b64d(need('key'),'Schlüssel');
  if(strlen($key)!==32)bad('Schlüssel muss 32 Byte sein (Base64).');
  if(strlen($raw)<28)bad('Chiffre zu kurz.');
  $iv=substr($raw,0,12);$tag=substr($raw,12,16);$ct=substr($raw,28);
  $pt=openssl_decrypt($ct,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
  if($pt===false)bad('Entschlüsselung fehlgeschlagen (falscher Schlüssel oder manipulierte Daten).');
  ok(['out'=>$pt]);}

/* RSA OAEP 3072 */
case 'rsa.gen':{needopenssl();$res=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
  if($res===false)bad('Schlüsselerzeugung fehlgeschlagen.');
  openssl_pkey_export($res,$sec);$d=openssl_pkey_get_details($res);
  ok(['pub'=>$d['key'],'sec'=>$sec]);}
case 'rsa.enc':{needopenssl();$data=need('data');$pk=openssl_pkey_get_public(need('pub'));
  if($pk===false)bad('Ungültiger Public Key.');
  if(!openssl_public_encrypt($data,$out,$pk,OPENSSL_PKCS1_OAEP_PADDING))bad('Verschlüsselung fehlgeschlagen (Klartext zu lang?).');
  ok(['out'=>b64e($out)]);}
case 'rsa.dec':{needopenssl();$raw=b64d(need('data'),'Chiffre');$sk=openssl_pkey_get_private(need('sec'));
  if($sk===false)bad('Ungültiger Private Key.');
  if(!openssl_private_decrypt($raw,$out,$sk,OPENSSL_PKCS1_OAEP_PADDING))bad('Entschlüsselung fehlgeschlagen.');
  ok(['out'=>$out]);}

/* Ed25519 */
case 'eds.gen':{needsodium();$kp=sodium_crypto_sign_keypair();
  ok(['pub'=>b64e(sodium_crypto_sign_publickey($kp)),'sec'=>b64e(sodium_crypto_sign_secretkey($kp))]);}
case 'eds.sgn':{needsodium();$data=need('data');$sk=b64d(need('sec'),'Secret Key');
  if(strlen($sk)!==SODIUM_CRYPTO_SIGN_SECRETKEYBYTES)bad('Secret Key hat falsche Länge.');
  ok(['sig'=>b64e(sodium_crypto_sign_detached($data,$sk))]);}
case 'eds.vrf':{needsodium();$data=need('data');$sig=b64d(need('sig'),'Signatur');$pk=b64d(need('pub'),'Public Key');
  if(strlen($pk)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)bad('Public Key hat falsche Länge.');
  $valid=strlen($sig)===SODIUM_CRYPTO_SIGN_BYTES&&sodium_crypto_sign_verify_detached($sig,$data,$pk);
  ok(['valid'=>$valid]);}

/* X25519 */
case 'dhe.gen':{needsodium();$kp=sodium_crypto_box_keypair();
  ok(['pub'=>b64e(sodium_crypto_box_publickey($kp)),'sec'=>b64e(sodium_crypto_box_secretkey($kp))]);}
case 'dhe.agr':{needsodium();$sk=b64d(need('sec'),'Secret Key');$pk=b64d(need('pub'),'Public Key');
  if(strlen($sk)!==SODIUM_CRYPTO_SCALARMULT_BYTES||strlen($pk)!==SODIUM_CRYPTO_SCALARMULT_BYTES)bad('Schlüssel muss 32 Byte sein.');
  ok(['shared'=>b64e(sodium_crypto_scalarmult($sk,$pk))]);}

/* HKDF-SHA256 */
case 'kdf.ext':{$ikm=need('ikm');$salt=inp('salt');
  ok(['prk'=>b64e(hash_hmac('sha256',$ikm,$salt,true))]);}
case 'kdf.exp':{$prk=b64d(need('prk'),'PRK');$info=inp('info');$len=(int)(inp('len')?:'32');
  if($len<1||$len>8160)bad('Länge muss 1..8160 sein.');
  $okm='';$t2='';$i=1;
  while(strlen($okm)<$len){$t2=hash_hmac('sha256',$t2.$info.chr($i),$prk,true);$okm.=$t2;$i++;}
  ok(['okm'=>b64e(substr($okm,0,$len))]);}

/* HMAC-SHA256 */
case 'mac.tag':{$data=need('data');$key=need('key');ok(['tag'=>hash_hmac('sha256',$data,$key)]);}
case 'mac.vrf':{$data=need('data');$key=need('key');$tag=strtolower(trim(need('tag')));
  ok(['valid'=>hash_equals(hash_hmac('sha256',$data,$key),$tag)]);}

/* SHA-256 */
case 'hsh.dig':{ok(['digest'=>hash('sha256',need('data'))]);}
case 'hsh.cmp':{$d=hash('sha256',need('data'));ok(['valid'=>hash_equals($d,strtolower(trim(need('digest'))))]);}

/* crypto_box (X25519-XSalsa20-Poly1305) */
case 'box.gen':{needsodium();$kp=sodium_crypto_box_keypair();
  ok(['pub'=>b64e(sodium_crypto_box_publickey($kp)),'sec'=>b64e(sodium_crypto_box_secretkey($kp))]);}
case 'box.enc':{needsodium();$data=need('data');$sk=b64d(need('sec'),'Secret Key');$pk=b64d(need('pub'),'Public Key');
  if(strlen($sk)!==SODIUM_CRYPTO_BOX_SECRETKEYBYTES||strlen($pk)!==SODIUM_CRYPTO_BOX_PUBLICKEYBYTES)bad('Schlüssel hat falsche Länge.');
  $nonce=random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
  $kp=sodium_crypto_box_keypair_from_secretkey_and_publickey($sk,$pk);
  ok(['out'=>b64e($nonce.sodium_crypto_box($data,$nonce,$kp))]);}
case 'box.dec':{needsodium();$raw=b64d(need('data'),'Chiffre');$sk=b64d(need('sec'),'Secret Key');$pk=b64d(need('pub'),'Public Key');
  $nb=SODIUM_CRYPTO_BOX_NONCEBYTES;
  if(strlen($raw)<=$nb)bad('Chiffre zu kurz.');
  $nonce=substr($raw,0,$nb);$ct=substr($raw,$nb);
  $kp=sodium_crypto_box_keypair_from_secretkey_and_publickey($sk,$pk);
  $pt=sodium_crypto_box_open($ct,$nonce,$kp);
  if($pt===false)bad('Entschlüsselung fehlgeschlagen (falsche Schlüssel oder manipulierte Daten).');
  ok(['out'=>$pt]);}

/* Argon2id */
case 'pwd.hsh':{if(!defined('PASSWORD_ARGON2ID'))bad('Argon2id nicht verfügbar (PHP ohne libargon2).');
  ok(['hash'=>password_hash(need('data'),PASSWORD_ARGON2ID)]);}
case 'pwd.vrf':{ok(['valid'=>password_verify(need('data'),need('hash'))]);}

default: bad('Operation nicht implementiert.');
}
