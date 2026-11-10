#!/usr/bin/env python3
"""Pruebas locales de concurrencia, límites y recuperación, sin llamadas externas."""
import concurrent.futures, json, os, pathlib, socket, subprocess, tempfile, time, urllib.request, urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
rate=root/'private/assistant/openai-rate-limit-72e27.php'
checks=[]
def check(name,condition):
    assert condition,name
    checks.append(name)
with tempfile.TemporaryDirectory(prefix='uco72e54_') as td:
    # 40 procesos independientes compiten por el mismo archivo bloqueado con flock.
    runner=pathlib.Path(td)/'rate.php'
    runner.write_text('<?php require '+repr(str(rate))+'; echo ucoOpenAIRateAllow72e27($argv[1],$argv[2],10,60)?"1":"0";',encoding='utf-8')
    def invoke(ip='127.0.0.1'):
        return subprocess.check_output(['php',str(runner),str(pathlib.Path(td)/'limits'),ip],timeout=8).decode()
    with concurrent.futures.ThreadPoolExecutor(max_workers=20) as pool:
        outcomes=list(pool.map(lambda _:invoke(),range(40)))
    check('Concurrencia: exactamente diez aceptadas',outcomes.count('1')==10)
    check('Concurrencia: treinta rechazadas',outcomes.count('0')==30)
    check('Rechazo tras alcanzar límite',invoke()=='0')
    check('IP independiente admitida',invoke('127.0.0.2')=='1')
    log=next((pathlib.Path(td)/'limits').glob('*.json'))
    check('Historial JSON íntegro',len(json.loads(log.read_text())) in (1,10))
    # Simulación de ventana vencida, sin esperar un minuto.
    import hashlib
    path=pathlib.Path(td)/'limits'/(hashlib.sha256(b'127.0.0.1').hexdigest()+'.json')
    path.write_text(json.dumps([int(time.time())-65]*10))
    check('Recuperación tras ventana vencida',invoke()=='1')
    check('Historial recuperado limpio',len(json.loads(path.read_text()))==1)
    # Estado corrupto: el limitador se recupera sin desbordar.
    path.write_text('JSON CORRUPTO')
    check('Recuperación tras estado corrupto',invoke()=='1')
    check('Estado corrupto reparado',len(json.loads(path.read_text()))==1)
    # Peticiones HTTP reales al endpoint local. Sin clave y sin curl: 503, nunca red externa.
    with socket.socket() as s:
        s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    env=os.environ.copy();env.pop('OPENAI_API_KEY',None)
    proc=subprocess.Popen(['php','-d',f'auto_prepend_file={root}/tests/php-mbstring-shim-72e41.php','-S',f'127.0.0.1:{port}','-t',str(root/'public')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:
                urllib.request.urlopen(f'http://127.0.0.1:{port}/',timeout=.2);break
            except Exception:time.sleep(.05)
        def request(message):
            data=json.dumps({'message':message}).encode()
            req=urllib.request.Request(f'http://127.0.0.1:{port}/api/assistant-openai.php',data=data,headers={'Content-Type':'application/json'})
            try:
                with urllib.request.urlopen(req,timeout=3) as r:return r.status,dict(r.headers),json.loads(r.read())
            except urllib.error.HTTPError as e:return e.code,dict(e.headers),json.loads(e.read())
        # Separar los contadores de los ensayos anteriores.
        ratefile=root/'private/logs/openai-rate-72e27'/(hashlib.sha256(b'127.0.0.1').hexdigest()+'.json')
        if ratefile.exists():ratefile.unlink()
        for i in range(10):
            status,headers,payload=request('Explica la fotosíntesis')
            check(f'HTTP consulta general {i+1}: 503 sin clave',status==503 and payload.get('unavailable') is True)
        status,headers,payload=request('Explica la fotosíntesis')
        check('HTTP solicitud 11: 429',status==429)
        check('HTTP 429 incluye Retry-After',headers.get('Retry-After')=='60')
        status,_,_=request('Mi factura privada 123')
        check('HTTP privacidad antes de limitación: 422',status==422)
        ratefile.write_text(json.dumps([int(time.time())-65]*10))
        status,_,payload=request('Explica la fotosíntesis')
        check('HTTP recuperación tras expiración: 503',status==503 and payload.get('unavailable') is True)
    finally:
        proc.terminate();proc.wait(timeout=5)
print(json.dumps({'passed':len(checks),'total':len(checks),'checks':checks},ensure_ascii=False,indent=2))
