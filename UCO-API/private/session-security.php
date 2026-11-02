<?php
declare(strict_types=1);
/* UCO 3E.4A: controles de sesión y limitación persistente de acceso. */
function ucoSecurityLogout():void {
    $_SESSION=[];
    if(ini_get('session.use_cookies')){
        $p=session_get_cookie_params();
        setcookie(session_name(),'',[
            'expires'=>time()-3600,'path'=>$p['path']?:'/',
            'domain'=>$p['domain'],'secure'=>(bool)$p['secure'],
            'httponly'=>(bool)$p['httponly'],'samesite'=>$p['samesite']?:'Strict'
        ]);
    }
    session_destroy();
}
function ucoSecurityIdleExpired(int $seconds=1800):bool {
    $now=time();
    $last=(int)($_SESSION['uco_last_activity']??$now);
    if($last>$now+60||$now-$last>$seconds){ucoSecurityLogout();return true;}
    $_SESSION['uco_last_activity']=$now;
    return false;
}
function ucoSecurityLoginSuccess():void {
    $_SESSION['uco_last_activity']=time();
}
function ucoSecurityThrottle(string $scope,string $identity,bool $success=false):bool {
    $folder=__DIR__.'/database';
    if(!is_dir($folder))throw new RuntimeException('Directorio privado de seguridad no disponible');
    $db=new PDO('sqlite:'.$folder.'/auth-rate-limit.sqlite',null,null,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT=>3
    ]);
    $db->exec('PRAGMA busy_timeout=3000');
    $db->exec('CREATE TABLE IF NOT EXISTS auth_attempts (key_hash TEXT PRIMARY KEY, attempts INTEGER NOT NULL, expires_at INTEGER NOT NULL)');
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    $key=hash('sha256',$scope.'|'.strtolower(trim($identity)).'|'.$ip);
    $now=time();
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM auth_attempts WHERE expires_at < ?')->execute([$now]);
        $q=$db->prepare('SELECT attempts FROM auth_attempts WHERE key_hash=?');
        $q->execute([$key]);$attempts=(int)($q->fetchColumn()?:0);
        if($success){
            $db->prepare('DELETE FROM auth_attempts WHERE key_hash=?')->execute([$key]);
            $blocked=false;
        } else {
            $blocked=$attempts>=8;
            if(!$blocked){
                $db->prepare('INSERT INTO auth_attempts(key_hash,attempts,expires_at) VALUES(?,?,?) ON CONFLICT(key_hash) DO UPDATE SET attempts=excluded.attempts,expires_at=excluded.expires_at')->execute([$key,$attempts+1,$now+900]);
            }
        }
        $db->commit();return $blocked;
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/* 3E.5: Nunca aceptar una comparación CSRF de dos tokens vacíos.
   Exige sesión con token emitido y cabecera explícita no vacía. */
function ucoSecurityCsrfValid(string $sessionKey):bool {
    $expected=$_SESSION[$sessionKey]??null;
    $received=$_SERVER['HTTP_X_CSRF_TOKEN']??null;
    return is_string($expected) && $expected!==''
        && is_string($received) && $received!==''
        && hash_equals($expected,$received);
}
