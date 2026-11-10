#!/usr/bin/env python3
"""HTTP local contra PHP real; shim mbstring SOLO en proceso de prueba; no toca OpenAI."""
import contextlib, http.client, json, os, pathlib, socket, subprocess, time, urllib.error, urllib.request
ROOT=pathlib.Path(__file__).resolve().parents[1]
RATE=ROOT/'private/logs/openai-rate-72e27'
SHIM=ROOT/'tests/php-mbstring-shim-72e29.php'

def request(method='POST', body=b'{"message":"Que es una API"}', ctype='application/json'):
    r=urllib.request.Request('http://127.0.0.1:'+str(PORT)+'/api/assistant-openai.php',data=body if method=='POST' else None,method=method,headers={'Content-Type':ctype})
    try:
        with urllib.request.urlopen(r,timeout=5) as response:return response.status,dict(response.headers),json.loads(response.read())
    except urllib.error.HTTPError as e:return e.code,dict(e.headers),json.loads(e.read())

with socket.socket() as sock:
    sock.bind(('127.0.0.1',0));PORT=sock.getsockname()[1]
if RATE.exists():
    for p in RATE.glob('*.json'):p.unlink() # only test-specific rate data; NOT use on production
cmd=['php','-d','auto_prepend_file='+str(SHIM),'-S','127.0.0.1:'+str(PORT),'-t',str(ROOT/'public')]
proc=subprocess.Popen(cmd,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
try:
    for _ in range(40):
        try:
            with socket.create_connection(('127.0.0.1',PORT),timeout=.2):break
        except OSError:time.sleep(.1)
    else:raise RuntimeError('Servidor PHP no arrancó')
    checks=[]
    for i in range(10):
        status,headers,data=request()
        checks.append(status==503 and data.get('unavailable') is True)
        print('POST válido',i+1,status)
    status,headers,data=request()
    checks.append(status==429 and headers.get('Retry-After')=='60' and data.get('ok') is False)
    print('POST undécimo',status,'Retry-After',headers.get('Retry-After'))
    status,headers,data=request('GET')
    checks.append(status==405)
    print('GET',status)
    status,headers,data=request(ctype='text/plain')
    checks.append(status==415)
    print('Content-Type incorrecto',status)
    status,headers,data=request(body=b'X'*12001)
    checks.append(status==413)
    print('Cuerpo demasiado grande',status)
    print(f'{sum(checks)}/{len(checks)} verificaciones HTTP')
    assert all(checks)
finally:
    proc.terminate()
    try:proc.communicate(timeout=4)
    except subprocess.TimeoutExpired:proc.kill();proc.communicate()
    if RATE.exists():
        for p in RATE.glob('*.json'):p.unlink()
