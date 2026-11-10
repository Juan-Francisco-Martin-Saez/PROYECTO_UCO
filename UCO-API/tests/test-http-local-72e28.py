#!/usr/bin/env python3
"""Prueba HTTP local sin API key ni conexión con OpenAI. No modifica datos."""
import subprocess,urllib.request,urllib.error,socket,time,json,sys
from pathlib import Path
root=Path(__file__).resolve().parents[1]
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(root/'public')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
 for _ in range(40):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.2):break
  except OSError:time.sleep(.1)
 url=f'http://127.0.0.1:{port}/api/assistant-openai.php'
 cases=[('GET rechazado','GET',None,{},405),('Content-Type rechazado','POST',b'{}',{'Content-Type':'text/plain'},415),('Carga > 12 KB rechazada','POST',b'x'*12001,{'Content-Type':'application/json'},413)]
 for label,method,data,headers,expected in cases:
  req=urllib.request.Request(url,data=data,headers=headers,method=method)
  try:
   with urllib.request.urlopen(req,timeout=5) as response:status=response.status;body=response.read()
  except urllib.error.HTTPError as e:status=e.code;body=e.read()
  assert status==expected,(label,status,expected)
  assert json.loads(body)['ok'] is False,label
  print('PASS',label,'HTTP',status)
 print('3/3 pruebas HTTP locales superadas; NO prueba OpenAI ni IONOS')
finally:
 server.terminate()
 try:server.wait(timeout=3)
 except subprocess.TimeoutExpired:server.kill()
