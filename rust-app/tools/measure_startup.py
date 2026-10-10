#!/usr/bin/env python3
"""Local process readiness only. This does NOT measure a Cloud Run cold start."""
import argparse,json,os,pathlib,subprocess,time,urllib.request
p=argparse.ArgumentParser();p.add_argument('--binary',default='rust-app/target/release/game-loadouts');p.add_argument('--runs',type=int,default=10);p.add_argument('--port',type=int,default=8089);p.add_argument('--output');a=p.parse_args()
if not 1<=a.runs<=100:p.error('runs must be 1..100')
# Bypass HTTP_PROXY for loopback; do not print environment or database credentials.
opener=urllib.request.build_opener(urllib.request.ProxyHandler({}));times=[];boot=[]
for _ in range(a.runs):
 env=os.environ.copy();env['PORT']=str(a.port)
 start=time.perf_counter();proc=subprocess.Popen([str(pathlib.Path(a.binary).resolve())],env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
 try:
  while time.perf_counter()-start<15:
   if proc.poll() is not None:raise RuntimeError('Application failed to start; inspect configuration')
   try:
    response=opener.open(f'http://127.0.0.1:{a.port}/healthz',timeout=.2)
    if response.status==200:times.append((time.perf_counter()-start)*1000);break
   except OSError:time.sleep(.005)
  else:raise RuntimeError('Readiness timeout')
 finally:
  proc.terminate()
  try:log,_=proc.communicate(timeout=10)
  except subprocess.TimeoutExpired:proc.kill();proc.communicate();raise RuntimeError('Graceful shutdown timeout')
  if proc.returncode!=0:raise RuntimeError('Graceful shutdown exited nonzero')
 for line in log.splitlines():
  try:
   record=json.loads(line)
   if record.get('fields',{}).get('message')=='ready':boot.append(record['fields']['startup_ms'])
  except ValueError:pass
result={'scope':'local process plus DB connection readiness; warm host/DB; excludes image pull and Cloud Run provisioning','runs':a.runs,'poll_interval_ms':5,'readiness_ms':times,'internal_startup_ms':boot};text=json.dumps(result,indent=2);print(text)
if a.output:pathlib.Path(a.output).write_text(text+'\n')
